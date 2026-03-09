#!/usr/bin/env python3
from __future__ import annotations

import json
from pathlib import Path


def load_json(path: Path):
    return json.loads(path.read_text(encoding="utf-8"))


def norm_rows(rows):
    return [
        {
            "task_type": r.get("task_type"),
            "status": r.get("status"),
            "item": r.get("item"),
            "vendor": r.get("vendor"),
        }
        for r in rows
    ]


def main() -> None:
    root = Path(__file__).resolve().parents[1]
    phase_dir = root / "phase5_1"
    py_dir = phase_dir / "python_output"
    lv_dir = phase_dir / "laravel_output"

    py_summary = load_json(py_dir / "summary.json")
    lv_summary = load_json(lv_dir / "summary.json")
    py_rows = norm_rows(load_json(py_dir / "queue_rows.json"))
    lv_rows = norm_rows(load_json(lv_dir / "queue_rows.json"))

    py_set = {json.dumps(r, sort_keys=True) for r in py_rows}
    lv_set = {json.dumps(r, sort_keys=True) for r in lv_rows}
    only_py = [json.loads(s) for s in sorted(py_set - lv_set)]
    only_lv = [json.loads(s) for s in sorted(lv_set - py_set)]

    report = []
    report.append("# Phase 5.1 Validation Report")
    report.append("")
    report.append("## Run inputs")
    report.append("- Dataset: `phase5_1/input/*` (generated synthetic sample)")
    report.append("- Python run: `scripts/research_loop.py` with strict mapping and no agent fallback")
    report.append("- Laravel run: `procurement:import-files` -> `procurement:build-queue` -> `procurement:run-research --no-claude --sync`")
    report.append("")
    report.append("## Summary comparison")
    report.append("")
    report.append("| Metric | Python | Laravel (latest batch) | Match |")
    report.append("|---|---:|---:|---|")

    metrics = [
        "queue_total",
        "price_findings_total",
        "actions_total",
        "modeled_savings_total",
    ]
    for m in metrics:
        pv = py_summary.get(m)
        lv = lv_summary.get(m)
        report.append(f"| {m} | {pv} | {lv} | {'yes' if pv == lv else 'no'} |")

    report.append("")
    report.append("### Status counts")
    report.append(f"- Python: `{py_summary.get('status_counts', {})}`")
    report.append(f"- Laravel: `{lv_summary.get('status_counts', {})}`")
    report.append("")
    report.append("### Task type counts")
    report.append(f"- Python: `{py_summary.get('task_type_counts', {})}`")
    report.append(f"- Laravel: `{lv_summary.get('task_type_counts', {})}`")
    report.append("")

    report.append("## Queue row parity")
    report.append(f"- Python rows: {len(py_rows)}")
    report.append(f"- Laravel rows (latest batch): {len(lv_rows)}")
    report.append(f"- Rows only in Python: {len(only_py)}")
    report.append(f"- Rows only in Laravel: {len(only_lv)}")
    if only_py:
        report.append("")
        report.append("### Only in Python")
        for row in only_py[:20]:
            report.append(f"- {row}")
    if only_lv:
        report.append("")
        report.append("### Only in Laravel")
        for row in only_lv[:20]:
            report.append(f"- {row}")

    report.append("")
    report.append("## Findings and fixes")
    report.append("- Fixed CLI import storage path mismatch in `ProcurementImportFiles` so copied files are readable by `ProcessImportJob`.")
    report.append("- Fixed non-catalog mapping handling in `ProcessImportJob::parseMpnMap` so rows with `lookup_mode=non_catalog` and blank `mpn` are imported as non-catalog.")
    report.append("- Residual difference: Python writes full `prioritized_research_actions.csv` rows even with no findings; Laravel currently only upserts `actions` when findings exist.")
    report.append("")
    report.append("## Recommendation")
    report.append("- If parity with Python action row count is required, update `PostProcessResearchService` to create action rows for tasks without findings (with zero savings and empty best-finding fields).")

    out_path = phase_dir / "PHASE_5_1_VALIDATION_REPORT.md"
    out_path.write_text("\n".join(report) + "\n", encoding="utf-8")
    print(f"Wrote {out_path}")


if __name__ == "__main__":
    main()
