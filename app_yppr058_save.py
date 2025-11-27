#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
app_yppr058_save.py
Flask API untuk SAVE data ke SAP via RFC Z_RFC_SAVE_YPPR058
Host: 0.0.0.0, Port: 5011
"""

import os
import re
import datetime
import logging
from typing import Any, Dict, List

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

load_dotenv()

# ---------------------------------------------------------------------
# Logging ke console
# ---------------------------------------------------------------------
logging.basicConfig(
    level=logging.INFO,
    format='[%(asctime)s] %(levelname)s: %(message)s',
)
logger = logging.getLogger("yppr058_save")

# ---------------------------------------------------------------------
# Konfigurasi SAP
# ---------------------------------------------------------------------
DEFAULT_SAP = {
    "ashost": os.environ.get("SAP_ASHOST", "192.168.254.154"),
    "sysnr":  os.environ.get("SAP_SYSNR", "01"),
    "client": os.environ.get("SAP_CLIENT", "300"),
    "lang":   os.environ.get("SAP_LANG", "EN"),
}

SAP_SAVE_RFC = "Z_RFC_SAVE_YPPR058"
ALLOWED_SAP_USERS = {"abaper01", "auto_email", "kmi-u030"}

# ---------------------------------------------------------------------
# Helper tanggal -> DATS (yyyymmdd)
# ---------------------------------------------------------------------
def to_dats(s: str) -> str:
    """
    Terima:
      - '20251124'
      - '2025-11-24'
      - '24.11.2025'
    Return:
      - '20251124' (DATS, yyyymmdd)
    """
    s = (s or "").strip()

    # dd.mm.yyyy
    m = re.match(r"^(\d{2})\.(\d{2})\.(\d{4})$", s)
    if m:
        return f"{m.group(3)}{m.group(2)}{m.group(1)}"

    # yyyy-mm-dd
    m = re.match(r"^(\d{4})-(\d{2})-(\d{2})$", s)
    if m:
        return f"{m.group(1)}{m.group(2)}{m.group(3)}"

    # yyyymmdd
    m = re.match(r"^\d{8}$", s)
    if m:
        return s

    raise ValueError(f"Invalid date format: {s!r}")


# ---------------------------------------------------------------------
# Flask app
# ---------------------------------------------------------------------
app = Flask(__name__)
CORS(app, resources={r"/api/*": {"origins": "*"}})


@app.get("/health")
def health():
    return {
        "ok": True,
        "service": "yppr058_save",
        "time": datetime.datetime.now().isoformat(),
    }


@app.post("/api/yppr058/save")
def api_save_yppr058():
    """
    Body JSON:
    {
      "sap_user": "USERID",
      "sap_pass": "PASSWORD",
      "items": [
        {
          "pernr": "10000030",
          "cname": "Rachman Tjahjono",
          "arbpl": "WC110",
          "start_date": "20251101",
          "end_date":   "20251125",
          "mint2": 7357,
          "mintu": 7357,
          "mintu2": 441420,
          "mintu3": 441420
        },
        ...
      ]
    }
    """
    # ------------------ parsing payload ------------------
    try:
        payload = request.get_json(force=True) or {}
    except Exception:
        logger.error("Invalid JSON from %s", request.remote_addr)
        return jsonify({"ok": False, "error": "Invalid JSON"}), 400

    sap_user = (payload.get("sap_user") or "").strip()
    sap_pass = (payload.get("sap_pass") or "").strip()
    items: List[Dict[str, Any]] = payload.get("items") or []

    if not sap_user or not sap_pass:
        return jsonify({"ok": False, "error": "sap_user & sap_pass required"}), 400
    if not isinstance(items, list) or not items:
        return jsonify({"ok": False, "error": "items[] required"}), 400

    # === Otorisasi: hanya beberapa SAP user yang boleh SAVE ===
    if sap_user.lower() not in ALLOWED_SAP_USERS:
        logger.warning(
            "SAP SAVE unauthorized user=%s ip=%s",
            sap_user,
            request.remote_addr,
        )
        return (
            jsonify(
                {
                    "ok": False,
                    "summary": {"total": 0, "success": 0, "failed": 0},
                    "results": [],
                    "error": f"SAP user {sap_user} tidak memiliki otorisasi untuk SAVE YPPR058.",
                }
            ),
            403,
        )

    sap_params = {**DEFAULT_SAP, "user": sap_user, "passwd": sap_pass}
    # ------------------ connect SAP ------------------
    try:
        conn = Connection(**sap_params)
        logger.info(
            "SAP connect ok host=%s client=%s user=%s",
            sap_params["ashost"],
            sap_params["client"],
            sap_user,
        )
    except (CommunicationError, LogonError) as e:
        logger.error("SAP logon failed user=%s error=%s", sap_user, e)
        return jsonify({"ok": False, "error": f"SAP logon failed: {e}"}), 401

    results: List[Dict[str, Any]] = []

    # ------------------ proses tiap item ------------------
    try:
        for it in items:
            pernr = str(it.get("pernr") or "").strip()
            cname = str(it.get("cname") or "").strip()
            arbpl = str(it.get("arbpl") or "").strip()
            start_raw = str(it.get("start_date") or "").strip()
            end_raw   = str(it.get("end_date") or "").strip()

            # --- konversi tanggal ---
            try:
                start_dats = to_dats(start_raw)
                end_dats   = to_dats(end_raw)
            except ValueError as e:
                logger.error(
                    "Invalid date pernr=%s arbpl=%s start=%r end=%r error=%s",
                    pernr,
                    arbpl,
                    start_raw,
                    end_raw,
                    e,
                )
                results.append(
                    {
                        "ok": False,
                        "pernr": pernr,
                        "arbpl": arbpl,
                        "start_date": start_raw,
                        "end_date": end_raw,
                        "error": str(e),
                    }
                )
                continue

            if not pernr or not cname or not arbpl:
                logger.error(
                    "Missing fields pernr=%s arbpl=%s start=%s end=%s",
                    pernr,
                    arbpl,
                    start_dats,
                    end_dats,
                )
                results.append(
                    {
                        "ok": False,
                        "pernr": pernr,
                        "arbpl": arbpl,
                        "start_date": start_dats,
                        "end_date": end_dats,
                        "error": "pernr, cname, arbpl, start_date, end_date wajib diisi",
                    }
                )
                continue

            def to_int(v) -> int:
                try:
                    return int(float(v))
                except Exception:
                    return 0

            mint2  = to_int(it.get("mint2"))
            mintu  = to_int(it.get("mintu"))
            mintu2 = to_int(it.get("mintu2"))
            mintu3 = to_int(it.get("mintu3"))

            # ------------------ PARAMETER RFC ------------------
            rfc_params = {
                "I_PERNR":      pernr,
                "I_CNAME":      cname,
                "I_MINTU2":     mintu2,
                "I_MINTU3":     mintu3,
                "I_MINT2":      mint2,
                "I_MINTU":      mintu,
                "I_START_DATE": start_dats,  # DATS yyyymmdd
                "I_END_DATE":   end_dats,    # DATS yyyymmdd
                "I_ARBPL":      arbpl,
            }

            # kalau suatu saat perlu lihat DETAIL param, tinggal naikkan level ke DEBUG
            logger.debug("RFC INPUT %s %s", SAP_SAVE_RFC, rfc_params)

            # ------------------ CALL RFC ------------------
            try:
                resp = conn.call(SAP_SAVE_RFC, **rfc_params)

                ret = (resp or {}).get("E_RETURN") or {}
                rtype = (ret.get("TYPE") or "").upper()
                msg = ret.get("MESSAGE") or ""

                result_rec: Dict[str, Any] = {
                    "ok": rtype in ("", "S", "I", "W"),
                    "pernr": pernr,
                    "arbpl": arbpl,
                    "cname": cname,
                    "start_date": start_dats,
                    "end_date": end_dats,
                    "mint2": mint2,
                    "mintu": mintu,
                    "mintu2": mintu2,
                    "mintu3": mintu3,
                    "return_type": rtype,
                    "return_id": ret.get("ID"),
                    "return_number": ret.get("NUMBER"),
                    "return_message": msg,
                    "message_v1": ret.get("MESSAGE_V1"),
                    "message_v2": ret.get("MESSAGE_V2"),
                    "message_v3": ret.get("MESSAGE_V3"),
                    "message_v4": ret.get("MESSAGE_V4"),
                }

                # 1 BARIS INFO PER ITEM: input intinya + hasil
                logger.info(
                    "RFC %s pernr=%s arbpl=%s range=%s-%s mint2=%d mintu=%d mintu2=%d mintu3=%d type=%s msg=%s",
                    SAP_SAVE_RFC,
                    pernr,
                    arbpl,
                    start_dats,
                    end_dats,
                    mint2,
                    mintu,
                    mintu2,
                    mintu3,
                    rtype or "-",
                    msg,
                )

                results.append(result_rec)

            except (ABAPApplicationError, ABAPRuntimeError, CommunicationError) as e:
                logger.error(
                    "RFC %s FAILED pernr=%s arbpl=%s range=%s-%s error=%s",
                    SAP_SAVE_RFC,
                    pernr,
                    arbpl,
                    start_dats,
                    end_dats,
                    e,
                )
                results.append(
                    {
                        "ok": False,
                        "pernr": pernr,
                        "arbpl": arbpl,
                        "cname": cname,
                        "start_date": start_dats,
                        "end_date": end_dats,
                        "mint2": mint2,
                        "mintu": mintu,
                        "mintu2": mintu2,
                        "mintu3": mintu3,
                        "error": f"SAP error: {e}",
                    }
                )

    finally:
        try:
            conn.close()
        except Exception:
            pass

    # ------------------ summary & response ------------------
    total = len(results)
    success = sum(1 for r in results if r.get("ok"))
    failed = total - success
    any_ok = success > 0

    logger.info(
        "SUMMARY %s total=%d success=%d failed=%d user=%s",
        SAP_SAVE_RFC,
        total,
        success,
        failed,
        sap_user,
    )

    summary = {
        "total": total,
        "success": success,
        "failed": failed,
    }

    status_code = 200 if any_ok else 500

    return jsonify({"ok": any_ok, "summary": summary, "results": results}), status_code


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5011, debug=False)
