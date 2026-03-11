import React, { useEffect, useState, useMemo } from 'react';
import { apiGet } from '../api';
import ButtonIcon from '../components/ui/ButtonIcon';

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

const DEFAULT_PAGE_SIZE = 25;
const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];

const COLUMNS = [
  { key: 'item_id', header: 'Item ID', accessor: (r) => r.item_id ?? '' },
  { key: 'vendor_name', header: 'Current vendor', accessor: (r) => r.vendor_name ?? '' },
  { key: 'unit_cost', header: 'Current unit cost', accessor: (r) => formatNum(r.unit_cost) },
  { key: 'quantity', header: 'Quantity', accessor: (r) => r.quantity ?? '' },
  { key: 'lowest_current_vendor_price', header: 'Lowest price (current vendor)', accessor: (r) => formatNum(r.lowest_current_vendor_price) },
  { key: 'current_vendor_link', header: 'Current vendor (link)', accessor: (r) => r.current_vendor_name ?? '—' },
  { key: 'savings_vs_current_vendor', header: 'Savings vs current vendor', accessor: (r) => formatSavings(r.savings_vs_current_vendor) },
  { key: 'lowest_alt_vendor_price', header: 'Lowest price (alt vendor)', accessor: (r) => formatNum(r.lowest_alt_vendor_price) },
  { key: 'lowest_alt_vendor_name', header: 'Alt vendor (link)', accessor: (r) => r.lowest_alt_vendor_name ?? '—' },
  { key: 'savings_vs_alt_vendor', header: 'Savings vs alt vendor', accessor: (r) => formatSavings(r.savings_vs_alt_vendor) },
];

export default function PriceComparison() {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [vendorFilter, setVendorFilter] = useState('');
  const [itemFilter, setItemFilter] = useState('');
  const [minSavingsFilter, setMinSavingsFilter] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(DEFAULT_PAGE_SIZE);

  useEffect(() => {
    setPage(1);
  }, [vendorFilter, itemFilter, minSavingsFilter]);

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
    if (v) list = list.filter((r) => (r.vendor_name ?? '').toLowerCase().includes(v));
    const i = itemFilter.trim().toLowerCase();
    if (i) list = list.filter((r) => (r.item_id ?? '').toLowerCase().includes(i));
    const minS = parseFloat(minSavingsFilter);
    if (!Number.isNaN(minS) && minS > 0) {
      list = list.filter((r) => {
        const sCur = r.savings_vs_current_vendor ?? 0;
        const sAlt = r.savings_vs_alt_vendor ?? 0;
        return sCur >= minS || sAlt >= minS;
      });
    }
    return list;
  }, [data, vendorFilter, itemFilter, minSavingsFilter]);

  const totalFiltered = filtered.length;
  const totalPages = Math.max(1, Math.ceil(totalFiltered / perPage));
  const currentPage = Math.min(Math.max(1, page), totalPages);
  const start = (currentPage - 1) * perPage;
  const paginatedRows = useMemo(
    () => filtered.slice(start, start + perPage),
    [filtered, start, perPage]
  );

  const goToPage = (p) => setPage(Math.max(1, Math.min(p, totalPages)));
  const onPerPageChange = (e) => {
    const val = Number(e.target.value) || DEFAULT_PAGE_SIZE;
    setPerPage(val);
    setPage(1);
  };

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
          placeholder="Filter by item ID"
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
          <ButtonIcon>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
          </ButtonIcon>
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
            {paginatedRows.length === 0 ? (
              <tr>
                <td colSpan={COLUMNS.length} style={{ padding: '1rem', color: '#8b949e' }}>
                  No rows match the filters.
                </td>
              </tr>
            ) : (
              paginatedRows.map((row) => (
                <tr key={row.inventory_id} style={{ borderBottom: '1px solid #30363d' }}>
                  {COLUMNS.map((c) => {
                    if (c.key === 'current_vendor_link') {
                      const url = row.current_vendor_url;
                      return (
                        <td key={c.key} style={{ padding: '0.5rem 0.75rem' }}>
                          {url ? (
                            <a href={url} target="_blank" rel="noopener noreferrer" style={{ color: '#58a6ff' }}>
                              {row.current_vendor_name || 'Link'}
                            </a>
                          ) : (
                            row.current_vendor_name ?? '—'
                          )}
                        </td>
                      );
                    }
                    if (c.key === 'lowest_alt_vendor_name') {
                      const url = row.lowest_alt_vendor_url;
                      const name = row.lowest_alt_vendor_name;
                      return (
                        <td key={c.key} style={{ padding: '0.5rem 0.75rem' }}>
                          {url ? (
                            <a href={url} target="_blank" rel="noopener noreferrer" style={{ color: '#58a6ff' }}>
                              {name || 'Link'}
                            </a>
                          ) : (
                            name ?? '—'
                          )}
                        </td>
                      );
                    }
                    return (
                      <td key={c.key} style={{ padding: '0.5rem 0.75rem' }}>
                        {c.accessor(row)}
                      </td>
                    );
                  })}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: '0.75rem', marginTop: '1rem', padding: '0.5rem 0' }}>
        <p style={{ margin: 0, color: '#8b949e', fontSize: '0.8125rem' }}>
          Showing {totalFiltered === 0 ? 0 : start + 1}–{Math.min(start + perPage, totalFiltered)} of {totalFiltered} rows
          {data.length !== totalFiltered && ` (${data.length} total before filters)`}
        </p>
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', flexWrap: 'wrap' }}>
          <label style={{ color: '#8b949e', fontSize: '0.8125rem', display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
            Rows per page
            <select
              value={perPage}
              onChange={onPerPageChange}
              style={{
                padding: '0.25rem 0.5rem',
                background: '#161b22',
                border: '1px solid #30363d',
                borderRadius: 6,
                color: '#e6edf3',
                fontSize: '0.8125rem',
              }}
            >
              {PAGE_SIZE_OPTIONS.map((n) => (
                <option key={n} value={n}>{n}</option>
              ))}
            </select>
          </label>
          <span style={{ color: '#8b949e', fontSize: '0.8125rem' }}>
            Page {currentPage} of {totalPages}
          </span>
          <button
            type="button"
            onClick={() => goToPage(1)}
            disabled={currentPage <= 1}
            style={{
              padding: '0.25rem 0.5rem',
              background: currentPage <= 1 ? '#21262d' : '#161b22',
              border: '1px solid #30363d',
              borderRadius: 6,
              color: currentPage <= 1 ? '#484f58' : '#e6edf3',
              cursor: currentPage <= 1 ? 'not-allowed' : 'pointer',
              fontSize: '0.8125rem',
            }}
          >
            First
          </button>
          <button
            type="button"
            onClick={() => goToPage(currentPage - 1)}
            disabled={currentPage <= 1}
            style={{
              padding: '0.25rem 0.5rem',
              background: currentPage <= 1 ? '#21262d' : '#161b22',
              border: '1px solid #30363d',
              borderRadius: 6,
              color: currentPage <= 1 ? '#484f58' : '#e6edf3',
              cursor: currentPage <= 1 ? 'not-allowed' : 'pointer',
              fontSize: '0.8125rem',
            }}
          >
            Previous
          </button>
          <button
            type="button"
            onClick={() => goToPage(currentPage + 1)}
            disabled={currentPage >= totalPages}
            style={{
              padding: '0.25rem 0.5rem',
              background: currentPage >= totalPages ? '#21262d' : '#161b22',
              border: '1px solid #30363d',
              borderRadius: 6,
              color: currentPage >= totalPages ? '#484f58' : '#e6edf3',
              cursor: currentPage >= totalPages ? 'not-allowed' : 'pointer',
              fontSize: '0.8125rem',
            }}
          >
            Next
          </button>
          <button
            type="button"
            onClick={() => goToPage(totalPages)}
            disabled={currentPage >= totalPages}
            style={{
              padding: '0.25rem 0.5rem',
              background: currentPage >= totalPages ? '#21262d' : '#161b22',
              border: '1px solid #30363d',
              borderRadius: 6,
              color: currentPage >= totalPages ? '#484f58' : '#e6edf3',
              cursor: currentPage >= totalPages ? 'not-allowed' : 'pointer',
              fontSize: '0.8125rem',
            }}
          >
            Last
          </button>
        </div>
      </div>
    </div>
  );
}
