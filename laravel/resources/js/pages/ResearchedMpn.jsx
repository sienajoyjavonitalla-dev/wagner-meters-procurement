import React, { useEffect, useMemo, useState } from 'react';
import { apiGet } from '../api';

const API = '/api/procurement/researched-mpn';
const DEFAULT_PAGE_SIZE = 25;
const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];

function formatTime(iso) {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}

export default function ResearchedMpn() {
  const [data, setData] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, per_page: DEFAULT_PAGE_SIZE, total: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(DEFAULT_PAGE_SIZE);
  const [search, setSearch] = useState('');
  const [source, setSource] = useState('');

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    const params = new URLSearchParams();
    params.set('page', page);
    params.set('per_page', perPage);
    if (search.trim()) params.set('q', search.trim());
    if (source) params.set('source', source);

    apiGet(`${API}?${params.toString()}`)
      .then((res) => {
        if (cancelled) return;
        setData(res.data ?? []);
        setMeta(res.meta ?? { current_page: 1, last_page: 1, per_page: perPage, total: 0 });
      })
      .catch((err) => {
        if (cancelled) return;
        setError(err?.message || 'Failed to load researched MPN cache');
      })
      .finally(() => {
        if (cancelled) return;
        setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [page, perPage, search, source]);

  const totalPages = meta.last_page ?? 1;
  const total = meta.total ?? 0;

  const sources = useMemo(
    () => [
      { value: '', label: 'All sources' },
      { value: 'gemini', label: 'Gemini' },
      { value: 'digikey', label: 'Digi-Key' },
      { value: 'mouser', label: 'Mouser' },
      { value: 'element14', label: 'element14/Newark' },
    ],
    []
  );

  if (loading && data.length === 0) {
    return (
      <div className="app-main-inner" style={{ padding: '1.5rem' }}>
        <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Researched MPN cache</h1>
        <p style={{ color: '#8b949e' }}>Loading…</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="app-main-inner" style={{ padding: '1.5rem' }}>
        <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Researched MPN cache</h1>
        <p style={{ color: '#f85149' }}>{error}</p>
      </div>
    );
  }

  return (
    <div className="app-main-inner" style={{ padding: '1.5rem' }}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Researched MPN cache</h1>
      <p style={{ color: '#8b949e', marginBottom: '1rem' }}>
        Read-only view of cached provider responses per MPN/source. This helps debug why a given part number returns specific vendors or prices.
      </p>

      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.75rem', alignItems: 'center', marginBottom: '0.75rem' }}>
        <input
          type="text"
          placeholder="Filter by MPN (cache key)"
          value={search}
          onChange={(e) => {
            setPage(1);
            setSearch(e.target.value);
          }}
          style={{
            padding: '0.35rem 0.5rem',
            background: '#161b22',
            border: '1px solid #30363d',
            borderRadius: 6,
            color: '#e6edf3',
            minWidth: 180,
          }}
        />
        <select
          value={source}
          onChange={(e) => {
            setPage(1);
            setSource(e.target.value);
          }}
          style={{
            padding: '0.35rem 0.5rem',
            background: '#161b22',
            border: '1px solid #30363d',
            borderRadius: 6,
            color: '#e6edf3',
            minWidth: 160,
          }}
        >
          {sources.map((s) => (
            <option key={s.value || 'all'} value={s.value}>
              {s.label}
            </option>
          ))}
        </select>
      </div>

      {data.length === 0 ? (
        <p style={{ color: '#8b949e' }}>No cache entries found for the current filters.</p>
      ) : (
        <>
          <div style={{ overflowX: 'auto', border: '1px solid #30363d', borderRadius: 8, background: '#161b22' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 600 }}>
              <thead>
                <tr>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', fontWeight: 600, color: '#8b949e', background: '#21262d', borderBottom: '1px solid #30363d' }}>MPN / cache key</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', fontWeight: 600, color: '#8b949e', background: '#21262d', borderBottom: '1px solid #30363d' }}>Source</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', fontWeight: 600, color: '#8b949e', background: '#21262d', borderBottom: '1px solid #30363d' }}>URL</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', fontWeight: 600, color: '#8b949e', background: '#21262d', borderBottom: '1px solid #30363d' }}>Last updated</th>
                </tr>
              </thead>
              <tbody>
                {data.map((row) => (
                  <tr key={row.id} style={{ borderBottom: '1px solid #21262d' }}>
                    <td style={{ padding: '0.5rem 0.75rem', color: '#e6edf3', fontFamily: 'SFMono-Regular, ui-monospace, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace' }}>
                      {row.cache_key}
                    </td>
                    <td style={{ padding: '0.5rem 0.75rem', color: '#e6edf3', textTransform: 'capitalize' }}>{row.source}</td>
                    <td style={{ padding: '0.5rem 0.75rem', color: '#58a6ff', maxWidth: 260, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                      {row.url ? (
                        <a
                          href={row.url}
                          target="_blank"
                          rel="noreferrer"
                          style={{ color: 'inherit', textDecoration: 'none' }}
                          title={row.url}
                        >
                          {row.url}
                        </a>
                      ) : (
                        <span style={{ color: '#8b949e' }}>—</span>
                      )}
                    </td>
                    <td style={{ padding: '0.5rem 0.75rem', color: '#8b949e' }}>{formatTime(row.updated_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '0.75rem', marginTop: '1rem' }}>
            <span style={{ color: '#8b949e', fontSize: '0.875rem' }}>
              {total} entr{total === 1 ? 'y' : 'ies'}
            </span>
            <select
              value={perPage}
              onChange={(e) => {
                const val = Number(e.target.value) || DEFAULT_PAGE_SIZE;
                setPerPage(val);
                setPage(1);
              }}
              style={{
                padding: '0.35rem 0.5rem',
                background: '#161b22',
                border: '1px solid #30363d',
                borderRadius: 6,
                color: '#e6edf3',
              }}
            >
              {PAGE_SIZE_OPTIONS.map((n) => (
                <option key={n} value={n}>
                  {n} per page
                </option>
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

