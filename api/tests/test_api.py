import io
import os
import sys

import pytest
from reportlab.lib.pagesizes import A4
from reportlab.pdfgen import canvas

sys.path.insert(0, os.path.dirname(os.path.dirname(__file__)))

import app as api  # noqa: E402
import extract  # noqa: E402


def make_pdf(rows):
    buf = io.BytesIO()
    c = canvas.Canvas(buf, pagesize=A4)
    y = 800
    for row in rows:
        for x, text in row:
            c.drawString(x, y, text)
        y -= 18
    c.save()
    return buf.getvalue()


STATEMENT = [
    [(40, "Date"), (120, "Libelle"), (400, "Debit"), (480, "Credit")],
    [(40, "02/05/2026"), (120, "CARTE LIDL GRENOBLE"), (400, "45,10")],
    [(40, "03/05/2026"), (120, "VIR SEPA RECU ACME SA"), (480, "1 500,00")],
]

PAYSLIP = [
    [(40, "Bulletin de paie - Periode 06/2026")],
    [(40, "Salaire de base"), (300, "1 900,00")],
    [(40, "Brut"), (300, "2 050,00")],
    [(40, "Net social"), (300, "1 600,00")],
    [(40, "Net imposable"), (300, "1 650,00")],
    [(40, "Net paye avant impot"), (300, "1 580,50")],
]


def test_to_cents():
    assert extract.to_cents("1 234,56") == 123456
    assert extract.to_cents("1.234,56") == 123456
    assert extract.to_cents("-0,5") == -50
    assert extract.to_cents("abc") is None


def test_statement_columns():
    res = extract.extract_statement(make_pdf(STATEMENT))
    assert res["lines"] == [
        {"booked_on": "2026-05-02", "label": "CARTE LIDL GRENOBLE", "debit_cents": 4510, "credit_cents": 0},
        {"booked_on": "2026-05-03", "label": "VIR SEPA RECU ACME SA", "debit_cents": 0, "credit_cents": 150000},
    ]
    assert res["warnings"] == []


def test_statement_without_header_uses_hints():
    rows = [[(40, "02/05/2026"), (120, "REMBOURST CB SHOP"), (400, "10,00")]]
    res = extract.extract_statement(make_pdf(rows))
    assert res["lines"][0]["credit_cents"] == 1000
    assert res["warnings"]


def test_payslip_text():
    res = extract.parse_payslip_text("Salaire de base 1 900,00\nBrut 2 050,00\nNet social 1 600,00\nNet payé 1 580,50")
    assert res["fields"]["net_paid"] == 158050
    assert res["fields"]["base_salary"] == 190000
    assert res["fields"]["gross"] == 205000
    assert res["warnings"] == []
    assert extract.parse_payslip_text("rien")["warnings"]


def test_payslip_pdf():
    res = extract.extract_payslip(make_pdf(PAYSLIP))
    assert res["fields"]["net_paid"] == 158050
    assert res["fields"]["net_taxable"] == 165000
    assert res["month"] == "2026-06-01"


@pytest.fixture
def client():
    return api.app.test_client()


def test_endpoints(client, monkeypatch):
    monkeypatch.delenv("API_TOKEN", raising=False)
    assert client.get("/health").json == {"status": "ok"}
    r = client.post("/extract/statement", data={"file": (io.BytesIO(make_pdf(STATEMENT)), "s.pdf")})
    assert r.status_code == 200 and len(r.json["lines"]) == 2
    r = client.post("/extract/payslip", data={"file": (io.BytesIO(b"hello"), "x.pdf")})
    assert r.status_code == 400
    assert client.post("/extract/payslip").status_code == 400


def test_token(client, monkeypatch):
    monkeypatch.setenv("API_TOKEN", "s3cret")
    r = client.post("/extract/payslip", data={"file": (io.BytesIO(make_pdf(PAYSLIP)), "p.pdf")})
    assert r.status_code == 401
    r = client.post("/extract/payslip", data={"file": (io.BytesIO(make_pdf(PAYSLIP)), "p.pdf")},
                    headers={"Authorization": "Bearer " + "s3cret"})
    assert r.status_code == 200
