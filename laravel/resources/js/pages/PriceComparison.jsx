import React, { useEffect, useState, useMemo } from 'react';
import { apiGet } from '../api';

const API = '/api/procurement/price-comparison';

function formatNum(n) {
  if (n == null || Number.isNaN(n)) return '—';
  return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 });
}

function formatSavings(n) {
  if (n == null || Number.isNaN(n)) return '—';
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
}

function toCSV(rows, columns) {
  const header = columns.map((c) => c.header).join(',');
  const escape = (v) => (v == null ? '' : String(v).replace(/"/g, '""'));
  const body = rows.map((r) => columns.map((c) => `"${escape(c.accessor(r))}"`).join(',')).join('\n');
  return header + '\n' + body;
}

function downloadCSV(filename, content) {
  const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  link.click();
  URL.revokeObjectURL(link.href);
}

function BtnIcon({ children }) {
  return (
    <span className="employees-btn-icon" aria-hidden="true" style={{ width: 16, height: 16, display: 'inline-flex', marginRight: '0.35rem' }}>
      {children}
    </span>
  );
}

const COLUMNS = [
  { key: 'item', header: 'Item', accessor: (r) => r.item?.internal_part_number ?? '' },
  { key: 'vendor', header: 'Vendor', accessor: (r) => r.supplier?.name ?? '' },
  { key: 'current', header: 'Current unit cost', accessor: (r) => formatNum(r.avg_unit_cost_12m) },
  { key: 'best_price', header: 'Best unit price', accessor: (r) => (r.best_finding ? formatNum(r.best_finding.min_unit_price) : '—') },
  { key: 'provider', header: 'Provider', accessor: (r) => r.best_finding?.provider ?? '—' },
  { key: 'savings', header: 'Est. savings', accessor: (r) => formatSavings(r.estimated_savings) },
  { key: 'action_type', header: 'Action type', accessor: (r) => r.action_type ?? '' },
];

export default function PriceComparison() {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [vendorFilter, setVendorFilter] = useState('');
  const [itemFilter, setItemFilter] = useState('');
  const [minSavingsFilter, setMinSavingsFilter] = useState('');

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    apiGet(API)
      .then((res) => {
        if (!cancelled) setData(res.data ?? []);
      })
      .catch((err) => {
        if (!cancelled) setError(err?.message || 'Failed to load price comparison');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  }, []);

  const filtered = useMemo(() => {
    let list = data;
    const v = vendorFilter.trim().toLowerCase();
    if (v) list = list.filter((r) => (r.supplier?.name ?? '').toLowerCase().includes(v));
    const i = itemFilter.trim().toLowerCase();
    if (i) list = list.filter((r) => (r.item?.internal_part_number ?? '').toLowerCase().includes(i));
    const minS = parseFloat(minSavingsFilter);
    if (!Number.isNaN(minS) && minS > 0) list = list.filter((r) => (r.estimated_savings ?? 0) >= minS);
    return list;
  }, [data, vendorFilter, itemFilter, minSavingsFilter]);

  const handleExportCSV = () => {
    downloadCSV('price-comparison.csv', toCSV(filtered, COLUMNS));
  };

  if (loading) {
    return (
      <div className="app-main-inner" style={{ padding: '1.5rem' }}>
        <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Price Comparison</h1>
        <p style={{ color: '#8b949e' }}>Loading…</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="app-main-inner" style={{ padding: '1.5rem' }}>
        <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Price Comparison</h1>
        <p style={{ color: '#f85149' }}>{error}</p>
      </div>
    );
  }

  return (
    <div className="app-main-inner" style={{ padding: '1.5rem' }}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Item Price Comparison</h1>

      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.75rem', alignItems: 'center', marginBottom: '1rem' }}>
        <input
          type="text"
          placeholder="Filter by vendor"
          value={vendorFilter}
          onChange={(e) => setVendorFilter(e.target.value)}
          style={{
            padding: '0.35rem 0.5rem',
            background: '#161b22',
            border: '1px solid #30363d',
            borderRadius: 6,
            color: '#e6edf3',
            minWidth: 140,
          }}
        />
        <input
          type="text"
          placeholder="Filter by item"
          value={itemFilter}
          onChange={(e) => setItemFilter(e.target.value)}
          style={{
            padding: '0.35rem 0.5rem',
            background: '#161b22',
            border: '1px solid #30363d',
            borderRadius: 6,
            color: '#e6edf3',
            minWidth: 140,
          }}
        />
        <input
          type="number"
          placeholder="Min savings"
          value={minSavingsFilter}
          onChange={(e) => setMinSavingsFilter(e.target.value)}
          min={0}
          step={0.01}
          style={{
            padding: '0.35rem 0.5rem',
            background: '#161b22',
            border: '1px solid #30363d',
            borderRadius: 6,
            color: '#e6edf3',
            width: 120,
          }}
        />
        <button
          type="button"
          onClick={handleExportCSV}
          style={{
            padding: '0.35rem 0.75rem',
            background: '#238636',
            border: '1px solid #2ea043',
            borderRadius: 6,
            color: '#fff',
            cursor: 'pointer',
            fontSize: '0.875rem',
            display: 'inline-flex',
            alignItems: 'center',
            lineHeight: 1,
          }}
        >
          <BtnIcon>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
          </BtnIcon>
          Export CSV
        </button>
      </div>

      <div style={{ overflowX: 'auto' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse', color: '#e6edf3', fontSize: '0.875rem' }}>
          <thead>
            <tr style={{ borderBottom: '1px solid #30363d' }}>
              {COLUMNS.map((c) => (
                <th key={c.key} style={{ textAlign: 'left', padding: '0.5rem 0.75rem', fontWeight: 600, color: '#8b949e' }}>
                  {c.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {filtered.length === 0 ? (
              <tr>
                <td colSpan={COLUMNS.length} style={{ padding: '1rem', color: '#8b949e' }}>
                  No rows match the filters.
                </td>
              </tr>
            ) : (
              filtered.map((row) => (
                <tr key={row.research_task_id} style={{ borderBottom: '1px solid #30363d' }}>
                  {COLUMNS.map((c) => (
                    <td key={c.key} style={{ padding: '0.5rem 0.75rem' }}>
                      {c.accessor(row)}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      <p style={{ marginTop: '0.5rem', color: '#8b949e', fontSize: '0.8125rem' }}>
        Showing {filtered.length} of {data.length} rows.
      </p>
    </div>
  );
}
