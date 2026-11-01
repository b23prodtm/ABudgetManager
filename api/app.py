"""Extraction API: PDF bank statements and payslips -> structured JSON."""
import hmac
import os

from flask import Flask, jsonify, request

import extract

MAX_BYTES = 10 * 1024 * 1024

app = Flask(__name__)
app.config["MAX_CONTENT_LENGTH"] = MAX_BYTES


def _authorized() -> bool:
    token = os.environ.get("API_TOKEN")
    if not token:
        return True
    header = request.headers.get("Authorization", "")
    return hmac.compare_digest(header, "Bearer " + token)


def _read_pdf():
    if not _authorized():
        return None, (jsonify(error="unauthorized"), 401)
    f = request.files.get("file")
    if f is None:
        return None, (jsonify(error="missing file"), 400)
    data = f.read()
    if not data.startswith(b"%PDF"):
        return None, (jsonify(error="not a PDF"), 400)
    return data, None


@app.get("/health")
def health():
    return jsonify(status="ok")


@app.post("/extract/statement")
def statement():
    data, err = _read_pdf()
    if err:
        return err
    try:
        return jsonify(extract.extract_statement(data))
    except Exception:  # unreadable PDF: let the caller fall back to manual entry
        return jsonify(error="unreadable PDF"), 422


@app.post("/extract/payslip")
def payslip():
    data, err = _read_pdf()
    if err:
        return err
    try:
        return jsonify(extract.extract_payslip(data))
    except Exception:
        return jsonify(error="unreadable PDF"), 422


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)
