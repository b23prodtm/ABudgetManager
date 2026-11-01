"""PDF data extraction for bank statements and payslips.

Only parsed values are returned; the uploaded file is processed in memory.
"""
import io
import re
from typing import Dict, List, Optional, Tuple

import pdfplumber

AMOUNT_RE = re.compile(r"^[+-]?\d{1,3}(?:[ \u00a0\u202f.]?\d{3})*(?:,\d{1,2})$|^[+-]?\d+,\d{1,2}$")
DATE_RE = re.compile(r"^(\d{2})[/.](\d{2})[/.](\d{4})$")
CREDIT_HINTS = ("VIR SEPA RECU", "VIR SEPA INSTANT RECU", "REMBOURST", "REMISE", "VERSEMENT", "AVOIR")


def to_cents(text: str) -> Optional[int]:
    t = text.replace("\u00a0", "").replace("\u202f", "").replace(" ", "").replace("€", "").replace("−", "-")
    if not t:
        return None
    t = t.replace(".", "").replace(",", ".") if "," in t else t
    m = re.fullmatch(r"([+-]?)(\d+)(?:\.(\d+))?", t)
    if not m:
        return None
    frac = (m.group(3) or "").ljust(3, "0")
    cents = int(m.group(2)) * 100 + int(frac[:2]) + (1 if int(frac[2]) >= 5 else 0)
    return -cents if m.group(1) == "-" else cents


def is_amount(token: str) -> bool:
    return bool(AMOUNT_RE.match(token.replace("\u00a0", " ").strip()))


def parse_statement_rows(rows: List[List[Dict]], debit_x: Optional[float] = None,
                         credit_x: Optional[float] = None) -> Tuple[List[Dict], List[str]]:
    """rows: lines of words ({"text", "x0", "x1"}) sorted left to right.

    A statement line starts with a date and ends with one amount, placed in the
    debit or credit column (x position of the "Débit"/"Crédit" headers when known).
    """
    lines: List[Dict] = []
    warnings: List[str] = []
    for row in rows:
        if not row:
            continue
        m = DATE_RE.match(row[0]["text"])
        if not m:
            continue
        amounts = [w for w in row[1:] if is_amount(w["text"])]
        if not amounts:
            continue
        # tolerate "1 234,56" split in two words: keep the right-most run as one amount
        amount_word = amounts[-1]
        idx = row.index(amount_word)
        start = idx
        while start - 1 >= 1 and re.fullmatch(r"\d{1,3}", row[start - 1]["text"]) and \
                row[start]["x0"] - row[start - 1]["x1"] < 6 and re.fullmatch(r"\d{3}(?:,\d{1,2})?", amount_word["text"]):
            start -= 1
        text = " ".join(w["text"] for w in row[start:idx + 1])
        cents = to_cents(text)
        if cents is None:
            continue
        label = " ".join(w["text"] for w in row[1:start]).strip()
        label = re.sub(r"^\d{2}[/.]\d{2}[/.]\d{4}\s+", "", label)  # value date
        centre = (amount_word["x0"] + amount_word["x1"]) / 2
        if debit_x is not None and credit_x is not None:
            is_credit = abs(centre - credit_x) < abs(centre - debit_x)
        else:
            is_credit = any(h in label.upper() for h in CREDIT_HINTS) or cents > 0 and text.startswith("+")
        cents = abs(cents)
        lines.append({
            "booked_on": f"{m.group(3)}-{m.group(2)}-{m.group(1)}",
            "label": label,
            "debit_cents": 0 if is_credit else cents,
            "credit_cents": cents if is_credit else 0,
        })
    if debit_x is None or credit_x is None:
        warnings.append("Colonnes Débit/Crédit non détectées : vérifiez le sens de chaque ligne.")
    return lines, warnings


def group_words(words: List[Dict], tolerance: float = 3.0) -> List[List[Dict]]:
    rows: List[List[Dict]] = []
    for w in sorted(words, key=lambda w: (round(w["top"]), w["x0"])):
        if rows and abs(rows[-1][0]["top"] - w["top"]) <= tolerance:
            rows[-1].append(w)
        else:
            rows.append([w])
    return [sorted(r, key=lambda w: w["x0"]) for r in rows]


def extract_statement(data: bytes) -> Dict:
    lines: List[Dict] = []
    warnings: List[str] = []
    with pdfplumber.open(io.BytesIO(data)) as pdf:
        for page in pdf.pages:
            rows = group_words(page.extract_words(keep_blank_chars=False, use_text_flow=False))
            debit_x = credit_x = None
            for row in rows:
                for w in row:
                    t = w["text"].lower()
                    if t in ("débit", "debit", "débits"):
                        debit_x = (w["x0"] + w["x1"]) / 2
                    elif t in ("crédit", "credit", "crédits"):
                        credit_x = (w["x0"] + w["x1"]) / 2
            page_lines, page_warnings = parse_statement_rows(rows, debit_x, credit_x)
            lines += page_lines
            warnings += page_warnings
    return {"lines": lines, "warnings": sorted(set(warnings))}


PAYSLIP_PATTERNS = [
    ("net_paid", r"net\s+pay[ée]\s+(?!(?:en\s+euros\s+)?avant\s+imp[ôo]t\s+)(?:en\s+euros\s+)?"),
    ("net_paid", r"net\s+[àa]\s+payer\s+"),
    ("net_taxable", r"net\s+imposable\s+"),
    ("net_social", r"net\s+social\s+"),
    ("gross", r"(?:total\s+brut|salaire\s+brut|brut)\s+"),
    ("base_salary", r"salaire\s+de\s+base\s+"),
    ("panier", r"(?:indemnit[ée]\s+de\s+)?panier\s+(?:repas\s+)?"),
    ("income_tax_withheld", r"(?:imp[ôo]t\s+sur\s+le\s+revenu|pr[ée]l[èe]vement\s+[àa]\s+la\s+source)[^\d\n-]*"),
]
AMOUNT_TEXT = r"(-?\d{1,3}(?:[ \u00a0\u202f.]\d{3})*,\d{2}|-?\d+,\d{2})"


def parse_payslip_text(text: str) -> Dict:
    """Find the first amount following each known label."""
    fields: Dict[str, int] = {}
    warnings: List[str] = []
    for key, label in PAYSLIP_PATTERNS:
        if key in fields:
            continue
        m = re.search(label + r"(?:[^\d\n-]{0,40}?)" + AMOUNT_TEXT, text, re.IGNORECASE)
        if m:
            cents = to_cents(m.group(1))
            if cents is not None:
                fields[key] = abs(cents)
    if "net_paid" not in fields:
        warnings.append("Net payé introuvable : saisissez-le manuellement.")
    month = re.search(r"(?:p[ée]riode|bulletin)[^\d]{0,30}(\d{2})/(\d{4})", text, re.IGNORECASE)
    result: Dict = {"fields": fields, "warnings": warnings}
    if month:
        result["month"] = f"{month.group(2)}-{month.group(1)}-01"
    return result


def extract_payslip(data: bytes) -> Dict:
    with pdfplumber.open(io.BytesIO(data)) as pdf:
        text = "\n".join((p.extract_text() or "") for p in pdf.pages)
    return parse_payslip_text(text)
