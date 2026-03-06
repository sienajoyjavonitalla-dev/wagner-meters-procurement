## Procurement Research Tool – Laravel + React Rewrite Plan

GitHub repository target: `sienajavonitalla-dev/wagner-meters-procurement`.

This document outlines a phased plan to rebuild the existing Python/Streamlit procurement research tool as a **Laravel + React + Vite** application backed by **MySQL** and using **Claude** as the AI fallback where appropriate.

---

## Phase 1 – Backend Foundation & Upload Flow (Laravel + MySQL)

- **Goals**
  - Stand up a Laravel application with a clean domain model and MySQL schema that represents suppliers, items, purchases, mappings, research tasks, findings, and actions.
  - Implement an **upload/import flow** so users can upload Excel/CSV files through the UI and have them written into MySQL, instead of manually placing files on the server.
- **Key Tasks**
  - Initialize a new Laravel project (or dedicated module within an existing monolith).
  - Configure `.env` for MySQL (host, database, user, password) and queue driver (e.g., database or Redis).
  - Design and implement migrations and Eloquent models for:
    - `suppliers` (name, code, type, status).
    - `items` (internal part number, description, category, lifecycle status).
    - `purchases` (item, supplier, unit price, quantity, currency, order date, PO reference).
    - `mappings` (item → catalog MPN(s), manufacturer, mapping status, confidence, notes, lookup mode).
    - `research_tasks` (task type, item, supplier, status, priority, batch/run identifiers, notes).
    - `price_findings` (task, provider, currency, price breaks, min unit price, match score, accepted flag).
    - `actions` (derived savings actions, estimated savings, action type, approval status).
    - `fx_snapshots` or a generic `system_metrics` table for storing FX rates and pipeline metadata.
  - Implement an **Upload/Import screen** in Laravel (simple Blade or early React/Vite integration) that:
    - Accepts the inventory Excel and related CSV files from the user.
    - Validates that required columns are present and types/formats are reasonable.
    - Enqueues a background job that parses the files and writes records into MySQL tables (`suppliers`, `items`, `purchases`, `mappings`, etc.), treating each upload as a **snapshot**:
      - Soft-delete or archive rows from the previous snapshot for that import type.
      - Insert the new rows, so all “current” calculations always use the latest successful import.
    - Records import metadata (who uploaded, when, which file names, and high-level row counts) in an `imports` or `data_imports` table.
  - Provide a one-time import option (CLI/Artisan command or admin-only upload) to bootstrap the system with existing historical data.
  - Establish basic authentication/authorization primitives (even if internal-only to start).
- **Deliverables**
  - Running Laravel app connected to MySQL with all core tables and models.
  - One-time import script(s) that can pull existing data from Excel/CSV into MySQL.

---

## Phase 2 – Research Pipeline Services & Jobs

- **Goals**
  - Recreate the core logic of `scripts/research_loop.py` inside Laravel as testable services and queued jobs, now operating on MySQL data instead of files.
  - Preserve the concepts of strict mapping, provider lookups, and AI fallback, while switching the AI integration to **Claude**.
- **Key Tasks**
  - Implement a **queue builder service** that:
    - Reads inventory and purchase history from MySQL.
    - Applies prioritization rules (top vendors by spend, items per vendor, multi-source spread).
    - Creates `research_tasks` entries with appropriate task types and initial status.
  - Implement a **mapping service** that:
    - Builds an in-memory mapping index similar to the Python mapping index.
    - Enforces strict mapping mode (mapped vs non_catalog vs needs_mapping).
    - Exposes helper methods for looking up mapped MPNs and mapping status.
  - Implement **provider clients** as Laravel services using the HTTP client:
    - `DigiKeyClient`, `MouserClient`, `NexarClient` reading credentials from `.env`.
    - Normalize their responses into a common internal structure suitable for `price_findings`.
  - Implement a **Claude AI fallback service**:
    - Decide on the integration mechanism (e.g., HTTPS API to Anthropic or internal gateway).
    - Port the existing JSON-schema-style prompting into a Laravel service that:
      - Builds the research prompt and schema.
      - Sends it to Claude.
      - Parses the JSON response into a `price_findings`-compatible structure.
  - Implement a **research job** (Laravel queued job or batch) that:
    - Selects pending `research_tasks` (optionally in batches).
    - For each task, determines candidate MPNs and mapping conditions.
    - Calls provider clients and evaluates match scores and acceptance rules.
    - Falls back to Claude when configured and no acceptable catalog result is found.
    - Writes `price_findings`, updates `research_tasks` status and notes.
  - Implement a **post-processing service** that:
    - Derives best matches per task.
    - Computes estimated savings, action types, and priority scores.
    - Populates/updates `actions` and summary metrics tables.
    - Captures FX snapshots as needed.
- **Deliverables**
  - Laravel services and queued jobs that can run a full research pass against MySQL data.
  - Unit and integration tests comparing sample runs against the existing Python outputs where feasible.

---

## Phase 3 – Public APIs for the Frontend

- **Goals**
  - Expose a clean JSON API that the React + Vite frontend can consume for dashboards and controls.
- **Key Tasks**
  - Define routes and controllers (or API resources) for:
    - **KPIs & summary metrics**: overall queue status, mapped vs needs-mapping, modeled savings, provider hit counts.
    - **Research queue listing**: paginated list with filters (status, vendor, item search, priority).
    - **Price comparison view**: actions joined to best findings, including savings, price deltas, and provider info.
    - **Research evidence**: all findings for a given task or item.
    - **Vendor progress**: per-vendor stats, similar to the current dashboard.
    - **Mapping review queues**: items needing mapping or MPN-fill worklists.
    - **System health**: recent research run logs, FX snapshots, provider enablement status.
  - Implement endpoints to **trigger research runs**:
    - Start an API-only research batch.
    - Start a batch that allows Claude fallback, with configurable batch size.
    - Return a handle or status resource that the frontend can poll for progress/logs.
  - Add basic access control (e.g., API tokens or session-based auth) appropriate for internal tools.
- **Deliverables**
  - Versioned API endpoints documented (OpenAPI or simple markdown) for use by the React app.
  - Minimal API client code (e.g., in a shared JS SDK or documentation) describing payloads and filters.

---

## Phase 4 – React + Vite Dashboard

- **Goals**
  - Rebuild the Streamlit dashboard as a modern React single-page application using Vite, consuming the Laravel JSON API.
- **Key Tasks**
  - Scaffold a React + Vite project and integrate it with the Laravel backend (e.g., via API base URL and auth strategy).
  - Choose UI and charting libraries (e.g., a React component library plus Recharts/Chart.js).
  - Implement core views:
    - **Home / Overview**: KPIs, queue status chart, provider hits chart, last update time.
    - **Item Price Comparison**: filters for status, price result, minimum savings, vendor/item search; tabular view and CSV export.
    - **Research Evidence**: detail view for a selected item/task showing all provider findings.
    - **Vendor Progress**: per-vendor progress view.
    - **Mapping Review / MPN Worklist**: views equivalent to mapping-review and top-MPN worklists.
    - **Run Controls**: UI buttons/forms to trigger research runs (API-only or with Claude fallback) and show logs/status of the last run.
  - Implement reusable layout, theming, and navigation components.
  - Integrate data fetching (e.g., with Axios + React Query) and handle loading/error states.
- **Deliverables**
  - A working React + Vite dashboard with feature parity (or better) compared to the existing Streamlit UI.
  - Clear configuration for local development and production builds.

---

## Phase 5 – Validation, Migration, and Cutover

- **Goals**
  - Ensure the new Laravel + React + MySQL system matches or improves on the Python workflow’s behavior.
  - Plan a safe cutover from the old tooling to the new stack.
- **Key Tasks**
  - Run side-by-side comparisons for a representative sample of inventory:
    - Compare research queue composition.
    - Compare provider hits, accepted matches, and savings estimates.
  - Validate AI (Claude) results against expectations and business rules.
  - Collect feedback from stakeholders (e.g., procurement team) on the new dashboard and workflows.
  - Plan a cutover:
    - Freeze Python-based research for a short time window.
    - Run one or more full passes with the Laravel pipeline.
    - Switch users to the React dashboard for daily use.
- **Deliverables**
  - Validation report or notes documenting any differences and how they are handled.
  - Agreed cutover date and steps.

---

## Phase 6 – Hardening and Enhancements (Post-MVP)

- **Goals**
  - Improve robustness, observability, and usability beyond the initial rewrite.
- **Potential Enhancements**
  - Add audit logs for research decisions and supplier negotiations.
  - Implement more advanced scheduling (e.g., nightly auto-runs with configurable scopes).
  - Add role-based access control and SSO if needed.
  - Extend analytics (e.g., long-term savings tracking, trend analysis by supplier/commodity).
  - Add self-service configuration for thresholds (e.g., minimum savings %, match-score thresholds, strict vs relaxed mapping mode) via the UI.
- **Deliverables**
  - Prioritized backlog of improvements based on real-world usage.

