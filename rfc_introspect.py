#!/usr/bin/env python3
# rfc_introspect.py
"""
Introspeksi lengkap metadata RFC SAP (pyrfc):
- Tampilkan semua parameter (IMPORT/EXPORT/CHANGING/TABLES/EXCEPTIONS)
- Untuk TABLES/STRUCTURE: tampilkan semua field beserta tipe & panjang
- Opsi ekspor metadata ke JSON
- Opsi generate SQL skeleton untuk tabel hasil RFC

cara pakai cepat

Lihat semua metadata di layar:

python rfc_introspect.py --rfc Z_FM_GET_WC_DESC --dump


Simpan metadata ke JSON (buat referensi / mapping ke DB):

python rfc_introspect.py --rfc CR_PERSONS_OF_WORKCENTER --save-json meta_cr_wc.json


Generate SQL skeleton untuk setiap parameter tabel (bikin cepat skema awal di DB non-SAP):

python rfc_introspect.py --rfc CR_PERSONS_OF_WORKCENTER --emit-sql schema_wc.sql
"""

#!/usr/bin/env python3
# rfc_introspect_alt.py — introspeksi RFC via RFC_GET_* (robust + fallback)

import os, sys, re, json, signal, argparse
from typing import Any, Dict, List, Optional
from dotenv import load_dotenv
from pyrfc import Connection, CommunicationError, LogonError, ABAPApplicationError, ABAPRuntimeError

# Workaround pyrfc lama yang refer 'long' (Python2)
import builtins as _bt
if not hasattr(_bt, "long"):
    _bt.long = int

try:
    signal.signal(signal.SIGINT, signal.SIG_IGN)
except Exception:
    pass

load_dotenv()

DEFAULT_SAP = {
    "ashost": os.environ.get("SAP_ASHOST", "192.168.254.154"),
    "sysnr":  os.environ.get("SAP_SYSNR",  "01"),
    "client": os.environ.get("SAP_CLIENT", "300"),
    "lang":   os.environ.get("SAP_LANG",   "EN"),
}
SAP_USERNAME = os.environ.get("SAP_USER", "auto_email")
SAP_PASSWORD = os.environ.get("SAP_PASS", "11223344")

LINE = "-"*80
EQ   = "="*80

def box(title: str) -> str:
    return f"{EQ}\n{title}\n{EQ}"

def first_key(d: Dict[str, Any], *candidates: str, default=""):
    for k in candidates:
        if k in d and d[k] is not None:
            return d[k]
    return default

def yn_to_bool(v) -> bool:
    return str(v).strip().upper() in ("X","Y","TRUE","1")

def dtype(v) -> str:
    s = str(v or "").strip()
    s = s.replace("RFCTYPE.", "").replace("RFCTYPE_", "")
    s = re.sub(r"[<>]", "", s)
    s = s.split(":")[0]
    m = re.search(r"([A-Za-z0-9_]+)$", s)
    return (m.group(1) if m else s).upper()

def try_call(conn: Connection, fm: str, **kwargs):
    try:
        return conn.call(fm, **kwargs)
    except Exception as e:
        # jika hanya salah nama parameter, lanjut coba varian lain
        msg = str(e).lower()
        if "field" in msg and "not found" in msg:
            return None
        raise

def get_rfc_interface(conn: Connection, funcname: str) -> Dict[str, Any]:
    return conn.call("RFC_GET_FUNCTION_INTERFACE", FUNCNAME=funcname)

def get_struct_fields(conn: Connection, struct_name: str) -> List[Dict[str, Any]]:
    """Coba ambil field struktur/rowtype:
       1) RFC_GET_STRUCTURE_DEFINITION (dengan berbagai nama param)
       2) fallback: DDIF_FIELDINFO_GET (TABNAME=)
    """
    struct_name = (struct_name or "").strip().upper()
    if not struct_name:
        return []

    # 1) RFC_GET_STRUCTURE_DEFINITION dengan beberapa nama parameter
    for pname in ("STRUCTNAME", "TABNAME", "TABLENAME", "STRUCNAME", "DDICNAME"):
        resp = try_call(conn, "RFC_GET_STRUCTURE_DEFINITION", **{pname: struct_name})  # type: ignore
        if resp:
            rows = resp.get("FIELDS") or resp.get("fields") or []
            out = []
            for r in rows:
                name = first_key(r, "FIELDNAME","NAME","FLDNAME","ROLLNAME","COMPONENT", default="")
                abap = first_key(r, "DATATYPE","TYPE","EXID","INTTYPE", default="")
                leng = first_key(r, "LENGTH","LENG","INTLEN","N_UC_LENG","N_LENG", default=None)
                decs = first_key(r, "DECIMALS","DEC","SCALE", default=None)
                try: leng = int(leng) if leng not in (None,"") else None
                except: pass
                try: decs = int(decs) if decs not in (None,"") else None
                except: pass
                out.append({"name": name, "abap_type": dtype(abap), "length": leng, "decimals": decs})
            if out:
                return out
            # kalau kosong, lanjut fallback
            break

    # 2) Fallback DDIF_FIELDINFO_GET
    try:
        resp = conn.call("DDIF_FIELDINFO_GET", TABNAME=struct_name)
        rows = resp.get("DFIES_TAB") or resp.get("FIELDINFO") or []
        out = []
        for r in rows:
            name = first_key(r, "FIELDNAME","ROLLNAME","COMPONENT","NAME", default="")
            abap = first_key(r, "DATATYPE","INTTYPE","DATATYPE_DDIC", default="")
            leng = first_key(r, "LENG","LENGTH","INTLEN", default=None)
            decs = first_key(r, "DECIMALS","DEC", default=None)
            try: leng = int(leng) if leng not in (None,"") else None
            except: pass
            try: decs = int(decs) if decs not in (None,"") else None
            except: pass
            out.append({"name": name, "abap_type": dtype(abap), "length": leng, "decimals": decs})
        return out
    except (ABAPApplicationError, ABAPRuntimeError, CommunicationError):
        return []

def to_sql_type(abap_type: str, length: Optional[int], decimals: Optional[int]) -> str:
    T = abap_type.upper()
    if T in ("CHAR","CUKY","UNIT","LANG","CLNT"):      return f"VARCHAR({max(1, int(length or 1))})"
    if T in ("STRING","RAWSTRING","LCHR","LRAW"):      return "TEXT"
    if T in ("RAW","BYTE","X","XSTRING"):              return "BYTEA"
    if T in ("DATE","DATS"):                           return "CHAR(8)"
    if T in ("TIME","TIMS"):                           return "CHAR(6)"
    if T in ("NUM","NUMC","BCD","DEC","CURR","QUAN"):  return f"DECIMAL({int(length or 18)},{int(decimals or 0)})"
    if T in ("DECF16",):                               return "DECIMAL(16,3)"
    if T in ("DECF34",):                               return "DECIMAL(34,7)"
    if T in ("FLOAT","FLTP"):                          return "DOUBLE PRECISION"
    if T in ("INT","INT4","I"):                        return "INTEGER"
    if T in ("INT1","INT2"):                           return "SMALLINT"
    if T in ("UTCLONG",):                              return "VARCHAR(27)"
    return "TEXT"

def emit_sql(table_name: str, fields: List[Dict[str, Any]]) -> str:
    cols = []
    for f in fields:
        cols.append(f'  "{f["name"]}" {to_sql_type(f.get("abap_type",""), f.get("length"), f.get("decimals"))}')
    if not cols:
        cols = ['  "DUMMY" TEXT']
    return f'CREATE TABLE "{table_name}" (\n' + ",\n".join(cols) + "\n);\n"

def main():
    ap = argparse.ArgumentParser(description="Introspeksi RFC via RFC_GET_* (kompatibel & fallback DDIC)")
    ap.add_argument("--rfc", default="CR_PERSONS_OF_WORKCENTER", help="Nama RFC")
    ap.add_argument("--dump", action="store_true", help="Tampilkan metadata di console")
    ap.add_argument("--save-json", default="", help="Simpan metadata JSON")
    ap.add_argument("--emit-sql", default="", help="Simpan SQL skeleton untuk semua TABLES")
    args = ap.parse_args()

    params = {**DEFAULT_SAP, "user": SAP_USERNAME, "passwd": SAP_PASSWORD}
    print(f"Connect SAP {params['ashost']} client {params['client']} as {SAP_USERNAME} ...")
    try:
        conn = Connection(**params)
    except (CommunicationError, LogonError) as e:
        print(f"Gagal konek SAP: {e}")
        sys.exit(2)
    print("OK.\n")

    meta = {"rfc_name": args.rfc, "imports": [], "exports": [], "changings": [], "tables": [], "exceptions": []}

    # 1) Ambil interface
    try:
        iface = get_rfc_interface(conn, args.rfc)
    except (ABAPApplicationError, ABAPRuntimeError, CommunicationError) as e:
        print(f"Gagal mengambil interface dengan RFC_GET_FUNCTION_INTERFACE: {e}")
        print("Cek authority S_RFC untuk RFC_GET_FUNCTION_INTERFACE/DDIF_FIELDINFO_GET.")
        sys.exit(3)

    params_tab = iface.get("PARAMS") or iface.get("params") or []
    exc_tab    = iface.get("EXCEPT") or iface.get("exceptions") or []

    # 2) Kelompokkan parameter
    def add_param(row: Dict[str, Any]):
        name = first_key(row, "PARAMETER","PARAMNAME","NAME", default="")
        dirc = first_key(row, "DIRECTION","PARAMCLASS","KIND", default="").upper()
        typ  = first_key(row, "TYPE","DATATYPE","EXID", default="")
        opt  = first_key(row, "OPTIONAL","OPTION", default="")
        dflt = first_key(row, "DEFAULT","DEF","DFLT", default=None)
        leng = first_key(row, "LENGTH","LENG","INTLEN","N_UC_LENG","N_LENG", default=None)
        decs = first_key(row, "DECIMALS","DEC","SCALE", default=None)
        rowtype = first_key(row, "ROWTYPE","TABNAME","STRUCTTYPE","STRUCT_DEF","LINE_TYPE","REFSTRUCTURE", default="")

        try: leng = int(leng) if str(leng).strip() != "" else None
        except: pass
        try: decs = int(decs) if str(decs).strip() != "" else None
        except: pass

        item = {
            "name": name,
            "direction": {"I":"IMPORT","E":"EXPORT","C":"CHANGING","T":"TABLES"}.get(dirc, dirc),
            "abap_type": dtype(typ),
            "optional": yn_to_bool(opt),
            "default": dflt,
            "length": leng,
            "decimals": decs,
            "rowtype": rowtype,
            "fields": []
        }
        if item["direction"] == "IMPORT":   meta["imports"].append(item)
        elif item["direction"] == "EXPORT": meta["exports"].append(item)
        elif item["direction"] == "CHANGING": meta["changings"].append(item)
        elif item["direction"] == "TABLES": meta["tables"].append(item)
        else:
            meta.setdefault("others", []).append(item)

    for r in params_tab:
        add_param(r)

    # 3) Lengkapi field utk STRUCTURE/TABLES
    for grp in (meta["imports"], meta["exports"], meta["changings"], meta["tables"]):
        for it in grp:
            if it["direction"] == "TABLES" or it["abap_type"] in ("STRUCTURE","TABLE"):
                fields = get_struct_fields(conn, it.get("rowtype") or it.get("name"))
                it["fields"] = fields

    # 4) Exceptions (opsional)
    meta["exceptions"] = [first_key(r, "EXCEPTION","NAME","EXCNAME", default="") for r in exc_tab]

    # 5) Dump ke console
    if args.dump:
        def print_params(title: str, arr: List[Dict[str, Any]]):
            print(box(title))
            if not arr:
                print("(tidak ada)")
                return
            print("NAME | DIR | TYPE | LEN | DEC | OPTIONAL | DEFAULT | ROWTYPE")
            print(LINE)
            for it in arr:
                print(f'{it["name"]} | {it["direction"]} | {it["abap_type"]} | {it["length"]} | {it["decimals"]} | {it["optional"]} | {it["default"]} | {it["rowtype"]}')
            print()
        print_params("IMPORT PARAMETERS", meta["imports"])
        print_params("EXPORT PARAMETERS", meta["exports"])
        print_params("CHANGING PARAMETERS", meta["changings"])
        print_params("TABLE PARAMETERS", meta["tables"])

        # fields setiap TABLE/STRUCTURE
        for it in meta["tables"]:
            print(box(f'TABLE "{it["name"]}" (rowtype {it["rowtype"]}) FIELDS'))
            if not it["fields"]:
                print("(fields tidak tersedia)")
            else:
                print("FIELD | ABAP_TYPE | LEN | DEC")
                print(LINE)
                for f in it["fields"]:
                    print(f'{f["name"]} | {f["abap_type"]} | {f["length"]} | {f["decimals"]}')
            print()

        # struktur di import/export/changing (kalau ada)
        for grp_name, arr in (("IMPORT STRUCTS", meta["imports"]), ("EXPORT STRUCTS", meta["exports"]), ("CHANGING STRUCTS", meta["changings"])):
            for it in arr:
                if it["abap_type"] == "STRUCTURE":
                    print(box(f'STRUCTURE "{it["name"]}" (def {it["rowtype"]}) FIELDS'))
                    if not it["fields"]:
                        print("(fields tidak tersedia)")
                    else:
                        print("FIELD | ABAP_TYPE | LEN | DEC")
                        print(LINE)
                        for f in it["fields"]:
                            print(f'{f["name"]} | {f["abap_type"]} | {f["length"]} | {f["decimals"]}')
                    print()

        if meta["exceptions"]:
            print(box("EXCEPTIONS"))
            for ex in meta["exceptions"]:
                print(ex)
            print()

    # 6) Simpan JSON
    if args.save_json:
        with open(args.save_json, "w", encoding="utf-8") as f:
            json.dump(meta, f, indent=2, ensure_ascii=False)
        print(f"Metadata JSON tersimpan: {args.save_json}")

    # 7) Generate SQL untuk semua TABLES
    if args.emit_sql:
        sql_chunks = []
        for it in meta["tables"]:
            sql_chunks.append(emit_sql(it["name"], it["fields"]))
        with open(args.emit_sql, "w", encoding="utf-8") as f:
            f.write("\n".join(sql_chunks))
        print(f"SQL skeleton tersimpan: {args.emit_sql}")

    print("\nSelesai.")

if __name__ == "__main__":
    main()
