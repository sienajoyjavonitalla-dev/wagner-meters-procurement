import React, { useEffect, useState } from 'react';
import { apiGet } from '../api';
import ButtonIcon from '../components/ui/ButtonIcon';

const API = '/api/procurement/queue';

export default function ResearchQueue() {
  const [result, setResult] = useState({ data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('');
  const [vendor, setVendor] = useState('');
  const [itemSearch, setItemSearch] = useState('');

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    const params = new URLSearchParams({ page, per_page: 20 });
    if (status) params.set('status', status);
    if (vendor) params.set('vendor', vendor);
    if (itemSearch) params.set('item_search', itemSearch);
    apiGet(`${API}?${params}`)
      .then((res) => {
        if (!cancelled) setResult({ data: res.data ?? [], meta: res.meta ?? { current_page: 1, last_page: 1, per_page: 20, total: 0 } });
      })
      .catch((err) => {
        if (!cancelled) setError(err?.message || 'Failed to load queue');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  }, [page, status, vendor, itemSearch]);

  const { data, meta } = result;

  if (error) {
    return (
      <div className="app-main-inner" style={{ padding: '1.5rem' }}>
        <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Research Queue</h1>
        <p style={{ color: '#f85149' }}>{error}</p>
      </div>
    );
  }

  return (
    <div className="app-main-inner" style={{ padding: '1.5rem' }}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Research Queue</h1>
      <p style={{ color: '#8b949e', marginBottom: '1rem' }}>Paginated list with filters (status, vendor, item).</p>

      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.75rem', alignItems: 'center', marginBottom: '1rem' }}>
        <input
          type="text"
          placeholder="Status"
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          style={{
            padding: '0.35rem 0.5rem',
            background: '#161b22',
            border: '1px solid #30363d',
            borderRadius: 6,
            color: '#e6edf3',
            width: 120,
          }}
        />
        <input
          type="text"
          placeholder="Vendor"
          value={vendor}
          onChange={(e) => setVendor(e.target.value)}
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
          placeholder="Item search"
          value={itemSearch}
          onChange={(e) => setItemSearch(e.target.value)}
          style={{
            padding: '0.35rem 0.5rem',
            background: '#161b22',
            border: '1px solid #30363d',
            borderRadius: 6,
            color: '#e6edf3',
            minWidth: 140,
          }}
        />
        <button
          type="button"
          onClick={() => setPage(1)}
          style={{
            padding: '0.35rem 0.75rem',
            background: '#1f6feb',
            border: '1px solid #388bfd',
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
              <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
            </svg>
          </ButtonIcon>
          Apply
        </button>
      </div>

      {loading ? (
        <p style={{ color: '#8b949e' }}>Loading…</p>
      ) : (
        <>
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', color: '#e6edf3', fontSize: '0.875rem' }}>
              <thead>
                <tr style={{ borderBottom: '1px solid #30363d' }}>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>ID</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Type</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Status</th>
                  <th style={{ textAlign: 'right', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Priority</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Item</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Vendor</th>
                </tr>
              </thead>
              <tbody>
                {data.length === 0 ? (
                  <tr>
                    <td colSpan={6} style={{ padding: '1rem', color: '#8b949e' }}>No tasks in queue.</td>
                  </tr>
                ) : (
                  data.map((t) => (
                    <tr key={t.id} style={{ borderBottom: '1px solid #30363d' }}>
                      <td style={{ padding: '0.5rem 0.75rem' }}>{t.id}</td>
                      <td style={{ padding: '0.5rem 0.75rem' }}>{t.task_type ?? '—'}</td>
                      <td style={{ padding: '0.5rem 0.75rem' }}>{t.status}</td>
                      <td style={{ padding: '0.5rem 0.75rem', textAlign: 'right' }}>{t.priority ?? '—'}</td>
                      <td style={{ padding: '0.5rem 0.75rem' }}>{t.item?.internal_part_number ?? '—'}</td>
                      <td style={{ padding: '0.5rem 0.75rem' }}>{t.supplier?.name ?? '—'}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginTop: '0.75rem', flexWrap: 'wrap' }}>
            <button
              type="button"
              disabled={meta.current_page <= 1}
              onClick={() => setPage((p) => p - 1)}
              style={{
                padding: '0.25rem 0.5rem',
                background: '#21262d',
                border: '1px solid #30363d',
                borderRadius: 6,
                color: '#e6edf3',
                cursor: meta.current_page <= 1 ? 'not-allowed' : 'pointer',
                fontSize: '0.875rem',
              }}
            >
              Previous
            </button>
            <span style={{ color: '#8b949e', fontSize: '0.875rem' }}>
              Page {meta.current_page} of {meta.last_page} ({meta.total} total)
            </span>
            <button
              type="button"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => setPage((p) => p + 1)}
              style={{
                padding: '0.25rem 0.5rem',
                background: '#21262d',
                border: '1px solid #30363d',
                borderRadius: 6,
                color: '#e6edf3',
                cursor: meta.current_page >= meta.last_page ? 'not-allowed' : 'pointer',
                fontSize: '0.875rem',
              }}
            >
              Next
            </button>
          </div>
        </>
      )}
    </div>
  );
}
