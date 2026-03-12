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
  { key: 'mpn_list', header: 'MPN', accessor: (r) => r.mpn_list ?? '' },
  { key: 'unit_cost', header: 'Unit cost', accessor: (r) => formatNum(r.unit_cost) },
  { key: 'quantity', header: 'Quantity', accessor: (r) => (r.quantity != null && r.quantity !== '') ? Math.round(Number(r.quantity)).toLocaleString() : '' },
  { key: 'ext_cost', header: 'Total Cost', accessor: (r) => formatNum(r.ext_cost) },
  { key: 'current_vendor_link', header: 'Vendor', accessor: (r) => r.current_vendor_name ?? '—' },
  { key: 'lowest_current_vendor_price', header: 'Current Site Price', accessor: (r) => formatNum(r.lowest_current_vendor_price) },
  { key: 'savings_vs_current_vendor', header: 'Savings vs Total Cost', accessor: (r) => formatSavings(r.savings_vs_current_vendor) },
  { key: 'lowest_alt_vendor_name', header: 'Vendor', accessor: (r) => r.lowest_alt_vendor_name ?? '—' },
  { key: 'lowest_alt_vendor_price', header: 'Lowest Price', accessor: (r) => formatNum(r.lowest_alt_vendor_price) },
  { key: 'savings_vs_alt_vendor', header: 'Savings vs Total Cost', accessor: (r) => formatSavings(r.savings_vs_alt_vendor) },
  { key: 'alt_vendors_view', header: '', accessor: () => '', skipExport: true },
];

const TABLE_STYLES = {
  cellBase: { padding: '0.5rem 0.75rem' },
  borderVertical: { borderLeft: '2px solid #30363d' },
  headerCell: { textAlign: 'left', padding: '0.5rem 0.75rem', fontWeight: 600, color: '#8b949e', background: '#161b22' },
  mainHeaderCell: { textAlign: 'center', padding: '0.5rem 0.75rem', fontWeight: 700, color: '#e6edf3', background: '#21262d', borderBottom: '1px solid #30363d' },
};
const ITEM_COLS = 5;
const CURRENT_VENDOR_COLS = 3;
const ALT_VENDOR_COLS = 4;

export default function PriceComparison() {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [vendorFilter, setVendorFilter] = useState('');
  const [itemFilter, setItemFilter] = useState('');
  const [minSavingsFilter, setMinSavingsFilter] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(DEFAULT_PAGE_SIZE);
  const [altVendorsModalRow, setAltVendorsModalRow] = useState(null);

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
    const exportCols = COLUMNS.filter((c) => !c.skipExport);
    downloadCSV('price-comparison.csv', toCSV(filtered, exportCols));
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
            <tr>
              <th colSpan={ITEM_COLS} style={{ ...TABLE_STYLES.mainHeaderCell }}>
                Item
              </th>
              <th colSpan={CURRENT_VENDOR_COLS} style={{ ...TABLE_STYLES.mainHeaderCell, ...TABLE_STYLES.borderVertical }}>
                Current Vendor
              </th>
              <th colSpan={ALT_VENDOR_COLS} style={{ ...TABLE_STYLES.mainHeaderCell, ...TABLE_STYLES.borderVertical }}>
                Alternative Vendor with Lowest Price
              </th>
            </tr>
            <tr style={{ borderBottom: '1px solid #30363d' }}>
              {COLUMNS.map((c, idx) => (
                <th
                  key={c.key}
                  style={{
                    ...TABLE_STYLES.headerCell,
                    ...(idx === ITEM_COLS || idx === ITEM_COLS + CURRENT_VENDOR_COLS ? TABLE_STYLES.borderVertical : {}),
                  }}
                >
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
                  {COLUMNS.map((c, idx) => {
                    const cellStyle = {
                      ...TABLE_STYLES.cellBase,
                      ...(idx === ITEM_COLS || idx === ITEM_COLS + CURRENT_VENDOR_COLS ? TABLE_STYLES.borderVertical : {}),
                    };
                    if (c.key === 'mpn_list') {
                      return (
                        <td key={c.key} style={{ ...cellStyle, whiteSpace: 'pre-line' }}>
                          {row.mpn_list ?? '—'}
                        </td>
                      );
                    }
                    if (c.key === 'current_vendor_link') {
                      const url = row.current_vendor_url;
                      return (
                        <td key={c.key} style={cellStyle}>
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
                        <td key={c.key} style={cellStyle}>
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
                    if (c.key === 'alt_vendors_view') {
                      const altVendors = row.alt_vendors ?? [];
                      return (
                        <td key={c.key} style={cellStyle}>
                          {altVendors.length > 0 ? (
                            <button
                              type="button"
                              onClick={() => setAltVendorsModalRow(row)}
                              title="View all alternative vendors"
                              style={{
                                padding: '0.25rem',
                                background: 'transparent',
                                border: 'none',
                                cursor: 'pointer',
                                color: '#8b949e',
                                borderRadius: 4,
                              }}
                            >
                              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                              </svg>
                            </button>
                          ) : (
                            '—'
                          )}
                        </td>
                      );
                    }
                    return (
                      <td key={c.key} style={cellStyle}>
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

      {altVendorsModalRow && (
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="alt-vendors-modal-title"
          onClick={() => setAltVendorsModalRow(null)}
          style={{
            position: 'fixed',
            inset: 0,
            background: 'rgba(0,0,0,0.6)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: 1000,
            padding: '1rem',
          }}
        >
          <div
            onClick={(e) => e.stopPropagation()}
            style={{
              background: '#161b22',
              border: '1px solid #30363d',
              borderRadius: 8,
              maxWidth: 480,
              width: '100%',
              maxHeight: '80vh',
              overflow: 'auto',
              boxShadow: '0 8px 24px rgba(0,0,0,0.4)',
            }}
          >
            <div style={{ padding: '1rem 1.25rem', borderBottom: '1px solid #30363d', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <h2 id="alt-vendors-modal-title" style={{ margin: 0, fontSize: '1.125rem', color: '#e6edf3' }}>
                Alternative vendors {altVendorsModalRow.item_id ? `— ${altVendorsModalRow.item_id}` : ''}
              </h2>
              <button
                type="button"
                onClick={() => setAltVendorsModalRow(null)}
                style={{
                  padding: '0.25rem 0.5rem',
                  background: '#21262d',
                  border: '1px solid #30363d',
                  borderRadius: 6,
                  color: '#e6edf3',
                  cursor: 'pointer',
                  fontSize: '0.875rem',
                }}
              >
                Close
              </button>
            </div>
            <div style={{ padding: '1rem 1.25rem' }}>
              {(altVendorsModalRow.alt_vendors ?? []).length === 0 ? (
                <p style={{ margin: 0, color: '#8b949e' }}>No alternative vendors.</p>
              ) : (
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.875rem', color: '#e6edf3' }}>
                  <thead>
                    <tr style={{ borderBottom: '1px solid #30363d' }}>
                      <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e', fontWeight: 600 }}>Vendor</th>
                      <th style={{ textAlign: 'right', padding: '0.5rem 0.75rem', color: '#8b949e', fontWeight: 600 }}>Price</th>
                      <th style={{ textAlign: 'right', padding: '0.5rem 0.75rem', color: '#8b949e', fontWeight: 600 }}>Savings</th>
                    </tr>
                  </thead>
                  <tbody>
                    {altVendorsModalRow.alt_vendors.map((av, i) => (
                      <tr key={i} style={{ borderBottom: '1px solid #30363d' }}>
                        <td style={{ padding: '0.5rem 0.75rem' }}>
                          {av.url ? (
                            <a href={av.url} target="_blank" rel="noopener noreferrer" style={{ color: '#58a6ff' }}>
                              {av.vendor_name || '—'}
                            </a>
                          ) : (
                            av.vendor_name || '—'
                          )}
                        </td>
                        <td style={{ padding: '0.5rem 0.75rem', textAlign: 'right' }}>{formatNum(av.unit_price)}</td>
                        <td style={{ padding: '0.5rem 0.75rem', textAlign: 'right' }}>{formatSavings(av.savings)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </div>
        </div>
      )}

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
