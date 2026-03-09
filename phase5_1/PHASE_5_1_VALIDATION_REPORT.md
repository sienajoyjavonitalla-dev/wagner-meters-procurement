# Phase 5.1 Validation Report

## Run inputs
- Dataset: `phase5_1/input/*` (generated synthetic sample)
- Python run: `scripts/research_loop.py` with strict mapping and no agent fallback
- Laravel run: `procurement:import-files` -> `procurement:build-queue` -> `procurement:run-research --no-claude --sync`

## Summary comparison

| Metric | Python | Laravel (latest batch) | Match |
|---|---:|---:|---|
| queue_total | 10 | 10 | yes |
| price_findings_total | 0 | 0 | yes |
| actions_total | 10 | 0 | no |
| modeled_savings_total | 0.0 | 0 | yes |

### Status counts
- Python: `{'needs_research': 6, 'skipped_non_catalog': 2, 'needs_mapping': 2}`
- Laravel: `{'needs_research': 6, 'skipped_non_catalog': 2, 'needs_mapping': 2}`

### Task type counts
- Python: `{'pricing_benchmark': 6, 'alternate_part': 4}`
- Laravel: `{'pricing_benchmark': 6, 'alternate_part': 4}`

## Queue row parity
- Python rows: 10
- Laravel rows (latest batch): 10
- Rows only in Python: 0
- Rows only in Laravel: 0

## Findings and fixes
- Fixed CLI import storage path mismatch in `ProcurementImportFiles` so copied files are readable by `ProcessImportJob`.
- Fixed non-catalog mapping handling in `ProcessImportJob::parseMpnMap` so rows with `lookup_mode=non_catalog` and blank `mpn` are imported as non-catalog.
- Residual difference: Python writes full `prioritized_research_actions.csv` rows even with no findings; Laravel currently only upserts `actions` when findings exist.

## Recommendation
- If parity with Python action row count is required, update `PostProcessResearchService` to create action rows for tasks without findings (with zero savings and empty best-finding fields).
