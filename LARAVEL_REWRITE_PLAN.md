## Procurement Research Tool – Laravel + React Rewrite Plan

GitHub repository target: `sienajoyjavonitalla-dev/wagner-meters-procurement`.

This document outlines a phased plan to rebuild the existing Python/Streamlit procurement research tool as a **Laravel + React + Vite** application backed by **MySQL** and using **Claude** as the AI fallback where appropriate. Each phase is broken into small, actionable steps.

---

## Phase 1 – Backend Foundation & Upload Flow (Laravel + MySQL)

**Goal:** Laravel app with MySQL schema and an upload/import flow so users can load Excel/CSV via the UI.

### 1.1 – Laravel project setup
- 1.1.1 Create a new Laravel project (e.g. in a `laravel/` subdirectory or repo root).
- 1.1.2 Configure `.env`: set `DB_CONNECTION=mysql`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_HOST`.
- 1.1.3 Set queue driver in `.env` (e.g. `QUEUE_CONNECTION=database` for Phase 1).
- 1.1.4 Run `php artisan key:generate` and confirm the app boots.

### 1.2 – Core migrations (one step per table)
- 1.2.1 Migration: `data_imports` (id, type, user_id, file_names, row_counts, status, created_at, updated_at).
- 1.2.2 Migration: `suppliers` (id, name, code, type, status, data_import_id, created_at, updated_at, deleted_at for soft deletes).
- 1.2.3 Migration: `items` (id, internal_part_number, description, category, lifecycle_status, data_import_id, created_at, updated_at, deleted_at).
- 1.2.4 Migration: `purchases` (id, item_id, supplier_id, unit_price, quantity, currency, order_date, po_reference, data_import_id, created_at, updated_at, deleted_at).
- 1.2.5 Migration: `mappings` (id, item_id, mpn, manufacturer, mapping_status, confidence, notes, lookup_mode, data_import_id, created_at, updated_at, deleted_at).
- 1.2.6 Migration: `research_tasks` (id, task_type, item_id, supplier_id, status, priority, batch_id, notes, created_at, updated_at).
- 1.2.7 Migration: `price_findings` (id, research_task_id, provider, currency, price_breaks_json, min_unit_price, match_score, accepted, created_at, updated_at).
- 1.2.8 Migration: `actions` (id, research_task_id, estimated_savings, action_type, approval_status, created_at, updated_at).
- 1.2.9 Migration: `fx_snapshots` or `system_metrics` (id, key, value_json, created_at) for FX rates and pipeline metadata.
- 1.2.10 Run all migrations and verify tables in MySQL.

### 1.3 – Eloquent models
- 1.3.1 Create `DataImport` model (fillable, relation to User if applicable).
- 1.3.2 Create `Supplier` model with soft deletes and `data_import_id`.
- 1.3.3 Create `Item` model with soft deletes, relations to purchases and mappings.
- 1.3.4 Create `Purchase` model with `item_id`, `supplier_id`, casts for decimals/dates.
- 1.3.5 Create `Mapping` model with `item_id`, soft deletes.
- 1.3.6 Create `ResearchTask` model with relations to Item, Supplier, PriceFindings, Action.
- 1.3.7 Create `PriceFinding` model with `research_task_id`, JSON cast for price_breaks.
- 1.3.8 Create `Action` model with `research_task_id`.
- 1.3.9 Create `FxSnapshot` or use a generic `SystemMetric` model if preferred.

### 1.4 – Upload/import flow (small steps)
- 1.4.1 Add route and Blade view for “Data Import” page (form with file inputs for inventory Excel, vendor-priority CSV, item-spread CSV, optional mpn_map CSV).
- 1.4.2 Add validation rules: required columns for each file type (inventory: Transaction Date, Vendor Name, Item ID, Description, Ext. Cost, Unit Cost, Quantity; vendor_priority: Vendor Name, priority_rank; item_spread: Item ID; mpn_map: Item ID, mpn).
- 1.4.3 Create `ProcessImportJob`: accept paths or stored files, create one `DataImport` row (status pending), then enqueue the job.
- 1.4.4 Inside the job: for the given import type, soft-delete or archive existing rows tied to the “current” import (e.g. latest `data_import_id` per type), then insert new rows from the uploaded files, link to the new `DataImport` id, set status to completed/failed.
- 1.4.5 After job runs: update `DataImport` with row counts and status; optionally show success/error message and link to import history.
- 1.4.6 Ensure “current” data queries (for research queue later) scope to the latest successful `DataImport` per type (or use a `is_current` flag on `DataImport`).

### 1.5 – One-time import and auth
- 1.5.1 Create Artisan command `procurement:import-files {inventory} {vendor_priority} {item_spread} {--mpn-map=}` that reads from local paths and runs the same logic as the job (snapshot: soft-delete previous, insert new, record `DataImport`).
- 1.5.2 Install Laravel Breeze or Sanctum (or use default auth scaffolding); protect the import route so only logged-in users (or an “admin” role) can upload.

**Deliverables:** Running Laravel app, MySQL with all tables, Eloquent models, upload screen with validation and snapshot job, one-time import command, basic auth.

---

## Phase 2 – Research Pipeline Services & Jobs

**Goal:** Port the research loop logic into Laravel services and queued jobs (DigiKey, Mouser, Nexar, Claude fallback).

### 2.1 – Queue builder and mapping services
- 2.1.1 Create `QueueBuilderService`: read from MySQL (items, purchases, suppliers) scoped to latest `DataImport`; apply top vendors by spend, items per vendor, multi-source spread; create `research_tasks` rows with task_type and status.
- 2.1.2 Create `MappingService`: build in-memory index from `mappings` (item_id → mpn, status, lookup_mode); expose `getMappedMpn(itemId)`, `getMappingStatus(itemId)`, `isNonCatalog(itemId)`; enforce strict mapping (mapped / non_catalog / needs_mapping).

### 2.2 – Provider API clients
- 2.2.1 Create `DigiKeyClient` service: read credentials from config/env, OAuth2 token + product search, return normalized structure (provider, currency, price breaks, min unit price).
- 2.2.2 Create `MouserClient` service: part search API, same normalized output.
- 2.2.3 Create `NexarClient` service: GraphQL `supSearchMpn`, same normalized output.
- 2.2.4 Add a shared DTO or array shape for “price finding” so all three clients fill the same structure for `price_findings` table.

### 2.3 – Claude AI fallback
- 2.3.1 Create `ClaudeResearchService`: build prompt + JSON schema from task (item, vendor, description, query MPN), call Anthropic API (or gateway), parse JSON into the same price-finding structure.
- 2.3.2 Wire Claude API key from `.env`; make the service optional when key is missing.

### 2.4 – Research job and post-processing
- 2.4.1 Create `RunResearchJob` (or batch of jobs): select pending `research_tasks`, for each get candidate MPNs from MappingService, call DigiKey/Mouser/Nexar in turn, compute match score and accepted flag; if no accepted match and Claude enabled, call ClaudeResearchService; write `price_findings`, update `research_tasks` status and notes.
- 2.4.2 Create `PostProcessResearchService`: for each task with findings, pick best match, compute estimated_savings, action_type, priority_score; upsert `actions` table.
- 2.4.3 After a run, optionally write FX snapshot (e.g. call a simple FX API and store in `fx_snapshots` or `system_metrics`).
- 2.4.4 Add an Artisan command or controller action to “build queue” then “run research” (optionally with Claude fallback and batch size).

**Deliverables:** QueueBuilderService, MappingService, DigiKey/Mouser/Nexar clients, ClaudeResearchService, RunResearchJob, PostProcessResearchService, FX snapshot; one command or API to run a full pass.

---

## Phase 3 – Public APIs for the Frontend

**Goal:** JSON API for the React dashboard and run triggers.

### 3.1 – Read-only API endpoints
- 3.1.1 GET KPIs/summary: queue status counts, mapped vs needs-mapping counts, modeled savings total, provider hit counts (from `price_findings` / `actions`).
- 3.1.2 GET research queue: paginated list with filters (status, vendor, item search, priority).
- 3.1.3 GET price comparison: actions with best finding per task (savings, price deltas, provider).
- 3.1.4 GET research evidence: by task_id or item_id, return task + all `price_findings` for that task.
- 3.1.5 GET vendor progress: per-vendor stats (task counts, processed %, totals).
- 3.1.6 GET mapping review queue: items needing mapping or MPN-fill; GET MPN worklist (e.g. top 20).
- 3.1.7 GET system health: last research run time, FX snapshot, which providers are enabled (from config/env).

### 3.2 – Trigger and auth
- 3.2.1 POST (or similar) to trigger research run: optional body `{ "agent_fallback": "claude", "batch_size": 10 }`; create queue/build run and enqueue `RunResearchJob`; return job id or status URL.
- 3.2.2 GET run status/logs: by job id or latest run, return status and log output for polling.
- 3.2.3 Add API auth: Sanctum token or session-based; ensure all above routes require auth (and optionally role).

**Deliverables:** Documented API routes (OpenAPI or markdown list); frontend can list queue, compare prices, view evidence, trigger runs, poll status.

---

## Phase 4 – React + Vite Dashboard

**Goal:** React SPA that replaces the Streamlit UI and consumes the Laravel API.

### 4.0 – Vite setup and UI parity with Wagner accounting app
- 4.0.1 **Fix frontend build with Vite:** Ensure the procurement app uses Vite for assets (Laravel’s default `vite.config.js` and `resources/js/app.js`), or add a dedicated `frontend/` React+Vite app that builds into `public/build` and is loaded from Laravel Blade. Run `npm install` and `npm run build` (or `npm run dev`) so the UI loads without falling back to CDN.
- 4.0.2 **Match UI to Wagner accounting app:** Align layout and styling with `C:\laragon\www\wagners-accounting-app`:
  - **Layout:** Fixed sidebar (e.g. 240px) + main content area; full-height flex layout; sidebar contains brand/logo, user profile, nav links with icons, optional submenus, footer with logout.
  - **Theme:** Dark theme consistent with accounting app: background `#0d1117`, sidebar `#161b22`, borders `#30363d`, text `#e6edf3`, muted `#8b949e`, active link `#58a6ff` with light blue background; same nav link hover/active and logout button styles.
  - **Structure:** Reuse the same CSS class names and structure where possible (e.g. `.authenticated-layout`, `.app-nav`, `.app-nav-brand`, `.app-nav-links`, `.app-nav-link`, `.app-main`) so the procurement dashboard looks and behaves like the accounting app (same logo area, profile strip, icon+label nav items, main scroll area).
- 4.0.3 **Reference implementation:** Use `wagners-accounting-app/frontend/src/layouts/AuthenticatedLayout.jsx` and `AuthenticatedLayout.css` as the visual reference; adapt for procurement routes (Data Import, Dashboard, Research Queue, etc.) instead of Upload, Employees, 401K, etc.

### 4.1 – Project and layout
- 4.1.1 Scaffold React + Vite project (e.g. in `frontend/` or `resources/js` with Vite); set API base URL and auth (e.g. Bearer token or cookie).
- 4.1.2 Add UI library and charting (e.g. Recharts or Chart.js); keep styling in line with 4.0 (no conflicting Tailwind/component library theme).
- 4.1.3 Implement app layout per 4.0.2: sidebar + main, auth state (login/logout), nav links for procurement views.

### 4.2 – Core views (one step per view)
- 4.2.1 Home/Overview: fetch KPI endpoint; show queue processed %, needs research count, catalog hits, modeled savings, mapped vs needs-mapping; queue status pie chart, provider hits bar chart; last update time.
- 4.2.2 Item Price Comparison: fetch price-comparison API with filters (status, price result, min savings, vendor/item search); table with columns as in current dashboard; CSV export button.
- 4.2.3 Research Evidence: dropdown or link to select task; fetch evidence API for that task; show task row + table of all findings.
- 4.2.4 Vendor Progress: fetch vendor-progress API; table or cards per vendor.
- 4.2.5 Mapping Review / MPN Worklist: fetch mapping-review and MPN worklist APIs; tabular views and optional download.
- 4.2.6 Run Controls: form to trigger research (API-only vs Claude fallback, batch size); button to start; display last run status and logs (poll status endpoint).

### 4.3 – Data and polish
- 4.3.1 Use React Query (or similar) for fetching and caching; loading and error states for each view.
- 4.3.2 Document local dev: `npm run dev` for Vite, Laravel on `php artisan serve` or Laragon; production build steps.

**Deliverables:** React app with all views above, layout and theming, feature parity with Streamlit dashboard.

---

## Phase 5 – Validation, Migration, and Cutover

**Goal:** Verify behavior and switch users from Python tool to new stack.

### 5.1 – Validation
- 5.1.1 Run Python research loop on a sample dataset; export CSVs (queue, actions, findings).
- 5.1.2 Run Laravel pipeline on the same data (same inventory/vendor_priority/item_spread/mpn_map); compare queue composition, provider hits, accepted matches, savings estimates.
- 5.1.3 Document differences (e.g. match scoring edge cases); fix or accept and document.
- 5.1.4 Validate Claude fallback on a few items; check output shape and business rules.

### 5.2 – Cutover
- 5.2.1 Get stakeholder sign-off on dashboard and workflow.
- 5.2.2 Plan cutover: freeze Python runs for a short window; run Laravel import + full research pass; point users to React dashboard.
- 5.2.3 Update docs (README, runbooks) to describe Laravel + React flow and where to get API keys.

**Deliverables:** Validation notes or report; cutover checklist and updated documentation.

---

## Phase 6 – Hardening and Enhancements (Post-MVP)

**Goal:** Backlog of improvements; pick by priority.

### 6.1 – Optional enhancements (each is a small bit)
- 6.1.1 Audit log: log research decisions, import events, and (optionally) supplier-related actions to an `audit_logs` table.
- 6.1.2 Scheduling: Laravel scheduler or cron to run “build queue + run research” nightly (or configurable); optional scope (e.g. only certain vendors).
- 6.1.3 RBAC/SSO: roles (e.g. viewer vs admin), optional SAML/OIDC for enterprise SSO.
- 6.1.4 Analytics: long-term savings tracking, trend by supplier/commodity; new API endpoints and dashboard widgets.
- 6.1.5 Self-service config: UI to set min savings %, match-score threshold, strict vs relaxed mapping; store in `system_metrics` or a `settings` table and use in QueueBuilderService and research job.

**Deliverables:** Prioritized backlog; implement in order of business value.
