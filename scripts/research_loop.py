#!/usr/bin/env python3
"""
Automates pricing and alternate-part research for procurement.

Workflow:
1) Build a research queue from vendor/item priority outputs.
2) Run API-first research (DigiKey, Mouser, Nexar when keys are present).
3) Optionally run an agent fallback (codex/claude CLI) for unresolved tasks.
4) Emit CSV outputs for review and negotiation actioning.
"""

from __future__ import annotations

import argparse
import difflib
import json
import os
import re
import subprocess
import sys
import tempfile
import textwrap
from dataclasses import dataclass, asdict
from pathlib import Path
from typing import Any

import pandas as pd
import requests


NONCATALOG_MARKERS = {"NONCATALOG", "NON_CATALOG", "NON-CATALOG", "CUSTOM", "INTERNAL_ONLY"}
GENERIC_MPN_PATTERNS = [
    r"^\d+(\.\d+)?MM$",
    r"^\d+AWG$",
    r"^\d+POS$",
    r"^\d+(\.\d+)?W$",
    r"^\d+KB$",
    r"^\d+MB$",
    r"^SOT\d+",
    r"^QFP\d*",
    r"^TQFP\d*",
    r"^VSON\d*",
]


def normalize_part(value: str | None) -> str:
    if not value:
        return ""
    return re.sub(r"[^A-Z0-9]+", "", str(value).upper())


def is_likely_mpn(value: str | None) -> bool:
    if not value:
        return False
    raw = str(value).strip().upper()
    if not raw:
        return False
    if raw in NONCATALOG_MARKERS:
        return False
    norm = normalize_part(raw)
    if len(norm) < 5:
        return False
    if not re.search(r"[A-Z]", norm) or not re.search(r"\d", norm):
        return False
    for pat in GENERIC_MPN_PATTERNS:
        if re.match(pat, raw):
            return False
    return True


def match_score(target_mpn: str, matched_part: str) -> float:
    target = normalize_part(target_mpn)
    matched = normalize_part(matched_part)
    if not target or not matched:
        return 0.0
    if target == matched:
        return 1.0
    if len(target) >= 8 and (target in matched or matched in target):
        return 0.94
    return difflib.SequenceMatcher(None, target, matched).ratio()


def to_float(value: Any) -> float | None:
    if value is None:
        return None
    if isinstance(value, (float, int)):
        return float(value)
    if isinstance(value, str):
        cleaned = value.replace("$", "").replace(",", "").strip()
        if not cleaned:
            return None
        try:
            return float(cleaned)
        except ValueError:
            return None
    return None


def parse_price_breaks(price_breaks: list[dict[str, Any]] | None) -> float | None:
    if not price_breaks:
        return None
    prices: list[float] = []
    for row in price_breaks:
        if not isinstance(row, dict):
            continue
        value = to_float(row.get("Price") or row.get("price"))
        if value is not None:
            prices.append(value)
    return min(prices) if prices else None


def parse_digikey_product_price(product: dict[str, Any]) -> float | None:
    # Prefer explicit top-level unit price when present.
    unit = to_float(product.get("UnitPrice"))
    candidates: list[float] = [unit] if unit is not None else []

    # Fall back to variation-level standard pricing.
    variations = product.get("ProductVariations")
    if isinstance(variations, list):
        for var in variations:
            if not isinstance(var, dict):
                continue
            price = parse_price_breaks(var.get("StandardPricing"))
            if price is not None:
                candidates.append(price)
    return min(candidates) if candidates else None


def json_from_text(raw: str) -> dict[str, Any] | None:
    raw = raw.strip()
    if not raw:
        return None
    try:
        parsed = json.loads(raw)
        return parsed if isinstance(parsed, dict) else None
    except json.JSONDecodeError:
        pass

    match = re.search(r"\{.*\}", raw, re.S)
    if not match:
        return None
    try:
        parsed = json.loads(match.group(0))
        return parsed if isinstance(parsed, dict) else None
    except json.JSONDecodeError:
        return None


def extract_candidate_mpns(item_id: str, description: str, max_candidates: int = 5) -> list[str]:
    text = f"{item_id or ''} {description or ''}".upper()
    text = text.replace(",", " ").replace("(", " ").replace(")", " ")
    tokens = re.findall(r"\b[A-Z0-9][A-Z0-9\-_/\.]{3,}\b", text)
    drop = {
        "MODEL",
        "REV",
        "BOARD",
        "PCA",
        "PCBA",
        "SENSOR",
        "ASSY",
        "UNTESTED",
        "MANUAL",
        "INSTRUCTION",
        "OUTER",
        "INNER",
        "LABOR",
    }
    results: list[str] = []
    for token in tokens:
        if token in drop:
            continue
        if len(token) < 5:
            continue
        has_alpha = bool(re.search(r"[A-Z]", token))
        has_digit = bool(re.search(r"\d", token))
        if not (has_alpha and has_digit):
            continue
        if token not in results:
            results.append(token)
        if len(results) >= max_candidates:
            break
    if item_id and item_id.upper() not in results:
        results.append(item_id.upper())
    return results[:max_candidates]


@dataclass
class PriceFinding:
    task_id: str
    provider: str
    query_mpn: str
    matched_part: str
    manufacturer: str
    unit_price: float | None
    currency: str | None
    source_url: str | None
    note: str | None
    target_mpn: str | None = None
    match_score: float = 0.0
    accepted_match: bool = False


class DigiKeyClient:
    def __init__(self) -> None:
        self.client_id = os.getenv("DIGIKEY_CLIENT_ID")
        self.client_secret = os.getenv("DIGIKEY_CLIENT_SECRET")
        self.token_url = os.getenv("DIGIKEY_TOKEN_URL", "https://api.digikey.com/v1/oauth2/token")
        self.base_url = os.getenv(
            "DIGIKEY_PRODUCT_URL",
            "https://api.digikey.com/products/v4/search/{part_number}/productdetails",
        )
        self.locale_site = os.getenv("DIGIKEY_LOCALE_SITE", "US")
        self.locale_language = os.getenv("DIGIKEY_LOCALE_LANGUAGE", "en")
        self.locale_currency = os.getenv("DIGIKEY_LOCALE_CURRENCY", "USD")
        self.account_id = os.getenv("DIGIKEY_ACCOUNT_ID", "0")
        self.token: str | None = None
        self.enabled = bool(self.client_id and self.client_secret)

    def _auth(self) -> str | None:
        if self.token:
            return self.token
        if not self.enabled:
            return None
        data = {
            "client_id": self.client_id,
            "client_secret": self.client_secret,
            "grant_type": "client_credentials",
        }
        try:
            resp = requests.post(self.token_url, data=data, timeout=20)
            resp.raise_for_status()
            token = resp.json().get("access_token")
            if token:
                self.token = token
            return self.token
        except requests.RequestException:
            return None

    def lookup(self, task_id: str, query_mpn: str) -> list[PriceFinding]:
        token = self._auth()
        if not token:
            return []
        url = self.base_url.format(part_number=requests.utils.quote(query_mpn, safe=""))
        headers = {
            "Authorization": f"Bearer {token}",
            "X-DIGIKEY-Client-Id": self.client_id or "",
            "accept": "application/json",
            "X-DIGIKEY-Locale-Site": self.locale_site,
            "X-DIGIKEY-Locale-Language": self.locale_language,
            "X-DIGIKEY-Locale-Currency": self.locale_currency,
            "X-DIGIKEY-Account-Id": self.account_id,
        }
        try:
            resp = requests.get(url, headers=headers, timeout=20)
            if resp.status_code >= 400:
                return []
            payload = resp.json()
        except requests.RequestException:
            return []
        except ValueError:
            return []

        product: dict[str, Any] = {}
        if isinstance(payload, dict) and isinstance(payload.get("Product"), dict):
            product = payload["Product"]

        price = parse_digikey_product_price(product) if product else None
        manufacturer = ""
        matched_part = ""
        source_url: str | None = url
        if product:
            matched_part = str(
                product.get("ManufacturerProductNumber")
                or product.get("ManufacturerPartNumber")
                or product.get("DigiKeyProductNumber")
                or ""
            )
            man = product.get("Manufacturer")
            if isinstance(man, dict):
                manufacturer = str(man.get("Name") or "")
            source_url = str(product.get("ProductUrl") or url)
        finding = PriceFinding(
            task_id=task_id,
            provider="digikey",
            query_mpn=query_mpn,
            matched_part=matched_part,
            manufacturer=manufacturer,
            unit_price=price,
            currency="USD" if price is not None else None,
            source_url=source_url,
            note=None if price is not None else "No price break data returned",
        )
        return [finding]


class MouserClient:
    def __init__(self) -> None:
        self.api_key = os.getenv("MOUSER_API_KEY") or os.getenv("MOUSER_SEARCH_API_KEY")
        self.search_url = os.getenv(
            "MOUSER_PART_SEARCH_URL",
            "https://api.mouser.com/api/v1.0/search/partnumber",
        )
        self.enabled = bool(self.api_key)

    def lookup(self, task_id: str, query_mpn: str) -> list[PriceFinding]:
        if not self.enabled:
            return []
        url = f"{self.search_url}?apiKey={self.api_key}"
        body = {"SearchByPartRequest": {"mouserPartNumber": query_mpn, "partSearchOptions": "None"}}
        try:
            resp = requests.post(url, json=body, timeout=20)
            if resp.status_code >= 400:
                return []
            payload = resp.json()
        except requests.RequestException:
            return []
        except ValueError:
            return []

        parts = payload.get("SearchResults", {}).get("Parts", [])
        findings: list[PriceFinding] = []
        for part in parts[:3]:
            if not isinstance(part, dict):
                continue
            price = parse_price_breaks(part.get("PriceBreaks"))
            findings.append(
                PriceFinding(
                    task_id=task_id,
                    provider="mouser",
                    query_mpn=query_mpn,
                    matched_part=str(part.get("ManufacturerPartNumber") or part.get("MouserPartNumber") or ""),
                    manufacturer=str(part.get("Manufacturer") or ""),
                    unit_price=price,
                    currency="USD" if price is not None else None,
                    source_url=str(part.get("ProductDetailUrl") or ""),
                    note=None,
                )
            )
        return findings


class NexarClient:
    def __init__(self) -> None:
        self.client_id = os.getenv("NEXAR_CLIENT_ID")
        self.client_secret = os.getenv("NEXAR_CLIENT_SECRET")
        self.token_url = os.getenv("NEXAR_TOKEN_URL", "https://identity.nexar.com/connect/token")
        self.graphql_url = os.getenv("NEXAR_GRAPHQL_URL", "https://api.nexar.com/graphql")
        self.token: str | None = None
        self.enabled = bool(self.client_id and self.client_secret)

    def _auth(self) -> str | None:
        if self.token:
            return self.token
        if not self.enabled:
            return None
        data = {
            "grant_type": "client_credentials",
            "client_id": self.client_id,
            "client_secret": self.client_secret,
        }
        try:
            resp = requests.post(self.token_url, data=data, timeout=20)
            resp.raise_for_status()
            token = resp.json().get("access_token")
            if token:
                self.token = token
            return self.token
        except requests.RequestException:
            return None

    def lookup(self, task_id: str, query_mpn: str) -> list[PriceFinding]:
        token = self._auth()
        if not token:
            return []

        query = textwrap.dedent(
            """
            query ($q: String!) {
              supSearchMpn(q: $q, limit: 3) {
                results {
                  part { mpn shortDescription manufacturer { name } }
                  sellers { company { name } offers { prices { quantity price currency } } }
                }
              }
            }
            """
        ).strip()
        headers = {"Authorization": f"Bearer {token}", "Content-Type": "application/json"}
        payload = {"query": query, "variables": {"q": query_mpn}}
        try:
            resp = requests.post(self.graphql_url, headers=headers, json=payload, timeout=20)
            if resp.status_code >= 400:
                return []
            data = resp.json()
        except requests.RequestException:
            return []
        except ValueError:
            return []

        results = data.get("data", {}).get("supSearchMpn", {}).get("results", [])
        findings: list[PriceFinding] = []
        for row in results:
            if not isinstance(row, dict):
                continue
            part = row.get("part", {}) if isinstance(row.get("part"), dict) else {}
            manufacturer = ""
            man = part.get("manufacturer")
            if isinstance(man, dict):
                manufacturer = str(man.get("name") or "")
            best_price = None
            currency = None
            for seller in row.get("sellers", []) or []:
                offers = seller.get("offers", []) if isinstance(seller, dict) else []
                for offer in offers:
                    prices = offer.get("prices", []) if isinstance(offer, dict) else []
                    for p in prices:
                        value = to_float((p or {}).get("price"))
                        if value is None:
                            continue
                        if best_price is None or value < best_price:
                            best_price = value
                            currency = str((p or {}).get("currency") or "")
            findings.append(
                PriceFinding(
                    task_id=task_id,
                    provider="nexar",
                    query_mpn=query_mpn,
                    matched_part=str(part.get("mpn") or ""),
                    manufacturer=manufacturer,
                    unit_price=best_price,
                    currency=currency or None,
                    source_url="https://api.nexar.com/graphql",
                    note=None,
                )
            )
        return findings


def fx_snapshot() -> dict[str, float]:
    url = "https://open.er-api.com/v6/latest/USD"
    try:
        resp = requests.get(url, timeout=20)
        resp.raise_for_status()
        data = resp.json()
        rates = data.get("rates", {})
        if isinstance(rates, dict):
            return {k: float(v) for k, v in rates.items() if isinstance(v, (int, float))}
    except (requests.RequestException, ValueError):
        return {}
    return {}


def run_agent_research(
    agent: str,
    cwd: Path,
    task_id: str,
    vendor: str,
    item_id: str,
    description: str,
    query_mpn: str,
) -> PriceFinding | None:
    prompt = textwrap.dedent(
        f"""
        Research distributor pricing and alternates for this item and return only JSON.
        Context:
        - vendor: {vendor}
        - internal item_id: {item_id}
        - description: {description}
        - candidate part number: {query_mpn}

        Required JSON shape:
        {{
          "matched_part": "string",
          "manufacturer": "string",
          "low_unit_price": 0.0,
          "currency": "USD",
          "source_url": "https://...",
          "note": "short text"
        }}
        """
    ).strip()

    schema = {
        "type": "object",
        "properties": {
            "matched_part": {"type": "string"},
            "manufacturer": {"type": "string"},
            "low_unit_price": {"type": ["number", "null"]},
            "currency": {"type": "string"},
            "source_url": {"type": "string"},
            "note": {"type": "string"},
        },
        "required": ["matched_part", "manufacturer", "low_unit_price", "currency", "source_url", "note"],
    }

    schema_path: str | None = None
    try:
        if agent == "codex":
            with tempfile.NamedTemporaryFile(mode="w", suffix=".json", delete=False) as handle:
                json.dump(schema, handle)
                schema_path = handle.name
            cmd = ["codex", "--search", "exec", "-s", "read-only", "--output-schema", schema_path, "-C", str(cwd), prompt]
            raw = subprocess.run(cmd, check=True, capture_output=True, text=True, timeout=180).stdout
        elif agent == "claude":
            cmd = ["claude", "-p", "--output-format", "json", "--json-schema", json.dumps(schema), prompt]
            raw = subprocess.run(cmd, check=True, capture_output=True, text=True, timeout=180).stdout
        else:
            return None
    except (subprocess.SubprocessError, FileNotFoundError):
        return None
    finally:
        if schema_path and os.path.exists(schema_path):
            try:
                os.unlink(schema_path)
            except OSError:
                pass

    parsed = json_from_text(raw)
    if not parsed:
        return None
    price = to_float(parsed.get("low_unit_price"))
    return PriceFinding(
        task_id=task_id,
        provider=f"agent:{agent}",
        query_mpn=query_mpn,
        matched_part=str(parsed.get("matched_part") or ""),
        manufacturer=str(parsed.get("manufacturer") or ""),
        unit_price=price,
        currency=str(parsed.get("currency") or "") or None,
        source_url=str(parsed.get("source_url") or "") or None,
        note=str(parsed.get("note") or "") or None,
    )


def build_queue(
    inventory: pd.DataFrame,
    vendor_priority: pd.DataFrame,
    item_opps: pd.DataFrame,
    mapping_index: dict[str, dict[str, Any]],
    strict_mapping: bool,
    top_vendors: int,
    items_per_vendor: int,
    top_spread_items: int,
) -> pd.DataFrame:
    inv = inventory.copy()
    inv["Transaction Date"] = pd.to_datetime(inv["Transaction Date"], errors="coerce")
    for c in ["Ext. Cost", "Unit Cost", "Quantity"]:
        inv[c] = pd.to_numeric(inv[c], errors="coerce")
    inv = inv.dropna(subset=["Transaction Date", "Vendor Name", "Item ID", "Ext. Cost", "Unit Cost"])
    inv = inv[inv["Ext. Cost"] > 0]
    max_date = inv["Transaction Date"].max()
    inv["days_ago"] = (max_date - inv["Transaction Date"]).dt.days
    recent = inv[inv["days_ago"] <= 365]

    vp = vendor_priority.sort_values("priority_rank").head(top_vendors).copy()
    wanted_vendors = set(vp["Vendor Name"].astype(str).tolist())

    base = (
        recent[recent["Vendor Name"].isin(wanted_vendors)]
        .groupby(["Vendor Name", "Item ID"], as_index=False)
        .agg(
            description=("Description", "last"),
            spend_12m=("Ext. Cost", "sum"),
            qty_12m=("Quantity", "sum"),
            avg_unit_cost_12m=("Unit Cost", "mean"),
        )
        .sort_values(["Vendor Name", "spend_12m"], ascending=[True, False])
    )
    base = base.groupby("Vendor Name").head(items_per_vendor).reset_index(drop=True)
    base["task_type"] = "pricing_benchmark"

    spread = item_opps.head(top_spread_items).copy()
    alt_rows = (
        inv[inv["Item ID"].astype(str).isin(spread["Item ID"].astype(str))]
        .sort_values("Transaction Date")
        .groupby("Item ID", as_index=False)
        .tail(1)[["Vendor Name", "Item ID", "Description"]]
        .rename(columns={"Description": "description"})
    )
    alt_rows["spend_12m"] = 0.0
    alt_rows["qty_12m"] = 0.0
    alt_rows["avg_unit_cost_12m"] = 0.0
    alt_rows["task_type"] = "alternate_part"

    queue = pd.concat(
        [
            base[["Vendor Name", "Item ID", "description", "spend_12m", "qty_12m", "avg_unit_cost_12m", "task_type"]],
            alt_rows[["Vendor Name", "Item ID", "description", "spend_12m", "qty_12m", "avg_unit_cost_12m", "task_type"]],
        ],
        ignore_index=True,
    )
    queue = queue.drop_duplicates(subset=["Vendor Name", "Item ID", "task_type"])
    # Prioritize higher-spend lines so optional agent fallback works highest-value tasks first.
    queue = queue.sort_values(["spend_12m", "task_type", "Vendor Name", "Item ID"], ascending=[False, True, True, True]).reset_index(drop=True)
    queue.insert(0, "task_id", [f"T{idx:04d}" for idx in range(1, len(queue) + 1)])

    def mapping_for_item(item_id: str) -> dict[str, Any]:
        return mapping_index.get(
            item_id,
            {
                "mapping_status": "needs_review",
                "mapped_mpn": "",
                "mapped_mfr": "",
                "mapping_source": "unmapped",
                "match_confidence": 0.0,
                "notes": "No mapping row found in mpn_map.csv",
            },
        )

    def candidates_for_row(row: pd.Series) -> list[str]:
        item_id = str(row["Item ID"])
        mapping = mapping_for_item(item_id)
        status = str(mapping.get("mapping_status", "needs_review"))
        mapped_mpn = str(mapping.get("mapped_mpn", "") or "").strip()
        if status == "non_catalog":
            return []
        if status == "mapped" and mapped_mpn:
            return [mapped_mpn]
        if strict_mapping:
            return []
        inferred = extract_candidate_mpns(item_id, str(row["description"] or ""))
        mapped = [mapped_mpn] if mapped_mpn else []
        merged: list[str] = []
        for token in mapped + inferred:
            t = (token or "").strip()
            if t and t not in merged:
                merged.append(t)
        return merged[:7]

    queue["candidate_mpns"] = queue.apply(candidates_for_row, axis=1)
    queue["mapping_status"] = queue["Item ID"].astype(str).map(lambda item_id: mapping_for_item(item_id).get("mapping_status", "needs_review"))
    queue["mapped_mpn"] = queue["Item ID"].astype(str).map(lambda item_id: mapping_for_item(item_id).get("mapped_mpn", ""))
    queue["mapped_mfr"] = queue["Item ID"].astype(str).map(lambda item_id: mapping_for_item(item_id).get("mapped_mfr", ""))
    queue["mapping_source"] = queue["Item ID"].astype(str).map(lambda item_id: mapping_for_item(item_id).get("mapping_source", ""))
    queue["match_confidence"] = queue["Item ID"].astype(str).map(lambda item_id: mapping_for_item(item_id).get("match_confidence", 0.0))
    queue["mapping_note"] = queue["Item ID"].astype(str).map(lambda item_id: mapping_for_item(item_id).get("notes", ""))
    queue["lookup_mode"] = queue["Item ID"].astype(str).apply(
        lambda item_id: "non_catalog"
        if str(mapping_for_item(str(item_id)).get("mapping_status", "needs_review")) == "non_catalog"
        else "catalog_lookup"
    )
    queue["status"] = "pending"
    queue["research_note"] = ""
    return queue


def build_mapping_index(path: Path) -> dict[str, dict[str, Any]]:
    if not path.exists():
        return {}
    try:
        df = pd.read_csv(path)
    except Exception:
        return {}

    cols = {c.lower(): c for c in df.columns}
    item_col = cols.get("item id") or cols.get("item_id")
    mpn_col = cols.get("mpn") or cols.get("manufacturer part number") or cols.get("manufacturer_part_number")
    if not item_col or not mpn_col:
        return {}

    mode_col = cols.get("lookup_mode")
    notes_col = cols.get("notes")
    desc_col = cols.get("description")
    mfr_col = cols.get("mfr_name") or cols.get("manufacturer") or cols.get("mapped_mfr")

    def clean_text(value: Any) -> str:
        if value is None:
            return ""
        s = str(value).strip()
        return "" if s.lower() == "nan" else s

    mapping_index: dict[str, dict[str, Any]] = {}
    for _, row in df.iterrows():
        item_id = clean_text(row.get(item_col, ""))
        mpn_val = clean_text(row.get(mpn_col, ""))
        if not item_id:
            continue

        notes = clean_text(row.get(notes_col, "")) if notes_col else ""
        mapped_mfr = clean_text(row.get(mfr_col, "")) if mfr_col else ""
        description = clean_text(row.get(desc_col, "")) if desc_col else ""
        lookup_mode = clean_text(row.get(mode_col, "")).lower() if mode_col else ""
        if lookup_mode in {"non_catalog", "noncatalog", "custom"}:
            mapping_index[item_id] = {
                "item_id": item_id,
                "description": description,
                "mapping_status": "non_catalog",
                "mapped_mpn": "",
                "mapped_mfr": mapped_mfr,
                "mapping_source": notes or "lookup_mode",
                "match_confidence": 1.0,
                "notes": notes or "Marked non-catalog via lookup_mode",
            }
            continue

        if not mpn_val or mpn_val.lower() == "nan":
            mapping_index[item_id] = {
                "item_id": item_id,
                "description": description,
                "mapping_status": "needs_review",
                "mapped_mpn": "",
                "mapped_mfr": mapped_mfr,
                "mapping_source": notes or "missing_mpn",
                "match_confidence": 0.0,
                "notes": notes or "No mapped MPN",
            }
            continue

        mpn_first = [s.strip() for s in re.split(r"[|;,]", mpn_val) if s.strip()]
        mapped_mpn = mpn_first[0] if mpn_first else ""
        if mapped_mpn.strip().upper() in NONCATALOG_MARKERS:
            mapping_index[item_id] = {
                "item_id": item_id,
                "description": description,
                "mapping_status": "non_catalog",
                "mapped_mpn": "",
                "mapped_mfr": mapped_mfr,
                "mapping_source": notes or "noncatalog_marker",
                "match_confidence": 1.0,
                "notes": notes or "Marked non-catalog via marker",
            }
            continue

        if is_likely_mpn(mapped_mpn) or "curated_high_confidence" in notes:
            if "curated_high_confidence" in notes or "verified" in notes.lower() or "erp" in notes.lower():
                status = "mapped"
                confidence = 1.0
            elif "auto_suggest" in notes:
                status = "needs_review"
                confidence = 0.55
            else:
                status = "mapped"
                confidence = 0.8
            mapping_index[item_id] = {
                "item_id": item_id,
                "description": description,
                "mapping_status": status,
                "mapped_mpn": mapped_mpn,
                "mapped_mfr": mapped_mfr,
                "mapping_source": notes or "mpn_map",
                "match_confidence": confidence,
                "notes": notes,
            }
        else:
            mapping_index[item_id] = {
                "item_id": item_id,
                "description": description,
                "mapping_status": "needs_review",
                "mapped_mpn": mapped_mpn,
                "mapped_mfr": mapped_mfr,
                "mapping_source": notes or "mpn_map",
                "match_confidence": 0.35,
                "notes": notes or "MPN looks generic; needs manual review",
            }
    return mapping_index


def write_part_master_outputs(queue: pd.DataFrame, outdir: Path) -> None:
    if queue.empty:
        pd.DataFrame().to_csv(outdir / "part_master.csv", index=False)
        pd.DataFrame().to_csv(outdir / "mapping_review_queue.csv", index=False)
        return

    item_view = (
        queue[
            [
                "Item ID",
                "description",
                "mapping_status",
                "mapped_mpn",
                "mapped_mfr",
                "mapping_source",
                "match_confidence",
                "mapping_note",
                "lookup_mode",
            ]
        ]
        .drop_duplicates(subset=["Item ID"])
        .rename(
            columns={
                "Item ID": "internal_item_id",
                "description": "item_description",
            }
        )
    )

    item_queue_stats = (
        queue.groupby("Item ID", as_index=False)
        .agg(
            queue_tasks=("task_id", "count"),
            researched_tasks=("status", lambda s: int((s == "researched").sum())),
            needs_research_tasks=("status", lambda s: int((s == "needs_research").sum())),
            spend_12m=("spend_12m", "sum"),
        )
        .rename(columns={"Item ID": "internal_item_id"})
    )

    part_master = item_view.merge(item_queue_stats, on="internal_item_id", how="left").sort_values(
        ["mapping_status", "spend_12m"], ascending=[True, False]
    )
    part_master.to_csv(outdir / "part_master.csv", index=False)

    mapping_review = part_master[
        (part_master["mapping_status"] == "needs_review")
        & (part_master["lookup_mode"] != "non_catalog")
    ].copy()
    mapping_review.to_csv(outdir / "mapping_review_queue.csv", index=False)


def run(args: argparse.Namespace) -> int:
    inventory_path = Path(args.inventory).resolve()
    vendor_priority_path = Path(args.vendor_priority).resolve()
    item_spread_path = Path(args.item_spread).resolve()
    outdir = Path(args.outdir).resolve()
    outdir.mkdir(parents=True, exist_ok=True)

    inventory = pd.read_excel(inventory_path)
    vendor_priority = pd.read_csv(vendor_priority_path)
    item_spread = pd.read_csv(item_spread_path)

    mapping_index = build_mapping_index(Path(args.mpn_map).resolve())

    queue = build_queue(
        inventory=inventory,
        vendor_priority=vendor_priority,
        item_opps=item_spread,
        mapping_index=mapping_index,
        strict_mapping=args.strict_mapping,
        top_vendors=args.top_vendors,
        items_per_vendor=args.items_per_vendor,
        top_spread_items=args.top_spread_items,
    )

    digikey = DigiKeyClient()
    mouser = MouserClient()
    nexar = NexarClient()
    providers = [digikey, mouser, nexar]
    enabled_provider_names = [p.__class__.__name__.replace("Client", "").lower() for p in providers if getattr(p, "enabled", False)]
    findings: list[PriceFinding] = []

    agent_count = 0
    for idx, row in queue.iterrows():
        task_id = str(row["task_id"])
        vendor = str(row["Vendor Name"])
        item_id = str(row["Item ID"])
        description = str(row["description"] or "")
        candidate_mpns = row["candidate_mpns"] if isinstance(row["candidate_mpns"], list) else []
        lookup_mode = str(row.get("lookup_mode", "catalog_lookup"))
        mapping_status = str(row.get("mapping_status", "needs_review"))
        target_mpn = str(row.get("mapped_mpn", "") or "").strip()
        found = False
        any_provider_candidates = False

        if lookup_mode == "non_catalog":
            queue.at[idx, "status"] = "skipped_non_catalog"
            queue.at[idx, "research_note"] = "Marked NONCATALOG/CUSTOM in mpn_map.csv; API lookup skipped."
            continue

        if args.strict_mapping and mapping_status != "mapped":
            queue.at[idx, "status"] = "needs_mapping"
            queue.at[idx, "research_note"] = "No verified MPN mapping. Update part_master/mpn_map before pricing research."
            continue

        for mpn in candidate_mpns:
            for provider in providers:
                provider_findings = provider.lookup(task_id=task_id, query_mpn=mpn)
                if provider_findings:
                    any_provider_candidates = True
                    for pf in provider_findings:
                        pf.target_mpn = target_mpn or mpn
                        if pf.target_mpn:
                            pf.match_score = match_score(pf.target_mpn, pf.matched_part)
                        else:
                            pf.match_score = 0.0
                        pf.accepted_match = (
                            (not args.strict_mapping)
                            or (bool(pf.target_mpn) and pf.match_score >= args.min_match_score)
                        )
                        if args.strict_mapping and not pf.accepted_match and not pf.note:
                            pf.note = "Candidate returned but failed strict MPN match threshold."
                        findings.append(pf)
                        if pf.accepted_match:
                            found = True
            if found and args.strict_mapping:
                break

        agent_attempted = False
        if not found and args.agent_fallback and agent_count < args.agent_batch_size and candidate_mpns:
            agent_attempted = True
            agent_result = run_agent_research(
                agent=args.agent_fallback,
                cwd=Path(args.cwd).resolve(),
                task_id=task_id,
                vendor=vendor,
                item_id=item_id,
                description=description,
                query_mpn=target_mpn or candidate_mpns[0],
            )
            if agent_result:
                any_provider_candidates = True
                agent_result.target_mpn = target_mpn or candidate_mpns[0]
                agent_result.match_score = (
                    match_score(agent_result.target_mpn, agent_result.matched_part) if agent_result.target_mpn else 0.0
                )
                agent_result.accepted_match = (
                    (not args.strict_mapping)
                    or (bool(agent_result.target_mpn) and agent_result.match_score >= args.min_match_score)
                )
                if args.strict_mapping and not agent_result.accepted_match and not agent_result.note:
                    agent_result.note = "AI result failed strict MPN match threshold."
                findings.append(agent_result)
                if agent_result.accepted_match:
                    found = True
                    agent_count += 1

        if found:
            queue.at[idx, "status"] = "researched"
        else:
            queue.at[idx, "status"] = "needs_research"
            if args.strict_mapping and mapping_status == "mapped" and any_provider_candidates:
                queue.at[idx, "research_note"] = (
                    "Candidates found but none passed strict MPN match. Verify mapping or retry with alternate MPN."
                )
            elif not enabled_provider_names and not args.agent_fallback:
                queue.at[idx, "research_note"] = "No external APIs configured. Set provider credentials or use agent fallback."
            elif not enabled_provider_names and args.agent_fallback and not candidate_mpns:
                queue.at[idx, "research_note"] = "No candidate part number token extracted for API/agent research."
            elif not enabled_provider_names and args.agent_fallback and agent_attempted:
                queue.at[idx, "research_note"] = "Agent fallback did not return structured data for this task."
            else:
                queue.at[idx, "research_note"] = "No API match found. Keep in agent/manual queue."

    findings_df = pd.DataFrame(asdict(f) for f in findings)
    if findings_df.empty:
        findings_df = pd.DataFrame(
            columns=[
                "task_id",
                "provider",
                "query_mpn",
                "target_mpn",
                "matched_part",
                "manufacturer",
                "unit_price",
                "currency",
                "source_url",
                "note",
                "match_score",
                "accepted_match",
            ]
        )
    findings_df.to_csv(outdir / "price_findings.csv", index=False)

    best_source = findings_df.copy()
    if args.strict_mapping and "accepted_match" in best_source.columns:
        best_source = best_source[best_source["accepted_match"] == True]
    best = (
        best_source.dropna(subset=["unit_price"])
        .sort_values("unit_price")
        .groupby("task_id", as_index=False)
        .first()
        .rename(
            columns={
                "unit_price": "best_unit_price",
                "provider": "best_provider",
                "source_url": "best_source_url",
                "match_score": "best_match_score",
                "matched_part": "best_matched_part",
                "target_mpn": "target_mpn",
            }
        )
    )
    action = queue.merge(
        best[
            [
                "task_id",
                "best_unit_price",
                "best_provider",
                "best_source_url",
                "best_match_score",
                "best_matched_part",
                "target_mpn",
            ]
        ],
        on="task_id",
        how="left",
    )
    action["best_unit_price"] = pd.to_numeric(action["best_unit_price"], errors="coerce")
    action["avg_unit_cost_12m"] = pd.to_numeric(action["avg_unit_cost_12m"], errors="coerce")
    action["qty_12m"] = pd.to_numeric(action["qty_12m"], errors="coerce")
    action["estimated_savings"] = (
        (action["avg_unit_cost_12m"] - action["best_unit_price"]).clip(lower=0) * action["qty_12m"]
    ).fillna(0)
    action["action_type"] = action["task_type"].map(
        {
            "pricing_benchmark": "Renegotiate current supplier pricing",
            "alternate_part": "Review alternate/drop-in part options",
        }
    )
    action["priority_score"] = action["estimated_savings"] + action["spend_12m"] * 0.03
    action = action.sort_values(["priority_score", "spend_12m"], ascending=False)

    queue.to_csv(outdir / "research_queue.csv", index=False)
    action.to_csv(outdir / "prioritized_research_actions.csv", index=False)
    write_part_master_outputs(queue=queue, outdir=outdir)

    map_template = (
        queue[["Item ID", "description", "candidate_mpns", "lookup_mode"]]
        .drop_duplicates(subset=["Item ID"])
        .rename(columns={"candidate_mpns": "candidate_hint_tokens"})
        .sort_values("Item ID")
    )
    map_template["mpn"] = ""
    map_template["notes"] = ""
    map_template.to_csv(outdir / "mpn_map_template.csv", index=False)

    fx_rates = fx_snapshot()
    fx_df = pd.DataFrame(
        [{"base": "USD", "currency": ccy, "rate": rate} for ccy, rate in sorted(fx_rates.items()) if ccy in {"CNY", "EUR", "MXN", "VND", "JPY"}]
    )
    fx_df.to_csv(outdir / "fx_snapshot.csv", index=False)

    print(f"Queue rows: {len(queue)}")
    print(f"Price findings: {len(findings_df)}")
    print(f"Action rows: {len(action)}")
    mapped_count = sum(1 for v in mapping_index.values() if v.get("mapping_status") == "mapped")
    print(f"Mapping rows loaded: {len(mapping_index)} (mapped={mapped_count})")
    print(f"Outputs written to: {outdir}")
    return 0


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Run procurement pricing + alternates research loop.")
    parser.add_argument(
        "--inventory",
        default="Inventory List with pricing.xlsx",
        help="Path to inventory workbook.",
    )
    parser.add_argument(
        "--vendor-priority",
        default="output/spreadsheet/vendor_priority_actions.csv",
        help="Path to vendor priority CSV.",
    )
    parser.add_argument(
        "--item-spread",
        default="output/spreadsheet/item_multisource_price_spread.csv",
        help="Path to multi-source price spread CSV.",
    )
    parser.add_argument(
        "--mpn-map",
        default="output/research/mpn_map.csv",
        help="Optional CSV mapping internal Item ID to real manufacturer part number (MPN).",
    )
    parser.add_argument(
        "--strict-mapping",
        action=argparse.BooleanOptionalAction,
        default=True,
        help="If true, only run pricing lookups for rows with verified mapped MPNs.",
    )
    parser.add_argument(
        "--min-match-score",
        type=float,
        default=0.92,
        help="Minimum normalized match score between mapped MPN and returned part number.",
    )
    parser.add_argument(
        "--outdir",
        default="output/research",
        help="Output directory for research artifacts.",
    )
    parser.add_argument("--top-vendors", type=int, default=25, help="How many top vendors to queue.")
    parser.add_argument("--items-per-vendor", type=int, default=3, help="Top spend items per vendor for pricing research.")
    parser.add_argument("--top-spread-items", type=int, default=25, help="Top multi-source spread items to queue.")
    parser.add_argument(
        "--agent-fallback",
        choices=["codex", "claude"],
        default=None,
        help="Optional agent fallback for unresolved tasks.",
    )
    parser.add_argument(
        "--agent-batch-size",
        type=int,
        default=0,
        help="Maximum number of tasks to send to agent fallback.",
    )
    parser.add_argument(
        "--cwd",
        default=".",
        help="Working directory to pass into agent CLI calls.",
    )
    return parser.parse_args()


if __name__ == "__main__":
    sys.exit(run(parse_args()))
