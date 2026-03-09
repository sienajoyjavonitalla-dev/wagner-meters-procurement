#!/usr/bin/env python3
from pathlib import Path

import pandas as pd


def main() -> None:
    root = Path(__file__).resolve().parents[1]
    out = root / "phase5_1" / "input"
    out.mkdir(parents=True, exist_ok=True)

    inventory = pd.DataFrame(
        [
            {
                "Transaction Date": "2026-01-05",
                "Vendor Name": "Vendor A",
                "Item ID": "ABC1234",
                "Description": "Resistor ABC1234 10K",
                "Ext. Cost": 1200.0,
                "Unit Cost": 1.20,
                "Quantity": 1000,
            },
            {
                "Transaction Date": "2026-01-08",
                "Vendor Name": "Vendor A",
                "Item ID": "XYZ9876",
                "Description": "Capacitor XYZ9876 10uF",
                "Ext. Cost": 900.0,
                "Unit Cost": 0.90,
                "Quantity": 1000,
            },
            {
                "Transaction Date": "2026-01-10",
                "Vendor Name": "Vendor B",
                "Item ID": "LMN5555",
                "Description": "Regulator LMN5555 5V",
                "Ext. Cost": 800.0,
                "Unit Cost": 0.80,
                "Quantity": 1000,
            },
            {
                "Transaction Date": "2026-02-02",
                "Vendor Name": "Vendor B",
                "Item ID": "NC9999",
                "Description": "Custom Assembly NC9999",
                "Ext. Cost": 600.0,
                "Unit Cost": 6.00,
                "Quantity": 100,
            },
            {
                "Transaction Date": "2026-02-12",
                "Vendor Name": "Vendor C",
                "Item ID": "AAA1111",
                "Description": "Connector AAA1111",
                "Ext. Cost": 500.0,
                "Unit Cost": 0.50,
                "Quantity": 1000,
            },
            {
                "Transaction Date": "2026-02-15",
                "Vendor Name": "Vendor C",
                "Item ID": "BBB2222",
                "Description": "Relay BBB2222",
                "Ext. Cost": 400.0,
                "Unit Cost": 4.00,
                "Quantity": 100,
            },
        ]
    )
    vendor_priority = pd.DataFrame(
        [
            {"Vendor Name": "Vendor A", "priority_rank": 1},
            {"Vendor Name": "Vendor B", "priority_rank": 2},
            {"Vendor Name": "Vendor C", "priority_rank": 3},
        ]
    )
    item_spread = pd.DataFrame(
        [
            {"Item ID": "ABC1234"},
            {"Item ID": "XYZ9876"},
            {"Item ID": "LMN5555"},
            {"Item ID": "NC9999"},
        ]
    )
    mpn_map = pd.DataFrame(
        [
            {"Item ID": "ABC1234", "mpn": "ABC1234", "lookup_mode": "catalog_lookup", "notes": "curated_high_confidence"},
            {"Item ID": "XYZ9876", "mpn": "XYZ9876", "lookup_mode": "catalog_lookup", "notes": "verified"},
            {"Item ID": "LMN5555", "mpn": "LMN5555", "lookup_mode": "catalog_lookup", "notes": "erp"},
            {"Item ID": "NC9999", "mpn": "", "lookup_mode": "non_catalog", "notes": "custom part"},
            {"Item ID": "AAA1111", "mpn": "", "lookup_mode": "catalog_lookup", "notes": "missing mapping"},
            {"Item ID": "BBB2222", "mpn": "", "lookup_mode": "catalog_lookup", "notes": "missing mapping"},
        ]
    )

    inventory.to_excel(out / "inventory.xlsx", index=False)
    vendor_priority.to_csv(out / "vendor_priority.csv", index=False)
    item_spread.to_csv(out / "item_spread.csv", index=False)
    mpn_map.to_csv(out / "mpn_map.csv", index=False)

    print(f"Generated sample files in: {out}")


if __name__ == "__main__":
    main()
