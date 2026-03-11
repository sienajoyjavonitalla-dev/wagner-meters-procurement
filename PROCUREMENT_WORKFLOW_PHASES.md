# Procurement Workflow Rewrite – Build Phases

This document breaks the new procurement logic and workflow into phases for implementation. It replaces the multi-file import and research-queue flow with a **single inventory file**, new **inventories / mpn / alt_vendors** schema, and **Gemini**-based price lookup (one prompt for current-vendor price + alternative US vendors).

**Sample import format:** [Inventory List (Google Sheets)](https://docs.google.com/spreadsheets/d/1T2IEPrW2ZI3pEtMiVTah7Ia1W3yqw0Lj31ZAX5xOwCc/edit?usp=sharing) — columns A–V → inventories; W–AA → mpn (1:many).

---

## Phase 1 – New schema and models

**Goal:** Add new database tables and Eloquent models for the inventory-centric flow. Old tables remain for now.

### 1.1 – Migrations

| Step | Task | File / location |
|------|------|------------------|
| 1.1.1 | Create migration `create_inventories_table`: id, data_import_id (FK), transaction_date, item_id, description, fiscal_period, fiscal_year, reference_id, location_id, source_id, type, application_id, unit, quantity, unit_cost, ext_cost, comments, product_line, vendor_name, contact, address, region, phone, email, research_completed_at (nullable timestamp), timestamps | `laravel/database/migrations/` |
| 1.1.2 | Create migration `create_mpn_table`: id, inventory_id (FK), part_number, unit_price (nullable), price_fetched_at (nullable), currency (nullable), timestamps | `laravel/database/migrations/` |
| 1.1.3 | Create migration `create_alt_vendors_table`: id, inventory_id (FK), vendor_name, unit_price, url (nullable), fetched_at (timestamp), timestamps | `laravel/database/migrations/` |
| 1.1.4 | Run migrations and verify tables in MySQL | `php artisan migrate` |

### 1.2 – Eloquent models

| Step | Task | File |
|------|------|------|
| 1.2.1 | Create `Inventory` model: fillable (all A–V columns + data_import_id, research_completed_at), belongsTo DataImport, hasMany Mpn, hasMany AltVendor | `laravel/app/Models/Inventory.php` |
| 1.2.2 | Create `Mpn` model: fillable (inventory_id, part_number, unit_price, price_fetched_at, currency), belongsTo Inventory | `laravel/app/Models/Mpn.php` |
| 1.2.3 | Create `AltVendor` model: fillable (inventory_id, vendor_name, unit_price, url, fetched_at), belongsTo Inventory | `laravel/app/Models/AltVendor.php` |
| 1.2.4 | Update `DataImport` model: add hasMany Inventory (and optionally keep existing relations until Phase 7) | `laravel/app/Models/DataImport.php` |

**Deliverables:** New tables `inventories`, `mpn`, `alt_vendors`; models `Inventory`, `Mpn`, `AltVendor` with relations; `DataImport` hasMany Inventory.

---

## Phase 2 – Single-file import

**Goal:** Data import accepts only one file (inventory Excel/CSV). Parsed rows go into `inventories` and `mpn`; old import files and parsing are no longer required.

### 2.1 – Request and validation

| Step | Task | File |
|------|------|------|
| 2.1.1 | Change `StoreDataImportRequest`: require only `inventory` (file, mimes:xlsx,xls,csv); remove vendor_priority, item_spread, mpn_map | `laravel/app/Http/Requests/StoreDataImportRequest.php` |
| 2.1.2 | Validate inventory file has required headers: Transaction Date, Item ID, Description, Unit Cost, Ext. Cost, Quantity, Vendor Name, Product Line; optionally Mfg Part Number 1–5 | Same + custom validation |
| 2.1.3 | Remove or simplify column validators for vendor_priority, item_spread, mpn_map | Same |

### 2.2 – Controller and job

| Step | Task | File |
|------|------|------|
| 2.2.1 | Update `DataImportController::store`: accept only inventory file; store file under imports; create one DataImport row; dispatch ProcessImportJob with DataImport + single inventory path | `laravel/app/Http/Controllers/DataImportController.php` |
| 2.2.2 | Update `ProcessImportJob`: constructor (DataImport, string inventoryPath only); remove vendor_priority, item_spread, mpn_map paths | `laravel/app/Jobs/ProcessImportJob.php` |
| 2.2.3 | In job handle: clear previous run’s inventories/mpn/alt_vendors for previous data_import_id (or current full import) before inserting new data | Same |
| 2.2.4 | Parse inventory file: for each data row (skip blank rows), insert one `inventories` row (map columns A–V); then for non-empty Mfg Part Number 1–5 (W–AA), insert one `mpn` row per value with inventory_id | Same |
| 2.2.5 | Update DataImport row_counts (e.g. inventories_count, mpn_count) and status completed/failed | Same |

### 2.3 – Sheet column mapping

| Step | Task |
|------|------|
| 2.3.1 | Map sheet columns: A→transaction_date, B→item_id, C→description, D→fiscal_period, E→fiscal_year, F→reference_id, G→location_id, H→source_id, I→type, J→application_id, K→unit, L→quantity, M→unit_cost, N→ext_cost, O→comments, P→product_line, Q→vendor_name, R→contact, S→address, T→region, U→phone, V→email; W–AA → mpn.part_number (one row per non-empty value) |

**Deliverables:** Single-file upload in UI; ProcessImportJob populates `inventories` and `mpn`; DataImport stores one file name and row counts.

---

## Phase 3 – Gemini service and prompt

**Goal:** Add Gemini as the research provider: one prompt per item returns current-vendor price and alternative US vendors; config and persistence pattern defined.

### 3.1 – Config and env

| Step | Task | File |
|------|------|------|
| 3.1.1 | Add `gemini` section in config: api_key, model, base_url (or use Google default) | `laravel/config/procurement.php` |
| 3.1.2 | Add to `.env.example`: GEMINI_API_KEY, optional GEMINI_MODEL, GEMINI_BASE_URL | `laravel/.env.example` |
| 3.1.3 | Add `gemini_batch_size` (or `research_batch_size`) to research config; default 50 | Same config |

### 3.2 – GeminiResearchService

| Step | Task | File |
|------|------|------|
| 3.2.1 | Create `GeminiResearchService`: fromConfig(), isEnabled() (checks api_key) | `laravel/app/Services/GeminiResearchService.php` |
| 3.2.2 | Implement lookup(vendorName, productLine, mpns[], quantity): build prompt (vendor, product line, MPNs, quantity; ask for current vendor price + US-only alternative vendors with price and link) | Same |
| 3.2.3 | Call Gemini API (HTTP client); parse JSON response with schema: current_vendor_price, current_vendor_url (optional), current_vendor_currency; alt_vendors: [{ vendor_name, unit_price, url }] | Same |
| 3.2.4 | Return structured array for persistence; handle errors and invalid JSON gracefully | Same |

### 3.3 – Persistence helpers

| Step | Task |
|------|------|
| 3.3.1 | Document/implement: current vendor result → update `mpn` (unit_price, price_fetched_at, currency) for the inventory’s mpn row(s); alternatives → insert into `alt_vendors`; set `inventories.research_completed_at` | In RunResearchJob (Phase 4) or a small helper |

**Deliverables:** Config and env for Gemini; `GeminiResearchService` with single-prompt lookup returning current + alt vendors; clear contract for writing to mpn and alt_vendors.

---

## Phase 4 – Research run job (batch of 50)

**Goal:** Research run selects up to 50 inventory rows not yet researched, calls Gemini per row, and persists results to mpn and alt_vendors.

### 4.1 – Trigger and job wiring

| Step | Task | File |
|------|------|------|
| 4.1.1 | Update `ProcurementController::triggerRun`: remove “build queue”; accept batch_size (default 50); create ResearchRun with use_gemini (or equivalent); dispatch job with batch_size and run id | `laravel/app/Http/Controllers/Api/ProcurementController.php` |
| 4.1.2 | Add migration or reuse: research_runs.use_claude → use_gemini (or add use_gemini column) | `laravel/database/migrations/` |
| 4.1.3 | Refactor `RunResearchJob`: constructor (batchSize, researchRunId); no batchId/build queue; select from inventories where research_completed_at is null and data_import_id = current import, limit batch_size; optionally dedupe by item_id in batch | `laravel/app/Jobs/RunResearchJob.php` |

### 4.2 – Per-inventory research logic

| Step | Task | File |
|------|------|------|
| 4.2.1 | For each selected inventory: load mpn rows (part numbers); call GeminiResearchService::lookup(inventory.vendor_name, inventory.product_line, mpn part numbers, inventory.quantity) | Same |
| 4.2.2 | On success: update mpn row(s) with unit_price, price_fetched_at, currency; insert alt_vendors rows; set inventory.research_completed_at = now() | Same |
| 4.2.3 | On failure: log error; optionally leave research_completed_at null for retry or set and store error state | Same |
| 4.2.4 | Track “provider hits” (e.g. Gemini call count) for dashboard; store on ResearchRun or a small stats table if needed | Same / summary API |

### 4.3 – Deprecate old research path

| Step | Task |
|------|------|
| 4.3.1 | Remove or bypass: QueueBuilderService in triggerRun; BuildResearchQueueCommand for new flow; DigiKey/Mouser/Nexar/Claude in RunResearchJob for the new flow (or keep behind a flag until Phase 7) |
| 4.3.2 | Do not create research_tasks/actions/price_findings for the new flow; read from inventories + mpn + alt_vendors only |

**Deliverables:** Trigger run with batch_size only; job processes 50 inventories via Gemini and updates mpn/alt_vendors/research_completed_at; provider hits available for dashboard.

---

## Phase 5 – Backend APIs (summary, analytics, price comparison)

**Goal:** Summary and analytics use the new schema; price comparison endpoint returns data for the new columns; remove or stub old endpoints.

### 5.1 – Summary endpoint

| Step | Task | File |
|------|------|------|
| 5.1.1 | Rewrite `ProcurementController::summary`: queue_status_counts from inventories (e.g. count with/without research_completed_at); provider_hit_counts (e.g. Gemini); remove modeled_savings_total, mapping_counts | `laravel/app/Http/Controllers/Api/ProcurementController.php` |
| 5.1.2 | Add “savings potential per vendor”: per inventory, savings_potential = unit_cost − min(lowest from mpn, lowest from alt_vendors); aggregate by vendor_name; return top vendors by that potential | Same |

### 5.2 – Analytics endpoint

| Step | Task | File |
|------|------|------|
| 5.2.1 | Rewrite `ProcurementController::analytics`: top_suppliers_by_savings → savings potential by vendor (same as 5.1.2); daily_modeled_savings → optional trend by research_completed_at date or remove | Same |

### 5.3 – Price comparison endpoint

| Step | Task | File |
|------|------|------|
| 5.3.1 | Rewrite `ProcurementController::priceComparison`: read from inventories + mpn + alt_vendors (current import); for each inventory (or aggregated by item_id): item_id, description, vendor_name, current unit_cost, quantity; lowest current vendor price (from mpn), current vendor name, url; savings (current − lowest current); lowest alt vendor price, alt vendor name, url; savings (current − lowest alt) | Same |
| 5.3.2 | Remove action_type from response; ensure response shape matches frontend columns (Phase 6) | Same |

### 5.4 – Remove or stub endpoints

| Step | Task | File |
|------|------|------|
| 5.4.1 | `queue`: return empty data or minimal stub | Same |
| 5.4.2 | `evidence`: return 404 or empty | Same |
| 5.4.3 | `vendorProgress`: return empty array | Same |
| 5.4.4 | `mappingReview`: return empty array | Same |
| 5.4.5 | `systemHealth`: providers_enabled: replace claude with gemini | Same |

**Deliverables:** Summary and analytics use inventories/mpn/alt_vendors; price comparison returns new shape; queue/evidence/vendor-progress/mapping-review stubbed or removed; system health shows Gemini.

---

## Phase 6 – Frontend updates

**Goal:** Dashboard, Data Import, navigation, Price Comparison, Run Controls, and How to use reflect the new workflow and data.

### 6.1 – Dashboard

| Step | Task | File |
|------|------|------|
| 6.1.1 | Keep: Queue processed, Provider hits | `laravel/resources/js/pages/Dashboard.jsx` |
| 6.1.2 | Remove: Modeled savings card, Savings potential by current vendor chart/table | Same |
| 6.1.3 | Add: Savings potential per vendor (vendor with lowest price); consume new summary/analytics fields | Same |
| 6.1.4 | Replace any “Claude” label with “Gemini” | Same |

### 6.2 – Data Import

| Step | Task | File |
|------|------|------|
| 6.2.1 | Single file input: “Inventory (Excel/CSV)” only; remove vendor priority, item spread, MPN map inputs | `laravel/resources/js/pages/DataImport.jsx` |
| 6.2.2 | Update description text to match single-file import | Same |

### 6.3 – Navigation and routes

| Step | Task | File |
|------|------|------|
| 6.3.1 | Remove routes: research-queue, research-evidence, vendor-progress, mapping-review | `laravel/resources/js/AppRouter.jsx` |
| 6.3.2 | Remove nav links: Research Queue, Research Evidence, Vendor Progress, Mapping Review | `laravel/resources/js/layouts/AuthenticatedLayout.jsx` |

### 6.4 – Item Price Comparison

| Step | Task | File |
|------|------|------|
| 6.4.1 | Remove column: Action type | `laravel/resources/js/pages/PriceComparison.jsx` |
| 6.4.2 | Add columns: Lowest price (current vendor), Current vendor name (linked to URL), Savings (current − lowest current); Lowest price (alt), Alt vendor name (linked to URL), Savings (current − lowest alt) | Same |
| 6.4.3 | Use new price-comparison API response; keep filters (vendor, item, min savings) if desired | Same |

### 6.5 – Run Controls

| Step | Task | File |
|------|------|------|
| 6.5.1 | Remove “Build queue first” checkbox | `laravel/resources/js/pages/RunControls.jsx` |
| 6.5.2 | Replace “Claude” with “Gemini” in agent fallback (or remove dropdown and always use Gemini) | Same |
| 6.5.3 | Rename “Claude batch” to “Gemini batch” or “Batch size”; default 50; settings: gemini_batch_size | Same |
| 6.5.4 | Remove Codex/“None (API only)” if not used | Same |

### 6.6 – How to use

| Step | Task | File |
|------|------|------|
| 6.6.1 | Rewrite workflow steps: (1) Single inventory file import, (2) Run research (batch 50, Gemini), (3) Monitor run status, (4) Dashboard (queue processed, provider hits, savings potential per vendor), (5) Item Price Comparison (current vs lowest current vendor, current vs lowest alt vendor), (6) Users/Profile unchanged | `laravel/resources/js/pages/BeginnerGuide.jsx` |
| 6.6.2 | Remove sections: Research Queue, Research Evidence, Vendor Progress, Mapping Review | Same |
| 6.6.3 | Update Run Controls and Data Import steps to match new UI | Same |

**Deliverables:** Dashboard, Data Import, nav, Price Comparison, Run Controls, and How to use aligned with new workflow and APIs.

---

## Phase 7 – Clean-up (optional / follow-up)

**Goal:** Remove obsolete code, routes, and optionally old tables.

### 7.1 – Backend clean-up

| Step | Task |
|------|------|
| 7.1.1 | Remove or stub: QueueBuilderService usage, BuildResearchQueueCommand (or repurpose), MappingService in research flow, ClaudeResearchService (or keep for reference), DigiKey/Mouser/Nexar if unused |
| 7.1.2 | Remove PostProcessResearchService, FxSnapshotService usage if no longer needed |
| 7.1.3 | Update Artisan commands: procurement:import-files (single file), remove or update procurement:build-queue, procurement:run-research (Gemini-only) |

### 7.2 – Frontend clean-up

| Step | Task |
|------|------|
| 7.2.1 | Delete or archive: ResearchQueue.jsx, ResearchEvidence.jsx, VendorProgress.jsx, MappingReview.jsx (or keep as stubs that redirect) |

### 7.3 – Database (optional, later)

| Step | Task |
|------|------|
| 7.3.1 | Migrations to drop or archive: research_tasks, actions, price_findings, mappings, vendor_priorities, item_spreads, purchases, suppliers, items — only after full cutover and backup |

**Deliverables:** No references to old queue/evidence/vendor/mapping flows in active code; optional table drops in a separate migration after validation.

---

## Implementation order summary

| Phase | Focus |
|-------|--------|
| 1 | New schema and models (inventories, mpn, alt_vendors) |
| 2 | Single-file import (request, controller, job, parsing) |
| 3 | Gemini config and GeminiResearchService |
| 4 | Research run job (batch 50, Gemini, persistence) |
| 5 | Backend APIs (summary, analytics, price comparison; stub others) |
| 6 | Frontend (dashboard, data import, nav, price comparison, run controls, how to use) |
| 7 | Clean-up (optional) |

---

## Data flow (reference)

```
[Single inventory file] → DataImport → ProcessImportJob
    → inventories (A–V) + mpn (W–AA, 1:many)

[Trigger run, batch 50] → RunResearchJob
    → inventories where research_completed_at is null
    → GeminiResearchService::lookup(...) per row
    → Update mpn (price, date); insert alt_vendors; set research_completed_at

[Dashboard / Price comparison] → Summary & price-comparison APIs
    → Read inventories + mpn + alt_vendors
```
