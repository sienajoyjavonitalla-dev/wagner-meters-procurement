import React, { useCallback, useEffect, useState } from 'react';
import { apiGet, apiPost } from '../api';

const API = '/api/procurement/inventories';
const DEFAULT_PAGE_SIZE = 25;
const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];

function formatNum(n) {
  if (n == null || Number.isNaN(n)) return '—';
  return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 });
}

function formatDate(iso) {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}

export default function Inventories() {
  const [data, setData] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, per_page: DEFAULT_PAGE_SIZE, total: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(DEFAULT_PAGE_SIZE);
  const [clearingId, setClearingId] = useState(null);

  const fetchInventories = useCallback(() => {
    setLoading(true);
    setError(null);
    apiGet(`${API}?page=${page}&per_page=${perPage}`)
      .then((res) => {
        setData(res.data ?? []);
        setMeta(res.meta ?? { current_page: 1, last_page: 1, per_page: perPage, total: 0 });
      })
      .catch((err) => {
        setError(err?.message || 'Failed to load inventories');
      })
      .finally(() => {
        setLoading(false);
      });
  }, [page, perPage]);

  useEffect(() => {
    fetchInventories();
  }, [fetchInventories]);

  const handleClearResearch = (inv) => {
    if (clearingId != null) return;
    setClearingId(inv.id);
    apiPost(`${API}/${inv.id}/clear-research`, {})
      .then(() => {
        setData((prev) =>
          prev.map((row) => (row.id === inv.id ? { ...row, research_completed_at: null } : row))
        );
      })
      .catch((err) => {
        const msg = err?.errors?.message || err?.message || 'Failed to clear research';
        alert(msg);
      })
      .finally(() => {
        setClearingId(null);
      });
  };

  const totalPages = meta.last_page ?? 1;
  const total = meta.total ?? 0;

  if (loading && data.length === 0) {
    return (
      <div className="app-main-inner" style={{ padding: '1.5rem' }}>
        <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Inventories</h1>
        <p style={{ color: '#8b949e' }}>Loading…</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="app-main-inner" style={{ padding: '1.5rem' }}>
        <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Inventories</h1>
        <p style={{ color: '#f85149' }}>{error}</p>
      </div>
    );
  }

  return (
    <div className="app-main-inner" style={{ padding: '1.5rem' }}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Inventories</h1>
      <p style={{ color: '#8b949e', marginBottom: '1rem' }}>
        Inventory items from the current import. Use “Clear research” to reset an item so it is included in the next research run.
      </p>

      {data.length === 0 ? (
        <p style={{ color: '#8b949e' }}>No inventory items. Import an inventory file from Data Import first.</p>
      ) : (
        <>
          <div style={{ overflowX: 'auto', border: '1px solid #30363d', borderRadius: 8, background: '#161b22' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 800 }}>
              <thead>
                <tr>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', fontWeight: 600, color: '#8b949e', background: '#21262d', borderBottom: '1px solid #30363d' }}>Item ID</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', fontWeight: 600, color: '#8b949e', background: '#21262d', borderBottom: '1px solid #30363d' }}>Description</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', fontWeight: 600, color: '#8b949e', background: '#21262d', borderBottom: '1px solid #30363d' }}>Vendor</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', fontWeight: 600, color: '#8b949e', background: '#21262d', borderBottom: '1px solid #30363d' }}>Product line</th>
                  <th style={{ textAlign: 'right', padding: '0.5rem 0.75rem', fontWeight: 600, color: '#8b949e', background: '#21262d', borderBottom: '1px solid #30363d' }}>Qty</th>
                  <th style={{ textAlign: 'right', padding: '0.5rem 0.75rem', fontWeight: 600, color: '#8b949e', background: '#21262d', borderBottom: '1px solid #30363d' }}>Unit cost</th>
                  <th style={{ textAlign: 'right', padding: '0.5rem 0.75rem', fontWeight: 600, color: '#8b949e', background: '#21262d', borderBottom: '1px solid #30363d' }}>Ext. cost</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', fontWeight: 600, color: '#8b949e', background: '#21262d', borderBottom: '1px solid #30363d' }}>Research</th>
                  <th style={{ textAlign: 'center', padding: '0.5rem 0.75rem', fontWeight: 600, color: '#8b949e', background: '#21262d', borderBottom: '1px solid #30363d' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {data.map((row) => (
                  <tr key={row.id} style={{ borderBottom: '1px solid #21262d' }}>
                    <td style={{ padding: '0.5rem 0.75rem', color: '#e6edf3' }}>{row.item_id ?? '—'}</td>
                    <td style={{ padding: '0.5rem 0.75rem', color: '#e6edf3', maxWidth: 240, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }} title={row.description ?? ''}>{row.description ?? '—'}</td>
                    <td style={{ padding: '0.5rem 0.75rem', color: '#e6edf3' }}>{row.vendor_name ?? '—'}</td>
                    <td style={{ padding: '0.5rem 0.75rem', color: '#e6edf3' }}>{row.product_line ?? '—'}</td>
                    <td style={{ padding: '0.5rem 0.75rem', color: '#e6edf3', textAlign: 'right' }}>{row.quantity != null ? Number(row.quantity).toLocaleString() : '—'}</td>
                    <td style={{ padding: '0.5rem 0.75rem', color: '#e6edf3', textAlign: 'right' }}>{formatNum(row.unit_cost)}</td>
                    <td style={{ padding: '0.5rem 0.75rem', color: '#e6edf3', textAlign: 'right' }}>{formatNum(row.ext_cost)}</td>
                    <td style={{ padding: '0.5rem 0.75rem', color: '#8b949e' }}>
                      {row.research_completed_at ? (
                        <span title={formatDate(row.research_completed_at)}>Done</span>
                      ) : (
                        <span>Pending</span>
                      )}
                    </td>
                    <td style={{ padding: '0.5rem 0.75rem', textAlign: 'center' }}>
                      <button
                        type="button"
                        onClick={() => handleClearResearch(row)}
                        disabled={clearingId != null || !row.research_completed_at}
                        style={{
                          padding: '0.35rem 0.6rem',
                          fontSize: '0.8125rem',
                          background: row.research_completed_at && clearingId !== row.id ? '#238636' : '#21262d',
                          color: row.research_completed_at && clearingId !== row.id ? '#fff' : '#8b949e',
                          border: '1px solid #30363d',
                          borderRadius: 6,
                          cursor: row.research_completed_at && clearingId !== row.id ? 'pointer' : 'not-allowed',
                        }}
                        title={row.research_completed_at ? 'Clear research so this item is re-researched on the next run' : 'No research to clear'}
                      >
                        {clearingId === row.id ? 'Clearing…' : 'Clear research'}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '0.75rem', marginTop: '1rem' }}>
            <span style={{ color: '#8b949e', fontSize: '0.875rem' }}>
              {total} item{total !== 1 ? 's' : ''}
            </span>
            <select
              value={perPage}
              onChange={(e) => { setPerPage(Number(e.target.value) || DEFAULT_PAGE_SIZE); setPage(1); }}
              style={{
                padding: '0.35rem 0.5rem',
                background: '#161b22',
                border: '1px solid #30363d',
                borderRadius: 6,
                color: '#e6edf3',
              }}
            >
              {PAGE_SIZE_OPTIONS.map((n) => (
                <option key={n} value={n}>{n} per page</option>
              ))}
            </select>
            <div style={{ display: 'flex', gap: '0.25rem', alignItems: 'center' }}>
              <button
                type="button"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page <= 1}
                style={{
                  padding: '0.35rem 0.5rem',
                  background: '#21262d',
                  border: '1px solid #30363d',
                  borderRadius: 6,
                  color: page <= 1 ? '#484f58' : '#e6edf3',
                  cursor: page <= 1 ? 'not-allowed' : 'pointer',
                }}
              >
                Previous
              </button>
              <span style={{ color: '#8b949e', fontSize: '0.875rem', padding: '0 0.5rem' }}>
                Page {page} of {totalPages}
              </span>
              <button
                type="button"
                onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                disabled={page >= totalPages}
                style={{
                  padding: '0.35rem 0.5rem',
                  background: '#21262d',
                  border: '1px solid #30363d',
                  borderRadius: 6,
                  color: page >= totalPages ? '#484f58' : '#e6edf3',
                  cursor: page >= totalPages ? 'not-allowed' : 'pointer',
                }}
              >
                Next
              </button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
