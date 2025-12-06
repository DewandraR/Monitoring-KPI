#!/usr/bin/env python3

# yppr058_loader.py — T_ARBPL & T_PERNR + chunking + pair-lock + log unik
# Default tanpa tanggal: loop harian DESC dari kemarin → tanggal 1 bulan itu (satu per satu hari).
# Log disimpan di: <project>/storage/logs/python wc_person_mysql/

"""
Cara pakai ringkas (update):

0) Tarik hanya data HARI KEMARIN (1 hari saja)
   - Misal hari ini 2025-11-20 → akan memproses hanya 2025-11-19 (begda=endda=kemarin)

   python yppr058_loader.py --yesterday


1) Mode default (tanpa argumen) → per-hari DESC dari kemarin ke tgl 1 bulan itu
   Contoh (misal hari ini 2025-11-12):
   - akan memproses: 2025-11-11, 2025-11-10, ..., 2025-11-01 (satu per satu)

   python yppr058_loader.py


2) Batasi plant & pola ARBPL saat baca dari DB (multi-argumen boleh diulang)

   python yppr058_loader.py --werks-filter 1000 --werks-filter 3000 --like "WC%"


3) Pairs spesifik (tanpa tanggal; tanggal default = kemarin)

   python yppr058_loader.py --pairs "WC034:1000,WC035:3000"


4) Override tanggal range (format campur DD.MM.YYYY / YYYY-MM-DD / YYYYMMDD)
   - Range akan di-split per-hari (09→10→11). Jika ingin urutan lain, pakai --dates.

   python yppr058_loader.py --begda 2025-11-09 --endda 2025-11-11


5) Tarik hanya beberapa tanggal tertentu (urutan mengikuti input)

   python yppr058_loader.py --dates 2025-11-11,2025-11-10,2025-11-09


6) Lihat rencana tanpa ubah DB + tampilkan daftar pasangan

   python yppr058_loader.py --dry-run --show-pairs


7) Skip delete lama (hanya insert/update)

   python yppr058_loader.py --no-delete


CATATAN PRIORITAS MODE TANGGAL:
- Jika pakai --yesterday → selalu hanya hari kemarin (abaikan dates/begda/endda).
- Jika tidak pakai --yesterday tapi pakai --dates → ikuti daftar --dates.
- Jika tidak pakai --yesterday/--dates tapi pakai --begda/--endda → pakai range itu.
- Jika tidak ada semua → mode default (kemarin turun ke tanggal 1 bulan berjalan).

CATATAN SINKRON PERNR (BARU):
- Setiap run, sebelum tarik data baru, script akan:
  1) Bandingkan DISTINCT PERNR di wc_person_data vs yppr058_data
  2) Hapus semua baris di yppr058_data untuk PERNR yang sudah tidak ada di wc_person_data
     (kecuali jika pakai --dry-run atau --no-delete → hanya log, tidak hapus).
- Dengan ini, semua NIK di yppr058_data selalu mengikuti isi wc_person_data.
"""

import os, sys, re, argparse, signal, time, logging, datetime
from decimal import Decimal
from typing import Any, Dict, List, Optional, Tuple

from dotenv import load_dotenv
from pyrfc import (
    Connection,
    CommunicationError,
    LogonError,
    ABAPApplicationError,
    ABAPRuntimeError,
)
import mysql.connector
from pathlib import Path

# --- kompat pyrfc lama (refer 'long' Python2)
import builtins as _bt

if not hasattr(_bt, "long"):
    _bt.long = int

try:
    signal.signal(signal.SIGINT, signal.SIG_IGN)
except Exception:
    pass

load_dotenv()

# ---------- SAP ----------
DEFAULT_SAP = {
    "ashost": os.environ.get("SAP_ASHOST", "192.168.254.154"),
    "sysnr": os.environ.get("SAP_SYSNR", "01"),
    "client": os.environ.get("SAP_CLIENT", "300"),
    "lang": os.environ.get("SAP_LANG", "EN"),
}
SAP_USERNAME = os.environ.get("SAP_USER", "auto_email")
SAP_PASSWORD = os.environ.get("SAP_PASS", "11223344")
RFC_NAME = "Z_FM_YPPR058DX"

# ---------- MySQL ----------
DB_HOST = os.environ.get("DB_HOST", "127.0.0.1")
DB_PORT = int(os.environ.get("DB_PORT", "3306"))
DB_USER = os.environ.get("DB_USER", "root")
DB_PASS = os.environ.get("DB_PASS", "root")
DB_NAME = os.environ.get("DB_NAME", "wc_person")
WC_TABLE = os.environ.get("WC_TABLE", "wc_person_data")        # sumber pasangan + PERNR (+ desc)
OUT_TABLE = os.environ.get("DB_TABLE_OUT", "yppr058_data")     # target simpan hasil

# ---------- Logging ----------
PROJECT_ROOT = Path(__file__).resolve().parent

# Default log ke: <project>/storage/logs/python wc_person_mysql
DEFAULT_LOG_DIR = PROJECT_ROOT / "storage" / "logs" / "python wc_person_mysql"

# Bisa override via ENV: YPPR058_LOG_DIR
LOG_DIR = Path(os.environ.get("YPPR058_LOG_DIR", str(DEFAULT_LOG_DIR)))

LOG_DIR.mkdir(parents=True, exist_ok=True)

_start_ts = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
LOG_FILE = LOG_DIR / f"yppr058_loader_{os.getpid()}_{_start_ts}.log"

logger = logging.getLogger("yppr058")
logger.setLevel(logging.INFO)
formatter = logging.Formatter("%(asctime)s | %(message)s", datefmt="%H:%M:%S")

sh = logging.StreamHandler(sys.stdout)
sh.setLevel(logging.INFO)
sh.setFormatter(formatter)
fh = logging.FileHandler(str(LOG_FILE), mode="w", encoding="utf-8")
fh.setLevel(logging.INFO)
fh.setFormatter(formatter)
logger.addHandler(sh)
logger.addHandler(fh)

# ---------- Util ----------
WIDTH = 80


def hr(ch="="):
    logger.info(ch * WIDTH)


def subhr():
    logger.info("-" * WIDTH)


def title(s):
    hr("=")
    logger.info(s)
    hr("=")


def yyyymmdd(d: datetime.date) -> str:
    return d.strftime("%Y%m%d")


def yesterday_dats() -> str:
    dt = datetime.date.today() - datetime.timedelta(days=1)
    return yyyymmdd(dt)


def first_day_this_month() -> str:
    dt = datetime.date.today().replace(day=1)
    return yyyymmdd(dt)


def to_dats(s: str) -> str:
    s = (s or "").strip()
    if not s:
        return yesterday_dats()
    m = re.match(r"^(\d{2})\.(\d{2})\.(\d{4})$", s)   # DD.MM.YYYY
    if m:
        dd, mm, yy = m.groups()
        return f"{yy}{mm}{dd}"
    m = re.match(r"^(\d{4})-(\d{2})-(\d{2})$", s)     # YYYY-MM-DD
    if m:
        yy, mm, dd = m.groups()
        return f"{yy}{mm}{dd}"
    m = re.match(r"^\d{8}$", s)                       # YYYYMMDD
    if m:
        return s
    raise ValueError(f"Format tanggal tidak dikenali: {s}")


def parse_dates_list(s: str) -> List[str]:
    """Parse '--dates' jadi list YYYYMMDD, mempertahankan urutan input."""
    if not s.strip():
        return []
    out: List[str] = []
    for tok in s.split(","):
        tok = tok.strip()
        if not tok:
            continue
        out.append(to_dats(tok))
    return out


def daterange_inclusive(d1: datetime.date, d2: datetime.date):
    """Yield date per hari dari d1..d2 (inklusif)."""
    cur = d1
    while cur <= d2:
        yield cur
        cur += datetime.timedelta(days=1)


def dedupe_pairs(pairs: List[Tuple[str, str]]) -> List[Tuple[str, str]]:
    seen = set()
    out = []
    for a, w in pairs:
        key = (a.strip().upper(), w.strip().upper())
        if key in seen:
            continue
        seen.add(key)
        out.append((a.strip(), w.strip()))
    return out


def like_to_regex(pattern: str):
    esc = re.escape(pattern).replace(r"\%", ".*").replace(r"\_", ".")
    return re.compile(f"^{esc}$", re.IGNORECASE)


# ---------- Helper PERNR standar (BARU) ----------

def norm_pernr(p: Any) -> str:
    """Normalisasi PERNR ke string 8 digit kalau numerik."""
    if p is None:
        return ""
    s = str(p).strip()
    return s.zfill(8) if s.isdigit() else s


# ---------- MySQL helpers ----------
DDL_DB = f"""
CREATE DATABASE IF NOT EXISTS `{DB_NAME}`
  DEFAULT CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
"""

DDL_TABLE = f"""
CREATE TABLE IF NOT EXISTS `{OUT_TABLE}` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pernr`   VARCHAR(12) NOT NULL,
  `begda`   CHAR(8)     NOT NULL,
  `total_jam` DECIMAL(16,1) NULL,
  `mint2`   INT NULL,
  `mintu`   INT NULL,
  `mintu2`  INT NULL,
  `mintu3`  INT NULL,
  `cname`   VARCHAR(120) NULL,
  `gji`     DECIMAL(16,2) NULL,
  `gji2`    DECIMAL(16,2) NULL,
  `varnt`   DECIMAL(16,2) NULL,
  `varnt1`  DECIMAL(16,2) NULL,
  `arbpl`   VARCHAR(30) NOT NULL,
  `arbpl2`  VARCHAR(30) NULL,
  `shift`   INT NULL,
  `werks`   VARCHAR(10) NOT NULL,
  `desc`    VARCHAR(255) NULL,
  `role`    VARCHAR(20) NULL,
  `devisi`  VARCHAR(100) NULL,
  `source_rfc` VARCHAR(64) NOT NULL DEFAULT '{RFC_NAME}',
  `inserted_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_yppr058` (`pernr`,`begda`,`arbpl`,`werks`),
  KEY `idx_pernr` (`pernr`),
  KEY `idx_arbpl` (`arbpl`),
  KEY `idx_werks` (`werks`),
  KEY `idx_begda` (`begda`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
"""


UPSERT_SQL = f"""
INSERT INTO `{OUT_TABLE}`
(`pernr`,`begda`,`total_jam`,`mint2`,`mintu`,`mintu2`,`mintu3`,
 `cname`,`gji`,`gji2`,`varnt`,`varnt1`,
 `arbpl`,`arbpl2`,`shift`,`werks`,`desc`,`role`,`devisi`,`source_rfc`)
VALUES (%(pernr)s,%(begda)s,%(total_jam)s,%(mint2)s,%(mintu)s,%(mintu2)s,%(mintu3)s,
        %(cname)s,%(gji)s,%(gji2)s,%(varnt)s,%(varnt1)s,
        %(arbpl)s,%(arbpl2)s,%(shift)s,%(werks)s,%(desc)s,%(role)s,%(devisi)s,'{RFC_NAME}')
ON DUPLICATE KEY UPDATE
  `total_jam`=VALUES(`total_jam`),
  `mint2`=VALUES(`mint2`),
  `mintu`=VALUES(`mintu`),
  `mintu2`=VALUES(`mintu2`),
  `mintu3`=VALUES(`mintu3`),
  `cname`=VALUES(`cname`),
  `gji`=VALUES(`gji`),
  `gji2`=VALUES(`gji2`),
  `varnt`=VALUES(`varnt`),
  `varnt1`=VALUES(`varnt1`),
  `arbpl2`=VALUES(`arbpl2`),
  `shift`=VALUES(`shift`),
  `desc`=VALUES(`desc`),
  `role`=VALUES(`role`),
  `devisi`=VALUES(`devisi`),
  `inserted_at`=CURRENT_TIMESTAMP
"""


DELETE_SQL = f"""
DELETE FROM `{OUT_TABLE}`
WHERE `arbpl`=%s AND `werks`=%s AND `begda` BETWEEN %s AND %s
"""

COUNT_SQL = f"""
SELECT COUNT(*) FROM `{OUT_TABLE}`
WHERE `arbpl`=%s AND `werks`=%s AND `begda` BETWEEN %s AND %s
"""


def get_mysql_conn(database: Optional[str] = None):
    return mysql.connector.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USER,
        password=DB_PASS,
        database=database,
        autocommit=False,
    )


def ensure_db_and_table():
    root = get_mysql_conn(None)
    cur = root.cursor()
    cur.execute(DDL_DB)
    cur.close()
    root.commit()
    root.close()

    conn = get_mysql_conn(DB_NAME)
    cur2 = conn.cursor()
    cur2.execute(DDL_TABLE)
    cur2.close()
    conn.commit()

    # Pastikan kolom tambahan ada untuk instalasi lama
    ensure_shift_column_exists(conn)
    ensure_desc_column_exists(conn)
    ensure_role_column_exists(conn)
    ensure_devisi_column_exists(conn)
    return conn


def ensure_shift_column_exists(conn):
    check_sql = """
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME='shift'
    """
    cur = conn.cursor()
    try:
        cur.execute(check_sql, (DB_NAME, OUT_TABLE))
        exists = int(cur.fetchone()[0]) > 0
        if not exists:
            cur.execute(
                f"ALTER TABLE `{OUT_TABLE}` ADD COLUMN `shift` INT NULL AFTER `arbpl2`"
            )
            conn.commit()
            logger.info("[DDL] Kolom `shift` ditambahkan ke tabel hasil.")
    except mysql.connector.Error as e:
        conn.rollback()
        logger.info(f"[ERROR] ALTER TABLE add shift: {e}")
        raise
    finally:
        cur.close()


def ensure_desc_column_exists(conn):
    """
    Tambahkan kolom `desc` ke tabel hasil jika belum ada
    (untuk instalasi lama yang belum punya kolom ini).
    """
    check_sql = """
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME='desc'
    """
    cur = conn.cursor()
    try:
        cur.execute(check_sql, (DB_NAME, OUT_TABLE))
        exists = int(cur.fetchone()[0]) > 0
        if not exists:
            cur.execute(
                f"ALTER TABLE `{OUT_TABLE}` ADD COLUMN `desc` VARCHAR(255) NULL AFTER `werks`"
            )
            conn.commit()
            logger.info("[DDL] Kolom `desc` ditambahkan ke tabel hasil.")
    except mysql.connector.Error as e:
        conn.rollback()
        logger.info(f"[ERROR] ALTER TABLE add desc: {e}")
        raise
    finally:
        cur.close()


def ensure_role_column_exists(conn):
    """
    Tambahkan kolom `role` ke tabel hasil jika belum ada.
    """
    check_sql = """
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME='role'
    """
    cur = conn.cursor()
    try:
        cur.execute(check_sql, (DB_NAME, OUT_TABLE))
        exists = int(cur.fetchone()[0]) > 0
        if not exists:
            cur.execute(
                f"ALTER TABLE `{OUT_TABLE}` "
                "ADD COLUMN `role` VARCHAR(20) NULL AFTER `desc`"
            )
            conn.commit()
            logger.info("[DDL] Kolom `role` ditambahkan ke tabel hasil.")
    except mysql.connector.Error as e:
        conn.rollback()
        logger.info(f"[ERROR] ALTER TABLE add role: {e}")
        raise
    finally:
        cur.close()


def ensure_devisi_column_exists(conn):
    """
    Tambahkan kolom `devisi` ke tabel hasil jika belum ada.
    """
    check_sql = """
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME='devisi'
    """
    cur = conn.cursor()
    try:
        cur.execute(check_sql, (DB_NAME, OUT_TABLE))
        exists = int(cur.fetchone()[0]) > 0
        if not exists:
            cur.execute(
                f"ALTER TABLE `{OUT_TABLE}` "
                "ADD COLUMN `devisi` VARCHAR(100) NULL AFTER `role`"
            )
            conn.commit()
            logger.info("[DDL] Kolom `devisi` ditambahkan ke tabel hasil.")
    except mysql.connector.Error as e:
        conn.rollback()
        logger.info(f"[ERROR] ALTER TABLE add devisi: {e}")
        raise
    finally:
        cur.close()


def fetch_pairs_from_db(conn) -> List[Tuple[str, str]]:
    sql = (
        f"SELECT DISTINCT arbpl, werks FROM `{WC_TABLE}` "
        "WHERE arbpl<>'' AND werks<>''"
    )
    cur = conn.cursor()
    cur.execute(sql)
    rows = [(a or "", w or "") for (a, w) in cur.fetchall()]
    cur.close()
    return dedupe_pairs(rows)


def get_role_map_for_pair(
    conn,
    arbpl: str,
    werks: str,
    at_yyyymmdd: str,
) -> Dict[str, Optional[str]]:
    """
    Ambil mapping PERNR -> role dari wc_person_data untuk ARBPL/WERKS
    pada tanggal tertentu.
    Jika kolom `role` belum ada atau error, return {}.
    """
    q = f"""
        SELECT DISTINCT pernr, role
        FROM `{WC_TABLE}`
        WHERE arbpl=%s AND werks=%s
          AND begda <= %s AND endda >= %s
          AND pernr <> ''
          AND role IS NOT NULL AND role <> ''
    """
    cur = conn.cursor()
    mapping: Dict[str, Optional[str]] = {}
    try:
        cur.execute(q, (arbpl, werks, at_yyyymmdd, at_yyyymmdd))
        for pernr, role in cur.fetchall():
            s = norm_pernr(pernr)
            if s:
                mapping[s] = role
    except mysql.connector.Error as e:
        logger.info(
            f"[WARN] Gagal mengambil role WC (ARBPL={arbpl}, WERKS={werks}): {e}"
        )
    finally:
        cur.close()
    return mapping


def fetch_pernrs_for_pair(conn, arbpl: str, werks: str, at_yyyymmdd: str) -> List[str]:
    """
    Ambil semua PERNR aktif di wc_person_data untuk ARBPL/WERKS pada tanggal 'at_yyyymmdd'.
    Menggunakan rentang begda/endda dari wc_person_data.
    """
    q = f"""
        SELECT DISTINCT pernr
        FROM `{WC_TABLE}`
        WHERE arbpl=%s AND werks=%s
          AND begda <= %s AND endda >= %s
          AND pernr <> ''
    """
    cur = conn.cursor()
    cur.execute(q, (arbpl, werks, at_yyyymmdd, at_yyyymmdd))
    out: List[str] = []
    for (p,) in cur.fetchall():
        s = norm_pernr(p)
        if s:
            out.append(s)
    cur.close()
    return sorted(set(out))


def get_wc_meta(conn, arbpl: str, werks: str) -> Tuple[Optional[str], Optional[str]]:
    """
    Ambil deskripsi WC (`desc`) dan `devisi` dari tabel wc_person_data
    berdasarkan pasangan ARBPL/WERKS.

    Jika tidak ditemukan atau kolom belum ada, return (None, None).
    """
    sql = (
        f"SELECT `desc`, `devisi` FROM `{WC_TABLE}` "
        "WHERE arbpl=%s AND werks=%s "
        "  AND ( (`desc` IS NOT NULL AND `desc`<>'') "
        "     OR (`devisi` IS NOT NULL AND `devisi`<>'') ) "
        "LIMIT 1"
    )
    cur = conn.cursor()
    try:
        cur.execute(sql, (arbpl, werks))
        row = cur.fetchone()
        if not row:
            return None, None
        return row[0], row[1]
    except mysql.connector.Error as e:
        logger.info(
            f"[WARN] Gagal mengambil desc/devisi WC (ARBPL={arbpl}, WERKS={werks}): {e}"
        )
        return None, None
    finally:
        cur.close()


# ---------- Pair lock (hindari tabrakan dua proses di pair yang sama) ----------
def acquire_pair_lock(cur, arbpl: str, werks: str, timeout: int = 120) -> bool:
    cur.execute(
        "SELECT GET_LOCK(CONCAT('yppr058:', %s, ':', %s), %s)",
        (arbpl, werks, timeout),
    )
    row = cur.fetchone()
    return bool(row and row[0] == 1)


def release_pair_lock(cur, arbpl: str, werks: str):
    try:
        cur.execute(
            "SELECT RELEASE_LOCK(CONCAT('yppr058:', %s, ':', %s))", (arbpl, werks)
        )
        cur.fetchone()
    except Exception:
        pass


# ---------- RFC helpers ----------
def call_rfc(
    conn: Connection,
    arbpl: str,
    werks: str,
    begda: str,
    endda: str,
    pernrs: List[str],
) -> Dict[str, Any]:
    """
    Panggil RFC dengan TABLES: T_ARBPL (1 row) dan T_PERNR (list).
    """
    return conn.call(
        RFC_NAME,
        P_BEGDA=begda,
        P_ENDDA=endda,
        P_WERKS=werks,
        P_ARBPL=arbpl,
        T_ARBPL=[{"ARBPL": arbpl}],
        T_PERNR=[{"PERNR": p} for p in pernrs],
    )


def norm_val(x):
    if x is None:
        return None
    if isinstance(x, Decimal):
        return x
    try:
        return Decimal(str(x)) if isinstance(x, (int, float)) else x
    except Exception:
        return x


def normalize_tdata(
    row: Dict[str, Any],
    arbpl: str,
    werks: str,
    desc_value: Optional[str],
    devisi_value: Optional[str],
    role_map: Optional[Dict[str, Optional[str]]] = None,
) -> Dict[str, Any]:
    S = lambda v: "" if v is None else str(v)
    I = lambda v: None if S(v) == "" else int(S(v))
    D = lambda v: None if v in (None, "") else norm_val(v)

    pernr_raw = S(row.get("PERNR"))
    pernr_norm = pernr_raw.zfill(8) if pernr_raw.isdigit() else pernr_raw

    role_value = None
    if role_map:
        role_value = role_map.get(pernr_norm)

    return {
        "pernr": pernr_norm,
        "begda": S(row.get("BEGDA")),
        "total_jam": D(row.get("TOTAL_JAM")),
        "mint2": I(row.get("MINT2")),
        "mintu": I(row.get("MINTU")),
        "mintu2": I(row.get("MINTU2")),
        "mintu3": I(row.get("MINTU3")),
        "cname": S(row.get("CNAME")),
        "gji": D(row.get("GJI")),
        "gji2": D(row.get("GJI2")),
        "varnt": D(row.get("VARNT")),
        "varnt1": D(row.get("VARNT1")),
        "arbpl": S(row.get("ARBPL")) or arbpl,
        "arbpl2": S(row.get("ARBPL2")),
        "shift": I(row.get("SHIFT")),
        "werks": werks,
        "desc": desc_value,
        "role": role_value,
        "devisi": devisi_value,
    }


# ---------- Rekap Plant & Sinkronisasi PERNR (BARU) ----------

def get_plant_nik_counts(conn, table_name: str) -> Dict[str, int]:
    """
    Ambil jumlah DISTINCT PERNR per plant (WERKS) dari sebuah tabel.
    Dipakai untuk rekap seperti kartu 'Jumlah NIK' per plant.
    """
    cur = conn.cursor()
    sql = f"""
        SELECT werks, COUNT(DISTINCT pernr)
        FROM `{table_name}`
        WHERE pernr <> ''
        GROUP BY werks
        ORDER BY werks
    """
    counts: Dict[str, int] = {}
    try:
        cur.execute(sql)
        for werks, cnt in cur.fetchall():
            w = "" if werks is None else str(werks).strip()
            counts[w] = int(cnt or 0)
    finally:
        cur.close()
    return counts


def sync_yppr_with_wc_person(conn, dry_run: bool = False) -> None:
    """
    Pastikan isi yppr058_data selalu konsisten dengan wc_person_data.

    Level 1  : buang PERNR yang sama sekali tidak ada di wc_person_data.
    Level 2  : untuk PERNR yang masih ada di wc_person_data, buang kombinasi
               (PERNR, ARBPL, WERKS) yang tidak ada lagi di wc_person_data.
    """
    hr("=")
    logger.info("CEK SINKRON PERNR antara wc_person_data dan yppr058_data ...")

    cur = conn.cursor()

    # DISTINCT PERNR dari wc_person_data
    cur.execute(
        f"SELECT DISTINCT pernr FROM `{WC_TABLE}` "
        "WHERE pernr IS NOT NULL AND pernr<>''"
    )
    wc_pernrs = {norm_pernr(p) for (p,) in cur.fetchall() if p}

    # DISTINCT PERNR dari yppr058_data
    cur.execute(
        f"SELECT DISTINCT pernr FROM `{OUT_TABLE}` "
        "WHERE pernr IS NOT NULL AND pernr<>''"
    )
    yppr_pernrs = {norm_pernr(p) for (p,) in cur.fetchall() if p}

    missing_pernrs = sorted(yppr_pernrs - wc_pernrs)

    logger.info(
        f"TOTAL PERNR wc_person_data : {len(wc_pernrs)} | "
        f"TOTAL PERNR yppr058_data : {len(yppr_pernrs)}"
    )

    # Rekap plant sebelum sync
    wc_counts = get_plant_nik_counts(conn, WC_TABLE)
    yp_counts_before = get_plant_nik_counts(conn, OUT_TABLE)

    subhr()
    logger.info("REKAP DISTINCT NIK per PLANT (sebelum sync YPPR vs WC):")
    logger.info("PLANT | NIK di WC_PERSON | NIK di YPPR")
    subhr()
    all_plants = sorted(set(list(wc_counts.keys()) + list(yp_counts_before.keys())))
    for w in all_plants:
        c_wc = wc_counts.get(w, 0)
        c_yp = yp_counts_before.get(w, 0)
        logger.info(f"{w or '-':<5} | {c_wc:>15} | {c_yp:>11}")
    subhr()

    # -------------------- LEVEL 1: SYNC PERNR --------------------
    if not missing_pernrs:
        logger.info(
            "OK: Semua PERNR di yppr058_data sudah ada di wc_person_data "
            "(level PERNR)."
        )
    else:
        logger.info(
            f"WARNING: DITEMUKAN {len(missing_pernrs)} PERNR di {OUT_TABLE} "
            f"yang sudah tidak ada di {WC_TABLE}."
        )
        logger.info(
            "   Contoh PERNR yang akan diproses: "
            + ", ".join(missing_pernrs[:20])
            + (" ..." if len(missing_pernrs) > 20 else "")
        )

        # Rekap berapa NIK & baris yg akan kena per plant
        placeholders = ",".join(["%s"] * len(missing_pernrs))
        stats_sql = f"""
            SELECT werks, COUNT(DISTINCT pernr) AS nik_cnt, COUNT(*) AS row_cnt
            FROM `{OUT_TABLE}`
            WHERE pernr IN ({placeholders})
            GROUP BY werks
            ORDER BY werks
        """
        try:
            cur.execute(stats_sql, missing_pernrs)
            stats = cur.fetchall()
        except Exception as e:
            logger.info(f"[WARN] Gagal hitung statistik per-plant untuk PERNR hilang: {e}")
            stats = []

        if stats:
            subhr()
            logger.info("DETAIL NIK YANG AKAN DIHAPUS (level PERNR; berdasarkan PLANT di YPPR):")
            logger.info("PLANT | NIK YPPR TIDAK ADA DI WC | JUMLAH BARIS YANG AKAN DIHAPUS")
            subhr()
            for werks, nik_cnt, row_cnt in stats:
                logger.info(
                    f"{(werks or '-'):>5} | {int(nik_cnt):>24} | {int(row_cnt):>29}"
                )
            subhr()

        if dry_run:
            logger.info(
                "[DRY-RUN / --no-delete] Mode simulasi, TIDAK ada baris yg dihapus "
                "dari yppr058_data (level PERNR)."
            )
        else:
            total_deleted_pernr = 0
            BATCH_DEL = 500
            logger.info(
                f"Mulai menghapus data YPPR untuk {len(missing_pernrs)} PERNR "
                f"dalam batch {BATCH_DEL} (level PERNR)..."
            )
            for i in range(0, len(missing_pernrs), BATCH_DEL):
                batch = missing_pernrs[i : i + BATCH_DEL]
                ph = ",".join(["%s"] * len(batch))
                del_sql = f"DELETE FROM `{OUT_TABLE}` WHERE pernr IN ({ph})"
                try:
                    cur.execute(del_sql, batch)
                    total_deleted_pernr += cur.rowcount
                except mysql.connector.Error as e:
                    logger.info(f"[ERROR] DELETE batch PERNR: {e}")
                    conn.rollback()
                    cur.close()
                    hr("=")
                    return
            conn.commit()
            logger.info(
                f"Hapus selesai (level PERNR). Total {total_deleted_pernr} baris di {OUT_TABLE} "
                f"untuk {len(missing_pernrs)} PERNR yang sudah tidak ada di WC Person."
            )

    # ---------------- LEVEL 2: SYNC (PERNR, ARBPL, WERKS) ----------------
    subhr()
    logger.info(
        "CEK SINKRON KOMBINASI (PERNR, ARBPL, WERKS) antara wc_person_data "
        "dan yppr058_data ..."
    )

    # Ambil semua kombinasi di WC (master)
    cur.execute(
        f"""
        SELECT DISTINCT pernr, arbpl, werks
        FROM `{WC_TABLE}`
        WHERE pernr IS NOT NULL AND pernr<>'' 
          AND arbpl IS NOT NULL AND arbpl<>'' 
          AND werks IS NOT NULL AND werks<>''
        """
    )
    wc_pairs = set()
    for pernr, arbpl, werks in cur.fetchall():
        p = norm_pernr(pernr)
        if not p:
            continue
        a = (arbpl or "").strip()
        w = (werks or "").strip()
        if not a or not w:
            continue
        wc_pairs.add((p, a, w))

    # Ambil semua kombinasi di YPPR (hasil)
    cur.execute(
        f"""
        SELECT DISTINCT pernr, arbpl, werks
        FROM `{OUT_TABLE}`
        WHERE pernr IS NOT NULL AND pernr<>'' 
          AND arbpl IS NOT NULL AND arbpl<>'' 
          AND werks IS NOT NULL AND werks<>''
        """
    )
    yp_pairs = set()
    for pernr, arbpl, werks in cur.fetchall():
        p = norm_pernr(pernr)
        if not p:
            continue
        a = (arbpl or "").strip()
        w = (werks or "").strip()
        if not a or not w:
            continue
        yp_pairs.add((p, a, w))

    logger.info(
        f"TOTAL pasangan WC   (PERNR,ARBPL,WERKS): {len(wc_pairs)}"
    )
    logger.info(
        f"TOTAL pasangan YPPR (PERNR,ARBPL,WERKS): {len(yp_pairs)}"
    )

    # Kombinasi yang ADA di YPPR tapi TIDAK ADA di WC
    # namun PERNR-nya masih ada di WC (sesuai requirement-mu).
    combos_missing = {
        (p, a, w)
        for (p, a, w) in yp_pairs
        if (p in wc_pernrs) and ((p, a, w) not in wc_pairs)
    }

    if not combos_missing:
        logger.info(
            "OK: Tidak ada kombinasi (PERNR,ARBPL,WERKS) di yppr058_data "
            "yang tidak ada di wc_person_data."
        )
        # Rekap akhir dan selesai
    else:
        logger.info(
            f"WARNING: ditemukan {len(combos_missing)} kombinasi (PERNR,ARBPL,WERKS) "
            f"di {OUT_TABLE} yang tidak ada di {WC_TABLE}."
        )
        sample = ", ".join(
            [f"{p}:{a}:{w}" for (p, a, w) in list(combos_missing)[:20]]
        )
        logger.info(
            "   Contoh kombinasi yang akan diproses: "
            + sample
            + (" ..." if len(combos_missing) > 20 else "")
        )

        # Hitung statistik baris yang akan dihapus
        triple_list = list(combos_missing)
        stats = []
        BATCH_COMBO = 300
        try:
            for i in range(0, len(triple_list), BATCH_COMBO):
                batch = triple_list[i : i + BATCH_COMBO]
                ph = ",".join(["(%s,%s,%s)"] * len(batch))
                stats_sql2 = f"""
                    SELECT pernr, arbpl, werks, COUNT(*) AS row_cnt
                    FROM `{OUT_TABLE}`
                    WHERE (pernr, arbpl, werks) IN ({ph})
                    GROUP BY pernr, arbpl, werks
                """
                params = []
                for p, a, w in batch:
                    params.extend([p, a, w])
                cur.execute(stats_sql2, params)
                stats.extend(cur.fetchall())
        except mysql.connector.Error as e:
            logger.info(
                f"[WARN] Gagal hitung statistik baris untuk kombinasi PERNR-ARBPL-WERKS: {e}"
            )
            stats = []

        if stats:
            subhr()
            logger.info(
                "DETAIL KOMBINASI YANG AKAN DIBERSIHKAN (level PERNR+ARBPL+WERKS):"
            )
            logger.info("PERNR    | ARBPL      | WERKS | JUMLAH BARIS")
            subhr()
            for pernr, arbpl, werks, row_cnt in stats:
                logger.info(
                    f"{(pernr or '-'):>8} | {(arbpl or '-'):<10} | "
                    f"{(werks or '-'):>5} | {int(row_cnt):>12}"
                )
            subhr()

        if dry_run:
            logger.info(
                "[DRY-RUN / --no-delete] Mode simulasi, TIDAK ada baris yg dihapus "
                "untuk kombinasi (PERNR,ARBPL,WERKS) tersebut."
            )
        else:
            total_deleted_pairs = 0
            logger.info(
                f"Mulai menghapus baris YPPR berdasarkan {len(combos_missing)} "
                "kombinasi (PERNR,ARBPL,WERKS) yang sudah tidak ada di WC Person ..."
            )
            for i in range(0, len(triple_list), BATCH_COMBO):
                batch = triple_list[i : i + BATCH_COMBO]
                ph = ",".join(["(%s,%s,%s)"] * len(batch))
                del_sql2 = f"""
                    DELETE FROM `{OUT_TABLE}`
                    WHERE (pernr, arbpl, werks) IN ({ph})
                """
                params = []
                for p, a, w in batch:
                    params.extend([p, a, w])
                try:
                    cur.execute(del_sql2, params)
                    total_deleted_pairs += cur.rowcount
                except mysql.connector.Error as e:
                    logger.info(
                        f"[ERROR] DELETE batch kombinasi PERNR-ARBPL-WERKS: {e}"
                    )
                    conn.rollback()
                    cur.close()
                    hr("=")
                    return

            conn.commit()
            logger.info(
                    f"[OK] Hapus selesai (level PERNR+ARBPL+WERKS). "
                    f"Total {total_deleted_pairs} baris di {OUT_TABLE} "
                    "yang kombinasi WC-nya sudah tidak ada di wc_person_data."
                )

    # Rekap plant SESUDAH semua sync
    yp_counts_after = get_plant_nik_counts(conn, OUT_TABLE)
    subhr()
    logger.info("REKAP DISTINCT NIK per PLANT (setelah sync YPPR vs WC):")
    logger.info("PLANT | NIK di WC_PERSON | NIK di YPPR (sesudah)")
    subhr()
    all_plants2 = sorted(set(list(wc_counts.keys()) + list(yp_counts_after.keys())))
    for w in all_plants2:
        c_wc = wc_counts.get(w, 0)
        c_yp = yp_counts_after.get(w, 0)
        logger.info(f"{w or '-':<5} | {c_wc:>15} | {c_yp:>19}")
    subhr()
    hr("=")
    cur.close()

# ---------- Core per-hari ----------
def process_one_day(
    rfc: Connection,
    db,
    args,
    pairs: List[Tuple[str, str]],
    begda: str,
    endda: str,
) -> Tuple[int, int]:
    """
    Proses satu hari (begda==endda). Return (total_inserted, total_deleted).
    """
    day_start = datetime.datetime.now()
    hr("=")
    logger.info(f"HARI  : {begda} .. {endda}")
    hr("=")

    total_rows = 0
    total_deleted = 0
    summary: List[Tuple[str, str, int, int, int, float]] = []

    cur = db.cursor()

    for (arbpl, werks) in pairs:
        t0 = time.perf_counter()

        # Ambil deskripsi & devisi WC dari wc_person_data untuk pasangan ini
        desc_for_pair, devisi_for_pair = get_wc_meta(db, arbpl, werks)

        # Kumpulkan PERNR untuk pasangan ini (aktif pada tanggal)
        pernr_all = fetch_pernrs_for_pair(db, arbpl, werks, begda)

        # Ambil role per PERNR (kalau kolom `role` ada)
        role_map_for_pair = get_role_map_for_pair(db, arbpl, werks, begda)

        logger.info(
            f"PAIR  : ARBPL={arbpl} | WERKS={werks} | {begda}..{endda} | "
            f"{len(pernr_all)} PERNR aktif"
        )

        if args.verbose_steps:
            sample = ", ".join(pernr_all[: args.sample_log]) if pernr_all else "-"
            logger.info(
                f"  BUILD T_PERNR : {len(pernr_all)} orang (contoh: {sample})"
            )
            logger.info(f"  BUILD T_ARBPL : 1 item ({arbpl})")

        fetched_total = 0
        rows_accum: List[Dict[str, Any]] = []

        # Jika tidak ada PERNR aktif → lewati
        if not pernr_all:
            if args.verbose_steps:
                logger.info("  SKIP RFC     : 0 PERNR aktif → lewati.")
            summary.append(
                (arbpl, werks, 0, 0, 0, time.perf_counter() - t0)
            )
            continue

        # Panggil RFC per-chunk PERNR
        chunk_size = max(1, args.pernr_chunk)
        chunks = [
            pernr_all[i: i + chunk_size]
            for i in range(0, len(pernr_all), chunk_size)
        ]

        for idx, chunk in enumerate(chunks, start=1):
            if args.verbose_steps:
                logger.info(
                    f"  CALL RFC     : chunk {idx}/{len(chunks)} (size {len(chunk)}) ..."
                )
            try:
                resp = call_rfc(rfc, arbpl, werks, begda, endda, chunk)
            except (ABAPApplicationError, ABAPRuntimeError, CommunicationError) as e:
                msg = str(e)
                if "RFC_CLOSED" in msg and "Data Kosong" in msg:
                    if args.verbose_steps:
                        logger.info(
                            "  [SAP] Data kosong untuk chunk ini, lanjut."
                        )
                    continue
                logger.info(f"  [ERROR] RFC  : {e}")
                continue

            # RETURN SAP
            returns = resp.get("RETURN") or []
            for r in returns:
                typ = (r.get("TYPE") or "").upper()
                msg = r.get("MESSAGE") or ""
                if typ in ("E", "A"):
                    logger.info(f"  [SAP-{typ}] {msg}")
                elif args.verbose_steps and msg:
                    logger.info(f"  [SAP-{typ}] {msg}")

            t_data = resp.get("T_DATA") or []
            fetched_total += len(t_data)
            if t_data:
                rows_accum.extend(
                    [
                        normalize_tdata(
                            r,
                            arbpl,
                            werks,
                            desc_for_pair,
                            devisi_for_pair,
                            role_map_for_pair,
                        )
                        for r in t_data
                    ]
                )

        # Hitung bakal dihapus
        cur.execute(COUNT_SQL, (arbpl, werks, begda, endda))
        would_delete = int(cur.fetchone()[0])

        # Lock pair saat DELETE/UPSERT
        locked = acquire_pair_lock(
            cur, arbpl, werks, timeout=args.lock_timeout
        )
        if not locked:
            logger.info("  [LOCK] Gagal acquire lock pair; lewati pasangan ini.")
            summary.append(
                (arbpl, werks, 0, fetched_total, 0, time.perf_counter() - t0)
            )
            continue

        try:
            # Delete selektif
            if args.no_delete:
                deleted = 0
                if args.verbose_steps:
                    logger.info("  DELETE       : [LEWATI] (--no-delete)")
            elif args.dry_run:
                deleted = would_delete
                if args.verbose_steps:
                    logger.info(
                        f"  DELETE       : [DRY-RUN] {would_delete} baris"
                    )
            else:
                try:
                    cur.execute(DELETE_SQL, (arbpl, werks, begda, endda))
                    deleted = cur.rowcount
                    db.commit()
                    if args.verbose_steps:
                        logger.info(
                            f"  DELETE       : {deleted} baris dihapus"
                        )
                except mysql.connector.Error as e:
                    db.rollback()
                    logger.info(f"  [ERROR] DB DELETE: {e}")
                    deleted = 0

            # Insert/Upsert
            inserted = 0
            if args.dry_run:
                inserted = fetched_total
                if args.verbose_steps:
                    logger.info(
                        f"  UPSERT       : [DRY-RUN] {inserted} baris"
                    )
            elif rows_accum:
                try:
                    for i in range(0, len(rows_accum), args.batch):
                        chunk = rows_accum[i: i + args.batch]
                        cur.executemany(UPSERT_SQL, chunk)
                    db.commit()
                    inserted = len(rows_accum)
                    if args.verbose_steps:
                        logger.info(
                            f"  UPSERT       : {inserted} baris diinsert/update"
                        )
                except mysql.connector.Error as e:
                    db.rollback()
                    logger.info(f"  [ERROR] DB INSERT: {e}")
                    inserted = 0
            else:
                if args.verbose_steps:
                    logger.info(
                        "  UPSERT       : 0 baris (tidak ada data)"
                    )
        finally:
            release_pair_lock(cur, arbpl, werks)

        total_rows += inserted
        total_deleted += deleted
        summary.append(
            (
                arbpl,
                werks,
                deleted,
                fetched_total,
                inserted,
                time.perf_counter() - t0,
            )
        )

    cur.close()

    # ---- RINGKASAN per-hari
    logger.info("")
    hr("=")
    logger.info(f"RINGKASAN HARI {begda}")
    logger.info("ARBPL  | WERKS | Dihapus | Dari RFC | Insert/Update | Detik")
    subhr()
    for a, w, dl, rf, ins, sec in summary:
        logger.info(
            f"{a:<6} | {w:<5} | {dl:>7} | {rf:>8} | {ins:>13} | {sec:>5.2f}"
        )
    subhr()
    logger.info(
        f"TOTAL-HARI : insert/update={total_rows}, dihapus={total_deleted}."
    )
    logger.info(f"TABEL      : {DB_NAME}.{OUT_TABLE} (utf8mb4_unicode_ci).")
    day_end = datetime.datetime.now()
    duration = (day_end - day_start).total_seconds()
    hr("=")
    logger.info(
        "WAKTU-HARI : mulai %s detik ke %06.3f  | selesai %s detik ke %06.3f  | durasi %.2fs"
        % (
            day_start.strftime("%Y-%m-%d %H:%M:%S"),
            day_start.timestamp() % 60,
            day_end.strftime("%Y-%m-%d %H:%M:%S"),
            day_end.timestamp() % 60,
            duration,
        )
    )
    hr("=")
    logger.info("")  # spasi antar-hari

    return total_rows, total_deleted


# ---------- Main ----------
def main():
    ap = argparse.ArgumentParser(
        description=(
            "Tarik Z_FM_YPPR058DX per (ARBPL, WERKS) berbasis wc_person_data "
            "(T_ARBPL/T_PERNR + lock)."
        )
    )
    # Manual override pasangan
    ap.add_argument("--arbpl", action="append", help="ARBPL; dapat diulang")
    ap.add_argument("--werks", action="append", help="WERKS; dapat diulang")
    ap.add_argument(
        "--pairs",
        default="",
        help='Format "ARBPL:WERKS,ARB2:WER2" (override sumber DB)',
    )
    # Filter saat baca dari DB
    ap.add_argument(
        "--werks-filter",
        action="append",
        help="Batasi plant saat baca dari DB; dapat diulang",
    )
    ap.add_argument(
        "--like",
        action="append",
        help='Filter ARBPL ala SQL LIKE, contoh "WC%%"; dapat diulang',
    )
    # Tanggal
    ap.add_argument(
        "--begda",
        default="",
        help=(
            "Tanggal awal (DD.MM.YYYY / YYYY-MM-DD / YYYYMMDD). "
            "Default: (mode otomatis bila kosong)"
        ),
    )
    ap.add_argument(
        "--endda",
        default="",
        help=(
            "Tanggal akhir (DD.MM.YYYY / YYYY-MM-DD / YYYYMMDD). "
            "Default: (mode otomatis bila kosong)"
        ),
    )
    ap.add_argument(
        "--dates",
        default="",
        help=(
            "Daftar tanggal spesifik, pisah koma. Contoh: "
            "2025-11-11,2025-11-10,20251109 (urutan dipertahankan)"
        ),
    )
    ap.add_argument(
        "--yesterday",
        action="store_true",
        help="Tarik hanya data 1 hari kemarin (begda=endda=tgl kemarin).",
    )
    # Mode & performa
    ap.add_argument(
        "--show-pairs",
        action="store_true",
        help="Tampilkan daftar pasangan yang diproses",
    )
    ap.add_argument(
        "--verbose-steps",
        action="store_true",
        help="Tampilkan log detail per langkah",
    )
    ap.add_argument(
        "--no-delete",
        action="store_true",
        help="Jangan hapus data lama untuk pasangan",
    )
    ap.add_argument(
        "--dry-run",
        action="store_true",
        help="Simulasi: tampilkan rencana tanpa perubahan DB",
    )
    ap.add_argument(
        "--batch",
        type=int,
        default=500,
        help="Ukuran batch insert (default: 500)",
    )
    ap.add_argument(
        "--pernr-chunk",
        type=int,
        default=100,
        help="Jumlah PERNR per panggilan RFC (default: 100)",
    )
    ap.add_argument(
        "--sample-log",
        type=int,
        default=8,
        help="Berapa PERNR contoh yg ditampilkan di log (default: 8)",
    )
    ap.add_argument(
        "--lock-timeout",
        type=int,
        default=120,
        help="Detik menunggu lock per pair (default: 120)",
    )
    args = ap.parse_args()

    start_ts_all = datetime.datetime.now()
    title(f"Start yppr058_loader (PID={os.getpid()}) | Log: {LOG_FILE}")

    # Connect SAP
    sap_params = {**DEFAULT_SAP, "user": SAP_USERNAME, "passwd": SAP_PASSWORD}
    title(
        f"Connect SAP {sap_params['ashost']} client {sap_params['client']} as {SAP_USERNAME} ..."
    )
    try:
        rfc = Connection(**sap_params)
    except (CommunicationError, LogonError) as e:
        logger.info(f"Gagal konek SAP: {e}")
        logger.info(f"Log tersimpan di: {LOG_FILE}")
        sys.exit(2)
    logger.info("OK.\n")

    # DB prep
    db = ensure_db_and_table()

    # --- SINKRON PERNR YPPR vs WC PERSON (BARU, SEKALI SAJA PER RUN) ---
    try:
        # Kalau --dry-run atau --no-delete → hanya log, tidak hapus
        do_dry = args.dry_run or args.no_delete
        sync_yppr_with_wc_person(db, dry_run=do_dry)
    except Exception as e:
        logger.info(f"[WARN] Gagal menjalankan sync awal PERNR YPPR vs WC: {e}")

    # Susun pasangan
    if args.pairs:
        pairs: List[Tuple[str, str]] = []
        for chunk in args.pairs.split(","):
            parts = [p.strip() for p in chunk.split(":")]
            if len(parts) != 2:
                raise ValueError(f"Format pairs tidak valid: {chunk}")
            pairs.append((parts[0], parts[1]))
    elif args.arbpl or args.werks:
        pairs = []
        arbpls = args.arbpl or ["WC034"]
        werksl = args.werks or ["1000"]
        for a in arbpls:
            for w in werksl:
                pairs.append((a, w))
    else:
        pairs = fetch_pairs_from_db(db)
        if args.werks_filter:
            allow = set([w.strip() for w in args.werks_filter])
            pairs = [(a, w) for (a, w) in pairs if w in allow]
        if args.like:
            regs = [like_to_regex(p) for p in args.like]
            pairs = [(a, w) for (a, w) in pairs if any(r.match(a) for r in regs)]
    pairs = dedupe_pairs(pairs)

    if not pairs:
        logger.info("Tidak ada pasangan (ARBPL,WERKS) untuk diproses. Selesai.")
        logger.info(f"Log tersimpan di: {LOG_FILE}")
        return

    if args.show_pairs:
        subhr()
        logger.info("DAFTAR PASANGAN:")
        for a, w in pairs:
            logger.info(f"  - {a}:{w}")
        subhr()
        logger.info("")

    # MODE TANGGAL (prioritas):
    # 0) --yesterday → satu hari kemarin (begda=endda)
    # 1) --dates → eksekusi tepat tanggal-tanggal yang diberikan (urutan dipertahankan)
    # 2) --begda/--endda → di-split per-hari (urutan naik 09→10→11). Bila ingin urutan lain, pakai --dates.
    # 3) Tanpa tanggal → default: loop harian DESC dari kemarin → tanggal 1 pada bulan tsb.

    if args.yesterday:
        day = yesterday_dats()
        logger.info(f"MODE: only yesterday (satu hari) {day}")

        total_all_ins = 0
        total_all_del = 0

        ins, dele = process_one_day(rfc, db, args, pairs, day, day)
        total_all_ins += ins
        total_all_del += dele

        hr("=")
        logger.info(
            f"TOTAL HARI KEMARIN : insert/update={total_all_ins}, dihapus={total_all_del}."
        )

    elif args.dates.strip():
        dates = parse_dates_list(args.dates)
        logger.info(
            f"MODE: explicit dates (per-hari, urutan sesuai input): {', '.join(dates)}"
        )
        total_all_ins = 0
        total_all_del = 0
        for day in dates:
            ins, dele = process_one_day(rfc, db, args, pairs, day, day)
            total_all_ins += ins
            total_all_del += dele
        hr("=")
        logger.info(
            f"TOTAL SEMUA HARI (--dates) : insert/update={total_all_ins}, dihapus={total_all_del}."
        )

    elif args.begda or args.endda:
        begda = to_dats(args.begda) if args.begda else yesterday_dats()
        endda = to_dats(args.endda) if args.endda else yesterday_dats()
        if begda > endda:
            begda, endda = endda, begda
        logger.info(
            f"MODE: range {begda}..{endda} (akan di-split per-hari, urutan naik)"
        )
        d1 = datetime.date(
            int(begda[0:4]), int(begda[4:6]), int(begda[6:8])
        )
        d2 = datetime.date(
            int(endda[0:4]), int(endda[4:6]), int(endda[6:8])
        )
        total_all_ins = 0
        total_all_del = 0
        for d in daterange_inclusive(d1, d2):
            day = yyyymmdd(d)
            ins, dele = process_one_day(rfc, db, args, pairs, day, day)
            total_all_ins += ins
            total_all_del += dele
        hr("=")
        logger.info(
            f"TOTAL SEMUA HARI (range) : insert/update={total_all_ins}, dihapus={total_all_del}."
        )

    else:
        # DEFAULT:
        # - Jika hari ini <= 6  -> dari kemarin turun ke tgl 1 BULAN LALU
        # - Jika hari ini > 6   -> dari kemarin turun ke tgl 1 BULAN BERJALAN
        today = datetime.date.today()
        yest = today - datetime.timedelta(days=1)

        if today.day <= 6:
            # Contoh: hari ini 2025-12-06
            #   → kemarin 2025-12-05
            #   → 1 bulan lalu = 2025-11-01
            first_of_this_month = today.replace(day=1)
            last_of_prev_month = first_of_this_month - datetime.timedelta(days=1)
            first = last_of_prev_month.replace(day=1)

            logger.info(
                "MODE: default loop harian (<=6) "
                f"{yyyymmdd(yest)} -> {yyyymmdd(first)} "
                "(kemarin s/d tgl 1 bulan lalu)"
            )
        else:
            # Contoh: hari ini 2025-12-23
            #   → kemarin 2025-12-22
            #   → awal bulan ini 2025-12-01
            first = yest.replace(day=1)
            logger.info(
                "MODE: default loop harian (>6) "
                f"{yyyymmdd(yest)} -> {yyyymmdd(first)} "
                "(kemarin s/d tgl 1 bulan berjalan)"
            )

        total_all_ins = 0
        total_all_del = 0

        d = yest
        while d >= first:
            day = yyyymmdd(d)
            ins, dele = process_one_day(rfc, db, args, pairs, day, day)
            total_all_ins += ins
            total_all_del += dele
            d -= datetime.timedelta(days=1)

        hr("=")
        logger.info(
            f"TOTAL SEMUA HARI (default) : insert/update={total_all_ins}, dihapus={total_all_del}."
        )

if __name__ == "__main__":
    main()
