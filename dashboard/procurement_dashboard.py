#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import shlex
import subprocess
from typing import Any

import pandas as pd
import plotly.express as px
import streamlit as st


ROOT = Path(__file__).resolve().parents[1]
RESEARCH_DIR = ROOT / "output" / "research"

FILES = {
    "queue": RESEARCH_DIR / "research_queue.csv",
    "findings": RESEARCH_DIR / "price_findings.csv",
    "actions": RESEARCH_DIR / "prioritized_research_actions.csv",
    "part_master": RESEARCH_DIR / "part_master.csv",
    "mapping_review": RESEARCH_DIR / "mapping_review_queue.csv",
    "mpn_fill": RESEARCH_DIR / "top20_mpn_fill_needed.csv",
}


def exists(path: Path) -> bool:
    return path.exists() and path.is_file()


def fmt_currency(value: float | int | None) -> str:
    if value is None or pd.isna(value):
        return "$0"
    return f"${value:,.0f}"


@st.cache_data(ttl=30)
def load_csv(path: Path) -> pd.DataFrame:
    if not exists(path):
        return pd.DataFrame()
    return pd.read_csv(path)


def apply_theme() -> None:
    st.markdown(
        """
<style>
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=IBM+Plex+Mono:wght@400;500&display=swap');

:root {
  --bg-a: #f6f4ef;
  --bg-b: #e8ece6;
  --ink: #142025;
  --muted: #4f626b;
  --accent: #005f73;
  --accent-2: #ca6702;
}

.stApp {
  background: radial-gradient(circle at 15% -20%, #d6e6ec 0%, transparent 40%),
              linear-gradient(135deg, var(--bg-a), var(--bg-b));
}

html, body, [class*="css"] {
  font-family: "Space Grotesk", sans-serif;
  color: var(--ink);
}

[data-testid="stAppViewContainer"],
[data-testid="stMainBlockContainer"],
[data-testid="stMarkdownContainer"],
[data-testid="stMetricLabel"],
[data-testid="stMetricValue"],
[data-testid="stMetricDelta"] {
  color: var(--ink) !important;
}

label, p, span, div, h1, h2, h3, h4, h5, h6 {
  color: var(--ink);
}

[data-baseweb="select"] *,
[data-baseweb="base-input"] *,
.stNumberInput * {
  color: var(--ink) !important;
}

.stDataFrame, .stDataEditor {
  background: rgba(255,255,255,0.84);
  border-radius: 10px;
}

.dashboard-hero {
  background: linear-gradient(120deg, #0b3c49 0%, #005f73 55%, #0a9396 100%);
  color: #e9f3f5;
  padding: 1rem 1.2rem;
  border-radius: 14px;
  border: 1px solid rgba(255,255,255,0.2);
  margin-bottom: 0.9rem;
}

.dashboard-subtle {
  color: #d0e6eb;
  font-size: 0.95rem;
}

.mono {
  font-family: "IBM Plex Mono", monospace;
  color: var(--muted);
}

/* Hide Streamlit chrome for internal dashboard presentation */
[data-testid="stToolbar"] { display: none; }
[data-testid="stHeaderActionElements"] { display: none; }
footer { visibility: hidden; }
</style>
        """,
        unsafe_allow_html=True,
    )


def show_header(last_update: str, queue_rows: int) -> None:
    st.markdown(
        f"""
<div class="dashboard-hero">
  <h2 style="margin:0;">Procurement Research Dashboard</h2>
  <div class="dashboard-subtle">Run snapshot: {last_update} | queued tasks: {queue_rows}</div>
</div>
        """,
        unsafe_allow_html=True,
    )


def most_recent_mtime(paths: list[Path]) -> str:
    mtimes = [p.stat().st_mtime for p in paths if exists(p)]
    if not mtimes:
        return "No output files found"
    ts = pd.to_datetime(max(mtimes), unit="s")
    return ts.strftime("%Y-%m-%d %H:%M:%S")


def status_chart(queue: pd.DataFrame) -> Any:
    counts = queue["status"].value_counts().rename_axis("status").reset_index(name="count")
    if counts.empty:
        return None
    return px.pie(
        counts,
        names="status",
        values="count",
        color="status",
        hole=0.58,
        color_discrete_map={
            "researched": "#0a9396",
            "needs_research": "#ca6702",
            "skipped_non_catalog": "#6c757d",
        },
        title="Queue Status Mix",
    )


def provider_chart(findings: pd.DataFrame) -> Any:
    if findings.empty:
        return None
    by_provider = findings["provider"].value_counts().rename_axis("provider").reset_index(name="hits")
    return px.bar(
        by_provider,
        x="provider",
        y="hits",
        color="provider",
        title="Catalog/API Hits by Provider",
        color_discrete_sequence=["#005f73", "#0a9396", "#94d2bd", "#ee9b00"],
    )


def vendor_progress(queue: pd.DataFrame) -> pd.DataFrame:
    if queue.empty:
        return pd.DataFrame()
    pvt = (
        queue.pivot_table(
            index="Vendor Name",
            columns="status",
            values="task_id",
            aggfunc="count",
            fill_value=0,
        )
        .reset_index()
        .rename_axis(None, axis=1)
    )
    for col in ["researched", "needs_research", "skipped_non_catalog"]:
        if col not in pvt.columns:
            pvt[col] = 0
    pvt["total"] = pvt["researched"] + pvt["needs_research"] + pvt["skipped_non_catalog"]
    pvt["processed_pct"] = ((pvt["researched"] + pvt["skipped_non_catalog"]) / pvt["total"] * 100).round(1)
    return pvt.sort_values(["processed_pct", "total"], ascending=[False, False])


def run_research_loop(agent_fallback: str | None = None, batch_size: int = 0) -> tuple[bool, str]:
    base_cmd = "set -a; [ -f .env ] && source .env; set +a; "
    if agent_fallback:
        base_cmd += (
            f"AGENT_FALLBACK={shlex.quote(agent_fallback)} "
            f"AGENT_BATCH_SIZE={int(batch_size)} "
        )
    base_cmd += "./scripts/run_research_loop.sh"
    cmd = ["bash", "-lc", f"cd {shlex.quote(str(ROOT))} && {base_cmd}"]
    proc = subprocess.run(cmd, capture_output=True, text=True)
    output = (proc.stdout or "") + ("\n" + proc.stderr if proc.stderr else "")
    return proc.returncode == 0, output.strip()


def build_item_comparison(actions: pd.DataFrame, findings: pd.DataFrame) -> pd.DataFrame:
    if actions.empty:
        return pd.DataFrame()

    out = actions.copy()
    out["avg_unit_cost_12m"] = pd.to_numeric(out.get("avg_unit_cost_12m"), errors="coerce")
    out["qty_12m"] = pd.to_numeric(out.get("qty_12m"), errors="coerce")
    out["best_unit_price"] = pd.to_numeric(out.get("best_unit_price"), errors="coerce")
    out["estimated_savings"] = pd.to_numeric(out.get("estimated_savings"), errors="coerce").fillna(0)

    if findings.empty:
        findings_agg = pd.DataFrame(columns=["task_id", "research_hits", "providers", "queries", "source_url", "found_price_raw"])
    else:
        f = findings.copy()
        f["unit_price"] = pd.to_numeric(f.get("unit_price"), errors="coerce")
        findings_agg = (
            f.groupby("task_id", as_index=False)
            .agg(
                research_hits=("provider", "count"),
                providers=("provider", lambda s: ", ".join(sorted({str(v) for v in s if pd.notna(v)}))),
                queries=("query_mpn", lambda s: ", ".join(sorted({str(v) for v in s if pd.notna(v)})[:5])),
                source_url=("source_url", lambda s: next((str(v) for v in s if isinstance(v, str) and v.startswith("http")), "")),
                found_price_raw=("unit_price", "min"),
            )
        )

    out = out.merge(findings_agg, on="task_id", how="left")
    out["found_unit_price"] = out["best_unit_price"].where(out["best_unit_price"].notna(), out.get("found_price_raw"))
    out["paid_unit_price"] = out["avg_unit_cost_12m"]
    out["unit_price_delta"] = out["paid_unit_price"] - out["found_unit_price"]
    out["unit_price_delta_pct"] = (
        (out["unit_price_delta"] / out["paid_unit_price"]) * 100
    ).where(out["paid_unit_price"].notna() & (out["paid_unit_price"] != 0))
    calc_savings = (out["unit_price_delta"].clip(lower=0) * out["qty_12m"]).fillna(0)
    out["savings_potential"] = out["estimated_savings"].where(out["estimated_savings"] > 0, calc_savings)

    def price_result(row: pd.Series) -> str:
        status = str(row.get("status", ""))
        if status == "skipped_non_catalog":
            return "Skipped (Non-Catalog)"
        found = row.get("found_unit_price")
        paid = row.get("paid_unit_price")
        if pd.isna(found):
            return "No Price Found"
        if pd.isna(paid):
            return "Found (No Baseline)"
        delta = paid - found
        if delta > 1e-9:
            return "Lower Price Found"
        if abs(delta) <= 1e-9:
            return "Same Price"
        return "Higher Price Found"

    out["price_result"] = out.apply(price_result, axis=1)
    out["research_hits"] = pd.to_numeric(out.get("research_hits"), errors="coerce").fillna(0).astype(int)
    out["research_done"] = out.apply(
        lambda r: (
            "Yes"
            if str(r.get("status", "")) in {"researched", "skipped_non_catalog"} or int(r.get("research_hits", 0)) > 0
            else "No"
        ),
        axis=1,
    )

    return out


def run() -> None:
    st.set_page_config(page_title="Procurement Dashboard", page_icon=":bar_chart:", layout="wide")
    apply_theme()

    queue = load_csv(FILES["queue"])
    findings = load_csv(FILES["findings"])
    actions = load_csv(FILES["actions"])
    part_master = load_csv(FILES["part_master"])
    mapping_review = load_csv(FILES["mapping_review"])
    mpn_fill = load_csv(FILES["mpn_fill"])

    last_update = most_recent_mtime(list(FILES.values()))
    show_header(last_update, len(queue))

    if queue.empty and actions.empty:
        st.error("No research outputs found. Run `./scripts/run_research_loop.sh` first.")
        return

    if "last_run_output" not in st.session_state:
        st.session_state["last_run_output"] = ""
        st.session_state["last_run_ok"] = None

    with st.sidebar:
        st.subheader("Research Controls")
        if st.button("Run API Research Now", use_container_width=True, type="primary"):
            with st.spinner("Running API research loop..."):
                ok, output = run_research_loop()
            st.session_state["last_run_output"] = output
            st.session_state["last_run_ok"] = ok
            load_csv.clear()
            st.rerun()

        ai_choice = st.selectbox("AI Fallback Provider", options=["codex", "claude"])
        batch_size = st.slider("AI Fallback Batch Size", min_value=1, max_value=30, value=8, step=1)
        if st.button("Run API + AI Fallback", use_container_width=True):
            with st.spinner(f"Running API + {ai_choice} fallback..."):
                ok, output = run_research_loop(agent_fallback=ai_choice, batch_size=batch_size)
            st.session_state["last_run_output"] = output
            st.session_state["last_run_ok"] = ok
            load_csv.clear()
            st.rerun()

        st.caption("Fallback is applied only when API lookup fails, up to batch size.")

    if st.session_state["last_run_ok"] is not None:
        if st.session_state["last_run_ok"]:
            st.success("Last trigger completed.")
        else:
            st.error("Last trigger failed.")
        with st.expander("Last Run Output"):
            st.code(st.session_state["last_run_output"] or "(no output)", language="text")

    comparison = build_item_comparison(actions, findings)

    queue_total = len(queue)
    researched = int((queue["status"] == "researched").sum()) if not queue.empty else 0
    needs_research = int((queue["status"] == "needs_research").sum()) if not queue.empty else 0
    skipped_non_catalog = int((queue["status"] == "skipped_non_catalog").sum()) if not queue.empty else 0
    processed = researched + skipped_non_catalog
    processed_pct = round((processed / queue_total * 100), 1) if queue_total else 0.0

    hits = len(findings)
    unique_hit_tasks = findings["task_id"].nunique() if not findings.empty else 0
    modeled_savings = pd.to_numeric(comparison.get("savings_potential"), errors="coerce").fillna(0).sum() if not comparison.empty else 0
    lower_price_count = int((comparison.get("price_result") == "Lower Price Found").sum()) if not comparison.empty else 0

    mapped_items = int((part_master.get("mapping_status", pd.Series(dtype=str)) == "mapped").sum()) if not part_master.empty else 0
    needs_mapping_items = int((part_master.get("mapping_status", pd.Series(dtype=str)) == "needs_review").sum()) if not part_master.empty else 0

    k1, k2, k3, k4, k5, k6 = st.columns(6)
    k1.metric("Queue Processed", f"{processed}/{queue_total}", f"{processed_pct}%")
    k2.metric("Needs Research", f"{needs_research}")
    k3.metric("Catalog Hits", f"{hits}", f"{unique_hit_tasks} tasks")
    k4.metric("Modeled Savings", fmt_currency(modeled_savings))
    k5.metric("Lower Price Found", f"{lower_price_count}")
    k6.metric("Mapped Items", f"{mapped_items}", f"{needs_mapping_items} need mapping")

    left, right = st.columns([1.1, 1.0])
    with left:
        fig = status_chart(queue)
        if fig is not None:
            fig.update_layout(
                template="plotly_white",
                font=dict(color="#142025"),
                paper_bgcolor="rgba(255,255,255,0.78)",
                plot_bgcolor="rgba(255,255,255,0.78)",
                height=380,
                margin=dict(l=20, r=20, t=70, b=10),
            )
            st.plotly_chart(fig, use_container_width=True)
    with right:
        pfig = provider_chart(findings)
        if pfig is not None:
            pfig.update_layout(
                template="plotly_white",
                font=dict(color="#142025"),
                paper_bgcolor="rgba(255,255,255,0.78)",
                plot_bgcolor="rgba(255,255,255,0.78)",
                height=380,
                margin=dict(l=20, r=20, t=70, b=10),
                showlegend=False,
            )
            st.plotly_chart(pfig, use_container_width=True)
        else:
            st.info("No provider hits yet. Check API keys and MPN map coverage.")

    st.subheader("Item Price Comparison")
    c1, c2, c3, c4 = st.columns([1.2, 1.2, 1.0, 1.0])
    status_filter = c1.multiselect(
        "Status",
        options=sorted(comparison["status"].dropna().unique()) if "status" in comparison else [],
        default=sorted(comparison["status"].dropna().unique()) if "status" in comparison else [],
    )
    result_filter = c2.multiselect(
        "Price Result",
        options=sorted(comparison["price_result"].dropna().unique()) if "price_result" in comparison else [],
        default=sorted(comparison["price_result"].dropna().unique()) if "price_result" in comparison else [],
    )
    min_savings = c3.number_input("Min Savings Potential", value=0.0, min_value=0.0, step=100.0)
    vendor_query = c4.text_input("Vendor/Item Search", value="")

    filtered_actions = comparison.copy()
    if "status" in filtered_actions and status_filter:
        filtered_actions = filtered_actions[filtered_actions["status"].isin(status_filter)]
    if "price_result" in filtered_actions and result_filter:
        filtered_actions = filtered_actions[filtered_actions["price_result"].isin(result_filter)]
    filtered_actions["savings_potential"] = pd.to_numeric(filtered_actions.get("savings_potential"), errors="coerce").fillna(0)
    filtered_actions = filtered_actions[filtered_actions["savings_potential"] >= min_savings]
    if vendor_query.strip():
        q = vendor_query.strip().lower()
        filtered_actions = filtered_actions[
            filtered_actions["Vendor Name"].astype(str).str.lower().str.contains(q)
            | filtered_actions["Item ID"].astype(str).str.lower().str.contains(q)
            | filtered_actions["description"].astype(str).str.lower().str.contains(q)
        ]
    filtered_actions = filtered_actions.sort_values(["savings_potential", "priority_score"], ascending=False)

    action_cols = [
        "task_id",
        "Vendor Name",
        "Item ID",
        "description",
        "status",
        "research_done",
        "price_result",
        "paid_unit_price",
        "found_unit_price",
        "unit_price_delta",
        "unit_price_delta_pct",
        "savings_potential",
        "providers",
        "queries",
        "source_url",
        "priority_score",
        "research_note",
    ]
    action_cols = [c for c in action_cols if c in filtered_actions.columns]
    st.dataframe(
        filtered_actions[action_cols],
        use_container_width=True,
        height=370,
        column_config={
            "paid_unit_price": st.column_config.NumberColumn("Paid Unit Price", format="$%.4f"),
            "found_unit_price": st.column_config.NumberColumn("Found Unit Price", format="$%.4f"),
            "unit_price_delta": st.column_config.NumberColumn("Unit Price Delta", format="$%.4f"),
            "unit_price_delta_pct": st.column_config.NumberColumn("Delta %", format="%.2f%%"),
            "savings_potential": st.column_config.NumberColumn("Savings Potential", format="$%.2f"),
            "source_url": st.column_config.LinkColumn("Source", display_text="open"),
        },
    )
    st.download_button(
        "Download Item Comparison CSV",
        filtered_actions.to_csv(index=False).encode("utf-8"),
        file_name="item_price_comparison.csv",
        mime="text/csv",
    )

    st.subheader("Research Evidence")
    task_options = filtered_actions["task_id"].dropna().astype(str).tolist()
    selected_task = None
    if task_options:
        selected_task = st.selectbox("Inspect Task", options=task_options, index=0)
    else:
        st.info("No tasks in current filter view.")
    if selected_task:
        task_row = filtered_actions[filtered_actions["task_id"].astype(str) == str(selected_task)]
        if not task_row.empty:
            st.dataframe(task_row[action_cols], use_container_width=True, height=160)
        task_findings = findings[findings["task_id"].astype(str) == str(selected_task)].copy() if not findings.empty else pd.DataFrame()
        if task_findings.empty:
            st.info("No findings yet for this task. Trigger API run or API+AI fallback.")
        else:
            st.dataframe(
                task_findings,
                use_container_width=True,
                height=190,
                column_config={"source_url": st.column_config.LinkColumn("Source", display_text="open")},
            )

    st.subheader("Vendor Progress")
    vp = vendor_progress(queue)
    if not vp.empty:
        st.dataframe(vp[["Vendor Name", "researched", "needs_research", "skipped_non_catalog", "processed_pct", "total"]], use_container_width=True, height=260)
    else:
        st.info("No queue data available.")

    st.subheader("Top-20 MPN Worklist")
    if not mpn_fill.empty:
        st.dataframe(mpn_fill, use_container_width=True, height=220)
        st.download_button(
            "Download MPN Fill Worklist",
            mpn_fill.to_csv(index=False).encode("utf-8"),
            file_name="top20_mpn_fill_needed.csv",
            mime="text/csv",
        )
    else:
        st.success("No open MPN fill items in top 20.")

    st.subheader("Mapping Review Queue")
    if not mapping_review.empty:
        show_cols = [
            "internal_item_id",
            "item_description",
            "mapped_mpn",
            "mapping_source",
            "match_confidence",
            "spend_12m",
            "queue_tasks",
        ]
        show_cols = [c for c in show_cols if c in mapping_review.columns]
        st.dataframe(mapping_review[show_cols], use_container_width=True, height=260)
        st.download_button(
            "Download Mapping Review Queue",
            mapping_review.to_csv(index=False).encode("utf-8"),
            file_name="mapping_review_queue.csv",
            mime="text/csv",
        )
    else:
        st.success("No items currently waiting on mapping review.")

    with st.expander("Raw Files and Health"):
        for name, path in FILES.items():
            marker = "OK" if exists(path) else "MISSING"
            st.write(f"- `{name}`: {marker} - `{path}`")
        st.markdown('<div class="mono">Tip: rerun `./scripts/run_research_loop.sh` after updating `mpn_map.csv`.</div>', unsafe_allow_html=True)


if __name__ == "__main__":
    run()
