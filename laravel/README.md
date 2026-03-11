# Wagner Meters Procurement (Laravel + React)

Primary application for the procurement research workflow.

## Local Setup

1. Create/update database and migrate:

```bash
cd laravel
php artisan migrate
```

2. Install frontend dependencies and run Vite:

```bash
npm install
npm run dev
```

3. Start Laravel (if not using Laragon virtual host):

```bash
php artisan serve
```

## Authentication

- Register/login through `/register` and `/login`
- Protected views require auth:
  - `/dashboard`
  - `/data-import`
  - `/profile`

## Data Import

Use UI (`/data-import`) or CLI:

```bash
php artisan procurement:import-files path/to/inventory.xlsx
```

The inventory file must have columns A–V (inventory fields) and W–AA (Mfg Part Number 1–5). Each full import **replaces** the previous one: the job deletes the prior import’s inventories (and their MPN/alt-vendor rows). The new import becomes the current dataset.

### Testing import file changes

1. **Back up the database** if you want to keep the current state (e.g. `mysqldump` or export).
2. **Use small/sample files** to try column changes without affecting production data, or run on a copy of the app/DB.
3. **Ensure the queue worker is running** so `ProcessImportJob` runs after upload:
   ```bash
   php artisan queue:work
   ```
4. Upload via **Data Import** and check the import row (status, row counts). Fix any validation errors reported by the form.

### Applying import and running research (Gemini)

1. **Upload** the inventory file on **Data Import**. Wait until the import shows **completed** (refresh the page; the queue job runs in the background).
2. **Run research** from the new import:
   - **UI:** Go to **Run Controls**. Set batch size (default 5) and click **Start run**. Research uses Gemini to fetch current-vendor and alternative-vendor prices for inventory rows that have not yet been researched.
   - **CLI:**
     ```bash
     php artisan procurement:run-research --limit=5
     ```
     Add `--sync` to run synchronously instead of dispatching a job.
3. **Review** results on the **Dashboard** (queue processed %, provider hits, savings per vendor) and **Price Comparison** (current vs lowest current/alt vendor, with links).

## Research pipeline commands

```bash
php artisan procurement:import-files path/to/inventory.xlsx
php artisan procurement:run-research --limit=5 --sync
```

Optional flags for `procurement:run-research`:

- `--limit=N` — Max inventory rows to process (default 5, max 500).
- `--sync` — Run research synchronously instead of dispatching a queue job.

Test Gemini config (and optionally run a part lookup):

```bash
php artisan procurement:test-providers
php artisan procurement:test-providers --mpn=1N4148
```

## Environment variables

Configured via `config/procurement.php`:

- **Gemini:** `GEMINI_API_KEY` (required for research). Optional: `GEMINI_BASE_URL`, `GEMINI_MODEL`, `GEMINI_MAX_OUTPUT_TOKENS`.
- **Research:** `PROCUREMENT_GEMINI_BATCH_SIZE` (default 5), `PROCUREMENT_STRICT_MAPPING`, `PROCUREMENT_MIN_MATCH_SCORE`.

## Phase 5 Operations

- Validation report: `../phase5_1/PHASE_5_1_VALIDATION_REPORT.md`
- Cutover checklist: `../phase5_2/CUTOVER_CHECKLIST.md`
- Stakeholder sign-off: `../phase5_2/STAKEHOLDER_SIGNOFF.md`
