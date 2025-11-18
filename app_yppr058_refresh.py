#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# app_yppr058_refresh.py
# Flask API to refresh yppr058_data per pernr+date, dengan auto-resolve WERKS/ARBPL
# Host: 0.0.0.0  Port: 5010

import os, re, json, datetime, calendar
from decimal import Decimal
from typing import Any, Dict, List, Tuple, Optional

from dotenv import load_dotenv
from flask import Flask, request, jsonify
from flask_cors import CORS

from pyrfc import (
    Connection,
    CommunicationError,
    LogonError,
    ABAPApplicationError,
    ABAPRuntimeError,
)
import mysql.connector
import logging
from logging.handlers import RotatingFileHandler

# ---------------- Env & Logging ----------------
load_dotenv()

DEFAULT_LOG_DIR = os.environ.get(
    "YPPR058_LOG_DIR",
    r"C:\laragon\www\WC-person\storage\logs\python wc_person_mysql",
)
os.makedirs(DEFAULT_LOG_DIR, exist_ok=True)
LOG_FILE = os.path.join(DEFAULT_LOG_DIR, "api_refresh.log")

logger = logging.getLogger("yppr058_api")
logger.setLevel(logging.INFO)
fmt = logging.Formatter("%(asctime)s | %(levelname)s | %(message)s", datefmt="%Y-%m-%d %H:%M:%S")
ch = logging.StreamHandler()
ch.setLevel(logging.INFO)
ch.setFormatter(fmt)
fh = RotatingFileHandler(LOG_FILE, maxBytes=2_000_000, backupCount=5, encoding="utf-8")
fh.setLevel(logging.INFO)
fh.setFormatter(fmt)
if not logger.handlers:
    logger.addHandler(ch)
    logger.addHandler(fh)

# ---------------- SAP ----------------
DEFAULT_SAP = {
    "ashost": os.environ.get("SAP_ASHOST", "192.168.254.154"),
    "sysnr": os.environ.get("SAP_SYSNR", "01"),
    "client": os.environ.get("SAP_CLIENT", "300"),
    "lang": os.environ.get("SAP_LANG", "EN"),
}
SAP_USERNAME = os.environ.get("SAP_USER", "auto_email")
SAP_PASSWORD = os.environ.get("SAP_PASS", "11223344")
RFC_NAME = "Z_FM_YPPR058DX"

# ---------------- MySQL ----------------
DB_HOST = os.environ.get("DB_HOST", "127.0.0.1")
DB_PORT = int(os.environ.get("DB_PORT", "3306"))
DB_USER = os.environ.get("DB_USER", "root")
DB_PASS = os.environ.get("DB_PASS", "")
DB_NAME = os.environ.get("DB_NAME", "wc_person")
OUT_TABLE = os.environ.get("DB_TABLE_OUT", "yppr058_data")
WC_TABLE = os.environ.get("WC_TABLE", "wc_person_data")

# ---- Behavior toggle: hapus walau SAP 0 row? (default: False)
ALLOW_EMPTY_DELETE = str(os.environ.get("ALLOW_EMPTY_DELETE", "false")).lower() in ("1", "true", "yes")

# ---- SQL tambahan untuk cek WC Confirmasi & cari induk ----
CHECK_CONFIRM_SQL = f"""
SELECT COUNT(*)
FROM `{OUT_TABLE}`
WHERE pernr = %s
  AND begda = %s
  AND arbpl2 IS NOT NULL
  AND arbpl2 <> ''
"""

FIND_INDUK_SQL = f"""
SELECT DISTINCT pernr
FROM `{WC_TABLE}`
WHERE arbpl = %s
  AND werks = %s
  AND begda <= %s
  AND endda >= %s
  AND (
      role = 'INDUK'
      OR role = 'Induk'
      OR role = 'induk'
  )
"""

# ---------------- Helpers ----------------
def to_dats(s: str) -> str:
    """Terima DD.MM.YYYY / YYYY-MM-DD / YYYYMMDD -> return YYYYMMDD."""
    s = (s or "").strip()
    m = re.match(r"^(\d{2})\.(\d{2})\.(\d{4})$", s)
    if m:
        dd, mm, yy = m.groups()
        return f"{yy}{mm}{dd}"
    m = re.match(r"^(\d{4})-(\d{2})-(\d{2})$", s)
    if m:
        yy, mm, dd = m.groups()
        return f"{yy}{mm}{dd}"
    m = re.match(r"^\d{8}$", s)
    if m:
        return s
    raise ValueError(f"Invalid date format: {s}")

def month_range(dats: str) -> Tuple[str, str]:
    y = int(dats[0:4])
    m = int(dats[4:6])
    start = f"{y:04d}{m:02d}01"
    last = calendar.monthrange(y, m)[1]
    end = f"{y:04d}{m:02d}{last:02d}"
    return start, end

def get_mysql():
    return mysql.connector.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME,
        autocommit=False,
    )

def acquire_pair_lock(cur, arbpl: str, werks: str, timeout: int = 120) -> bool:
    cur.execute("SELECT GET_LOCK(CONCAT('yppr058:', %s, ':', %s), %s)", (arbpl, werks, timeout))
    row = cur.fetchone()
    return bool(row and row[0] == 1)

def release_pair_lock(cur, arbpl: str, werks: str):
    try:
        cur.execute("SELECT RELEASE_LOCK(CONCAT('yppr058:', %s, ':', %s))", (arbpl, werks))
        cur.fetchone()
    except Exception:
        pass

def norm_val(x):
    if x is None:
        return None
    if isinstance(x, Decimal):
        return x
    try:
        return Decimal(str(x)) if isinstance(x, (int, float)) else x
    except Exception:
        return x

# <<< BARU: ambil deskripsi WC dari wc_person_data, sama seperti di loader >>>
def get_wc_desc(conn, arbpl: str, werks: str) -> Optional[str]:
    """
    Ambil deskripsi WC dari tabel wc_person_data (kolom `desc`)
    berdasarkan pasangan ARBPL/WERKS.
    """
    sql = (
        f"SELECT `desc` FROM `{WC_TABLE}` "
        "WHERE arbpl=%s AND werks=%s AND `desc` IS NOT NULL AND `desc`<>'' "
        "LIMIT 1"
    )
    cur = conn.cursor()
    try:
        cur.execute(sql, (arbpl, werks))
        row = cur.fetchone()
        return row[0] if row else None
    except mysql.connector.Error as e:
        logger.warning(f"[WARN] gagal ambil desc WC {arbpl}/{werks}: {e}")
        return None
    finally:
        cur.close()

# <<< DIUBAH: tambahkan desc_value, dan set field `desc` >>>
def normalize_tdata(row: Dict[str, Any], arbpl: str, werks: str, desc_value: Optional[str]) -> Dict[str, Any]:
    S = lambda v: "" if v is None else str(v)
    I = lambda v: None if (S(v) == "") else int(S(v))
    D = lambda v: None if v in (None, "") else norm_val(v)
    return {
        "pernr": S(row.get("PERNR")).zfill(8) if S(row.get("PERNR")).isdigit() else S(row.get("PERNR")),
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
    }

# <<< DIUBAH: tambah kolom `desc` di INSERT & UPDATE >>>
UPSERT_SQL = f"""
INSERT INTO `{OUT_TABLE}`
(`pernr`,`begda`,`total_jam`,`mint2`,`mintu`,`mintu2`,`mintu3`,
 `cname`,`gji`,`gji2`,`varnt`,`varnt1`,`arbpl`,`arbpl2`,`shift`,`werks`,`desc`,`source_rfc`)
VALUES (%(pernr)s,%(begda)s,%(total_jam)s,%(mint2)s,%(mintu)s,%(mintu2)s,%(mintu3)s,
        %(cname)s,%(gji)s,%(gji2)s,%(varnt)s,%(varnt1)s,%(arbpl)s,%(arbpl2)s,%(shift)s,%(werks)s,%(desc)s,'{RFC_NAME}')
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
  `inserted_at`=CURRENT_TIMESTAMP
"""

# <<< BARU: delete hanya untuk pernr yang ikut di-refresh >>>
def delete_old_rows(cur, arbpl: str, werks: str, begda: str, endda: str, pernrs: List[str]) -> int:
    """
    Hapus data lama hanya untuk pernr-pernr tertentu di WC/Plant + range tanggal.
    """
    if not pernrs:
        return 0

    placeholders = ",".join(["%s"] * len(pernrs))
    sql = f"""
        DELETE FROM `{OUT_TABLE}`
        WHERE `arbpl`=%s
          AND `werks`=%s
          AND `begda` BETWEEN %s AND %s
          AND `pernr` IN ({placeholders})
    """
    params = [arbpl, werks, begda, endda] + list(pernrs)
    cur.execute(sql, params)
    return cur.rowcount

def call_rfc(conn: Connection, arbpl: str, werks: str, begda: str, endda: str, pernrs: List[str]):
    return conn.call(
        RFC_NAME,
        P_BEGDA=begda,
        P_ENDDA=endda,
        P_WERKS=werks,
        P_ARBPL=arbpl,
        T_ARBPL=[{"ARBPL": arbpl}],
        T_PERNR=[{"PERNR": p} for p in pernrs],
    )

# -------- Pair resolver --------
def month_range_for_sql(dats: str) -> Tuple[str, str]:
    return month_range(dats)

def resolve_pairs(cur, pernr: str, dats: str) -> Tuple[str, List[Tuple[str, str]]]:
    """Kembalikan (strategy, [(arbpl, werks), ...]) untuk pernr di tanggal dats."""
    # 1) wc_person_data tepat di tanggal itu
    sql_wc = f"""
        SELECT DISTINCT arbpl, werks
        FROM `{WC_TABLE}`
        WHERE pernr=%s AND begda<=%s AND endda>=%s
          AND arbpl<>'' AND werks<>''"""
    cur.execute(sql_wc, (pernr, dats, dats))
    rows = [(a or "", w or "") for (a, w) in cur.fetchall()]
    if rows:
        return "wc_person", rows

    # 2) fallback: jejak paling sering di bulan yang sama dari yppr058_data
    m_start, m_end = month_range_for_sql(dats)
    sql_y = f"""
        SELECT arbpl, werks, COUNT(*) c, MAX(begda) last_d
        FROM `{OUT_TABLE}`
        WHERE pernr=%s AND begda BETWEEN %s AND %s
          AND arbpl<>'' AND werks<>'' 
        GROUP BY arbpl, werks
        ORDER BY c DESC, last_d DESC
        LIMIT 3"""
    cur.execute(sql_y, (pernr, m_start, m_end))
    rows = [(a or "", w or "") for (a, w, *_rest) in cur.fetchall()]
    if rows:
        return "yppr058_recent", rows

    return "none", []

def pernr_has_confirm(cur, pernr: str, dats: str) -> bool:
    """
    Cek apakah di yppr058_data untuk pernr + tanggal itu sudah ada WC Konfirmasi (arbpl2).
    """
    cur.execute(CHECK_CONFIRM_SQL, (pernr, dats))
    row = cur.fetchone()
    return bool(row and row[0] > 0)

def find_induk_for_pair(cur, arbpl: str, werks: str, dats: str) -> List[str]:
    """
    Cari semua PERNR dengan role INDUK untuk kombinasi WC/Plant & tanggal tsb di wc_person_data.
    Hasil dalam bentuk list PERNR (string 8 digit).
    """
    cur.execute(FIND_INDUK_SQL, (arbpl, werks, dats, dats))
    pernrs: List[str] = []
    for (p,) in cur.fetchall():
        if not p:
            continue
        s = str(p).strip()
        if not s:
            continue
        pernrs.append(s.zfill(8) if s.isdigit() else s)
    return pernrs

# ---------------- Flask App ----------------
app = Flask(__name__)
CORS(app, resources={r"/api/*": {"origins": "*"}})

@app.get("/health")
def health():
    return {"ok": True, "service": "yppr058_refresh", "time": datetime.datetime.now().isoformat()}

@app.post("/api/yppr058/refresh")
def api_refresh():
    """
    Body:
    {"items": [{"pernr":"10005817","werks":"","arbpl":"","begda":"20251108","endda":"20251108"}]}
    """
    try:
        payload = request.get_json(force=True) or {}
    except Exception:
        return jsonify({"ok": False, "error": "Invalid JSON"}), 400

    items = payload.get("items") or []
    if not isinstance(items, list) or not items:
        return jsonify({"ok": False, "error": "items[] required"}), 400

    # SAP connect sekali per request
    sap_params = {**DEFAULT_SAP, "user": SAP_USERNAME, "passwd": SAP_PASSWORD}
    try:
        rfc = Connection(**sap_params)
    except (CommunicationError, LogonError) as e:
        logger.error(f"SAP connect failed: {e}")
        return jsonify({"ok": False, "error": f"SAP connect failed: {e}"}), 500

    db = get_mysql()
    cur = db.cursor()

    results = []

    for it in items:
        pernr = str(it.get("pernr") or "").zfill(8)
        req_werks = (it.get("werks") or "").strip()
        req_arbpl = (it.get("arbpl") or "").strip()

        try:
            begda = to_dats(str(it.get("begda") or ""))
            endda = to_dats(str(it.get("endda") or begda))
        except Exception as e:
            results.append({"ok": False, "pernr": pernr, "error": str(e)})
            continue

        if not pernr or not begda or not endda:
            results.append({"ok": False, "pernr": pernr, "error": "Missing pernr/begda"})
            continue

        # Cek apakah NIK ini di tanggal tsb sudah punya WC Konfirmasi atau belum
        try:
            needs_induk = not pernr_has_confirm(cur, pernr, begda)
        except Exception as e:
            logger.error(f"[DB] gagal cek WC Confirmasi untuk {pernr}@{begda}: {e}")
            needs_induk = False

        # Tentukan pair WC/Plant
        if req_arbpl and req_werks:
            strategy, pairs = "request", [(req_arbpl, req_werks)]
        else:
            strategy, pairs = resolve_pairs(cur, pernr, begda)

        logger.info(
            f"[REQ] pernr={pernr} begda..endda={begda}..{endda} "
            f"strategy={strategy} pairs={pairs} needs_induk={needs_induk}"
        )

        # Jangan lanjut kalau tidak ada pasangan sama sekali
        if not pairs:
            results.append(
                {
                    "ok": False,
                    "pernr": pernr,
                    "strategy": strategy,
                    "pairs_used": [],
                    "deleted_total": 0,
                    "inserted_total": 0,
                    "error": "No WC/Plant pair found; skipped",
                }
            )
            continue

        item_pairs_report: List[Dict[str, Any]] = []
        total_deleted = 0
        total_inserted = 0

        for (arbpl, werks) in pairs:
            if not acquire_pair_lock(cur, arbpl, werks, timeout=120):
                item_pairs_report.append(
                    {"arbpl": arbpl, "werks": werks, "ok": False, "error": "Lock timeout"}
                )
                continue

            deleted = 0
            inserted = 0
            sap_rows = 0
            skipped_empty = False

            try:
                # Susun daftar PERNR untuk dipassing ke RFC
                pernrs_for_rfc: List[str] = [pernr]

                if needs_induk:
                    try:
                        induks = find_induk_for_pair(cur, arbpl, werks, begda)
                    except Exception as e:
                        logger.error(
                            f"[DB] gagal cari induk untuk {pernr}@{arbpl}/{werks} {begda}: {e}"
                        )
                        induks = []

                    if induks:
                        seen = {pernr}
                        extra: List[str] = []
                        for ip in induks:
                            if ip not in seen:
                                seen.add(ip)
                                extra.append(ip)
                        if extra:
                            pernrs_for_rfc.extend(extra)
                            logger.info(
                                f"[ROLE] pernr={pernr} tanpa WC Confirmasi, "
                                f"tambah induk={extra} untuk {arbpl}/{werks}"
                            )

                # <<< BARU: ambil DESC WC untuk pasangan ini >>>
                desc_for_pair = get_wc_desc(db, arbpl, werks)

                # Penting #2: CALL RFC DULU
                try:
                    logger.info(
                        f"[SAP CALL] {arbpl}/{werks} {begda}..{endda} "
                        f"T_PERNR={pernrs_for_rfc}"
                    )
                    resp = call_rfc(rfc, arbpl, werks, begda, endda, pernrs_for_rfc)
                except (ABAPApplicationError, ABAPRuntimeError, CommunicationError) as e:
                    logger.error(f"[SAP] {e}")
                    item_pairs_report.append(
                        {"arbpl": arbpl, "werks": werks, "ok": False, "error": f"SAP: {e}"}
                    )
                    continue

                for r in (resp.get("RETURN") or []):
                    typ = (r.get("TYPE") or "").upper()
                    msg = r.get("MESSAGE") or ""
                    (logger.warning if typ in ("E", "A") else logger.info)(
                        f"[SAP-{typ}] {msg}"
                    )

                t_data = resp.get("T_DATA") or []
                sap_rows = len(t_data)
                rows = [normalize_tdata(r, arbpl, werks, desc_for_pair) for r in t_data]
                logger.info(
                    f"[SAP] rows={sap_rows} for {pernr}@{arbpl}/{werks} {begda}"
                )

                # Penting #3: Jika SAP 0 row -> default TIDAK menghapus
                if sap_rows == 0 and not ALLOW_EMPTY_DELETE:
                    skipped_empty = True
                    item_pairs_report.append(
                        {
                            "arbpl": arbpl,
                            "werks": werks,
                            "ok": True,
                            "sap_rows": 0,
                            "deleted": 0,
                            "inserted": 0,
                            "no_change": True,
                            "skipped_empty": True,
                        }
                    )
                    continue

                # Mulai transaksi: hapus lama hanya utk pernr-pernr yang ikut di-refresh
                try:
                    deleted = delete_old_rows(cur, arbpl, werks, begda, endda, pernrs_for_rfc)
                    db.commit()
                    logger.info(
                        f"[DB] deleted={deleted} for {pernrs_for_rfc} @ {arbpl}/{werks} {begda}..{endda}"
                    )
                except mysql.connector.Error as e:
                    db.rollback()
                    logger.error(f"[DB DELETE] {e}")
                    item_pairs_report.append(
                        {
                            "arbpl": arbpl,
                            "werks": werks,
                            "ok": False,
                            "error": f"DB delete: {e}",
                        }
                    )
                    continue

                try:
                    if rows:
                        BATCH = 500
                        for i in range(0, len(rows), BATCH):
                            cur.executemany(UPSERT_SQL, rows[i : i + BATCH])
                        db.commit()
                        inserted = len(rows)
                        logger.info(
                            f"[DB] inserted={inserted} for {pernr}@{arbpl}/{werks} {begda}"
                        )
                except mysql.connector.Error as e:
                    db.rollback()
                    logger.error(f"[DB INSERT] {e}")
                    item_pairs_report.append(
                        {
                            "arbpl": arbpl,
                            "werks": werks,
                            "ok": False,
                            "error": f"DB insert: {e}",
                            "deleted": deleted,
                            "sap_rows": sap_rows,
                        }
                    )
                    continue

                item_pairs_report.append(
                    {
                        "arbpl": arbpl,
                        "werks": werks,
                        "ok": True,
                        "deleted": deleted,
                        "inserted": inserted,
                        "sap_rows": sap_rows,
                        "skipped_empty": skipped_empty,
                    }
                )
                total_deleted += deleted
                total_inserted += inserted

            finally:
                release_pair_lock(cur, arbpl, werks)

        results.append(
            {
                "ok": any(p.get("ok") for p in item_pairs_report),
                "pernr": pernr,
                "strategy": strategy,
                "pairs_used": item_pairs_report,
                "deleted_total": total_deleted,
                "inserted_total": total_inserted,
            }
        )

    cur.close()
    db.close()
    try:
        rfc.close()
    except Exception:
        pass

    return jsonify({"ok": True, "results": results})

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5010, debug=False)
