# Mon Budget

[![CI](https://github.com/b23prodtm/ABudgetManager/actions/workflows/ci.yml/badge.svg)](https://github.com/b23prodtm/ABudgetManager/actions/workflows/ci.yml)
[![balena deploy button](https://www.balena.io/deploy.svg)](https://dashboard.balena-cloud.com/deploy?repoUrl=https://github.com/b23prodtm/ABudgetManager)

Personal budget manager and salary-trend tracker (CakePHP 5 + Python extraction API), designed for Balena Cloud / Raspberry Pi.
UI in French, code and docs in English, amounts in EUR (stored as integer cents).

## User journey

`Welcome` → `Sign up / Log in` → `New month` (upload **bank statement** + **payslip**) → `Review & edit extracted data` → `Validate` → `My budget` (dashboard) → `Log out`

* Bank statement: CSV / TXT (BNP style `dd/mm/yyyy;label;debit;credit`, decimal comma, or a single signed amount), pasted text, or PDF.
* Payslip: PDF (text-based). Fields that cannot be extracted can be typed in the review screen.
* Files are processed in memory / a temp file and **never stored**; only the validated data goes to the database (no social-security number, IBAN or PDF is kept).
* Duplicate protection (`dedupe_key`), optional control totals with an exact-difference banner, balance banner (green / orange / red).
* Rule engine (case/accent-insensitive substring, `*` and `?` wildcards, highest position wins, manual overrides are locked), seeded with the spec's categories and 46 rules.
* Salary trend (`/salary`): running and moving averages, least-squares slope/intercept (same as Excel `SLOPE`/`INTERCEPT`), forecast, R², RMSE, payslip consistency warnings, CSV export.

## Layout

| Path | Role |
|---|---|
| `web/` | CakePHP 5 application (SQLite) |
| `api/` | Flask API: `POST /extract/statement`, `POST /extract/payslip` (multipart `file`, PDF), `GET /health` |
| `db/` | Owner of the SQLite volume: nightly `VACUUM INTO` backups, 14 kept (`/data/backups`) |
| `docker-compose.yml` | `init-web` (migrations, salt), `db`, `web`, `api` |

## Deployment (Balena Cloud)

Click the **balena deploy** button above, pick a fleet (e.g. `raspberry-pi2`), then set `APP_FULL_BASE_URL` and `API_TOKEN` as fleet variables. `docker-compose.yml` uses Compose `version: '2.1'` with balena-supported options only (no `container_name`, no `depends_on` conditions).

## Run

```sh
docker compose up --build        # http://localhost  (set APP_FULL_BASE_URL / API_TOKEN as needed)
```

Development without Docker:

```sh
cd web && composer install
export SECURITY_SALT=$(openssl rand -hex 32)
php bin/cake.php migrations migrate
php bin/cake.php server
```

## Tests

```sh
cd web && vendor/bin/phpunit
cd api && pip install -r requirements-dev.txt && python -m pytest
```

## Not implemented yet (see the functional specification)

Fixed costs, income (gross → net) and transport models and the budget/statement reconciliation, history charts,
XLSX export, `legacy:import`, settings screen, drag-and-drop rule ordering (up/down buttons are provided).
