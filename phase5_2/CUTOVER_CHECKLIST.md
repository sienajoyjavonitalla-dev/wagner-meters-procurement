# Phase 5.2 Cutover Checklist

This checklist executes Phase 5.2.2: freeze Python runs, run Laravel import + research, and switch users to the React dashboard.

## 1) Pre-Cutover

- [ ] Confirm stakeholder sign-off is complete (`phase5_2/STAKEHOLDER_SIGNOFF.md`)
- [ ] Confirm latest validation report is reviewed (`phase5_1/PHASE_5_1_VALIDATION_REPORT.md`)
- [ ] Confirm production `.env` contains required provider/API keys (if provider calls are enabled)
- [ ] Confirm database backup completed (timestamp: ____________________)
- [ ] Confirm queue worker process is available for production run mode
- [ ] Confirm incident channel and owner on-call for cutover window

## 2) Freeze Legacy Python Runs

- [ ] Announce freeze start to users/stakeholders
- [ ] Disable/stop any scheduled Python research jobs
- [ ] Record the last legacy run ID/time: ____________________

## 3) Laravel Data Load + Pipeline

- [ ] Import production files:
  - `php artisan procurement:import-files <inventory.xlsx> <vendor_priority.csv> <item_spread.csv> --mpn-map=<mpn_map.csv>`
- [ ] Build queue:
  - `php artisan procurement:build-queue --vendors=20 --per-vendor=50 --spread=100`
- [ ] Run research:
  - Sync mode: `php artisan procurement:run-research --limit=500 --sync`
  - Or async mode: `php artisan procurement:run-research --limit=500` then run queue worker
- [ ] Verify status in UI/API:
  - `/dashboard`
  - `/dashboard/run-controls`
  - `/api/procurement/run-status?latest=1`

## 4) Verification (Go/No-Go)

- [ ] Queue totals and status distribution are plausible
- [ ] No critical API/controller/runtime errors in logs
- [ ] Dashboard pages render and load data without auth loops
- [ ] Run controls trigger/polling works
- [ ] Procurement owner confirms output usability

Go/No-Go decision: ____________________

## 5) User Switch

- [ ] Share new URL and login guidance with users
- [ ] Confirm old dashboard is marked read-only or hidden
- [ ] Confirm support contact for first 24h after cutover

## 6) Post-Cutover Monitoring (24-48h)

- [ ] Monitor failed jobs, API errors, and auth failures
- [ ] Verify at least one additional successful run
- [ ] Log issues and assign owners

## 7) Rollback Plan

Trigger rollback if any of these occur:

- Critical data integrity issue
- Sustained auth/API failures blocking operations
- Research output unusable for business workflow

Rollback actions:

- [ ] Re-enable legacy Python run process
- [ ] Notify users of temporary rollback
- [ ] Preserve Laravel logs and DB snapshot for incident review
- [ ] Open follow-up fix ticket with owner and ETA
