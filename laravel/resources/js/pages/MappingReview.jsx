import React, { useEffect, useState } from 'react';
import { apiGet } from '../api';
import ButtonIcon from '../components/ui/ButtonIcon';

const API = '/api/procurement/mapping-review';

function toCSV(rows, keys) {
  const header = keys.join(',');
  const escape = (v) => (v == null ? '' : String(v).replace(/"/g, '""'));
  const body = rows.map((r) => keys.map((k) => `"${escape(r[k])}"`).join(',')).join('\n');
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

export default function MappingReview() {
  const [data, setData] = useState({ data: [], mpn_worklist: [] });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    apiGet(API + '?worklist_limit=20')
      .then((res) => {
        if (!cancelled) setData({ data: res.data ?? [], mpn_worklist: res.mpn_worklist ?? [] });
      })
      .catch((err) => {
        if (!cancelled) setError(err?.message || 'Failed to load mapping review');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  }, []);

  const handleDownloadFull = () => {
    const keys = ['id', 'item_id', 'internal_part_number', 'description', 'mpn', 'mapping_status', 'lookup_mode'];
    const rows = data.data.map((m) => ({
      id: m.id,
      item_id: m.item_id,
      internal_part_number: m.internal_part_number,
      description: m.description,
      mpn: m.mpn,
      mapping_status: m.mapping_status,
      lookup_mode: m.lookup_mode,
    }));
    downloadCSV('mapping-review.csv', toCSV(rows, keys));
  };

  if (loading) {
    return (
      <div className="app-main-inner" style={{ padding: '1.5rem' }}>
        <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Mapping Review</h1>
        <p style={{ color: '#8b949e' }}>Loading…</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="app-main-inner" style={{ padding: '1.5rem' }}>
        <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Mapping Review</h1>
        <p style={{ color: '#f85149' }}>{error}</p>
      </div>
    );
  }

  const review = data.data;
  const worklist = data.mpn_worklist;

  return (
    <div className="app-main-inner" style={{ padding: '1.5rem' }}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Mapping Review</h1>
      <p style={{ color: '#8b949e', marginBottom: '1rem' }}>
        Items needing mapping or MPN fill. Top 20 in worklist below.
      </p>
      <button
        type="button"
        onClick={handleDownloadFull}
        style={{
          padding: '0.35rem 0.75rem',
          background: '#238636',
          border: '1px solid #2ea043',
          borderRadius: 6,
          color: '#fff',
          cursor: 'pointer',
          fontSize: '0.875rem',
          marginBottom: '1rem',
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
        Download full CSV
      </button>

      <h2 style={{ fontSize: '1.125rem', fontWeight: 600, color: '#e6edf3', marginBottom: '0.5rem' }}>MPN worklist (top 20)</h2>
      <div style={{ overflowX: 'auto', marginBottom: '1.5rem' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse', color: '#e6edf3', fontSize: '0.875rem' }}>
          <thead>
            <tr style={{ borderBottom: '1px solid #30363d' }}>
              <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Item ID</th>
              <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Part number</th>
              <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Description</th>
              <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>MPN</th>
              <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Status</th>
              <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Lookup mode</th>
            </tr>
          </thead>
          <tbody>
            {worklist.length === 0 ? (
              <tr>
                <td colSpan={6} style={{ padding: '1rem', color: '#8b949e' }}>No items in worklist.</td>
              </tr>
            ) : (
              worklist.map((m) => (
                <tr key={m.id} style={{ borderBottom: '1px solid #30363d' }}>
                  <td style={{ padding: '0.5rem 0.75rem' }}>{m.item_id}</td>
                  <td style={{ padding: '0.5rem 0.75rem' }}>{m.internal_part_number ?? '—'}</td>
                  <td style={{ padding: '0.5rem 0.75rem', maxWidth: 240 }}>{m.description ?? '—'}</td>
                  <td style={{ padding: '0.5rem 0.75rem' }}>{m.mpn ?? '—'}</td>
                  <td style={{ padding: '0.5rem 0.75rem' }}>{m.mapping_status ?? '—'}</td>
                  <td style={{ padding: '0.5rem 0.75rem' }}>{m.lookup_mode ?? '—'}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <h2 style={{ fontSize: '1.125rem', fontWeight: 600, color: '#e6edf3', marginBottom: '0.5rem' }}>Full mapping review ({review.length} items)</h2>
      <div style={{ overflowX: 'auto' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse', color: '#e6edf3', fontSize: '0.875rem' }}>
          <thead>
            <tr style={{ borderBottom: '1px solid #30363d' }}>
              <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Item ID</th>
              <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Part number</th>
              <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Description</th>
              <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>MPN</th>
              <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Status</th>
              <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Lookup mode</th>
            </tr>
          </thead>
          <tbody>
            {review.length === 0 ? (
              <tr>
                <td colSpan={6} style={{ padding: '1rem', color: '#8b949e' }}>No items needing review.</td>
              </tr>
            ) : (
              review.slice(0, 100).map((m) => (
                <tr key={m.id} style={{ borderBottom: '1px solid #30363d' }}>
                  <td style={{ padding: '0.5rem 0.75rem' }}>{m.item_id}</td>
                  <td style={{ padding: '0.5rem 0.75rem' }}>{m.internal_part_number ?? '—'}</td>
                  <td style={{ padding: '0.5rem 0.75rem', maxWidth: 240 }}>{m.description ?? '—'}</td>
                  <td style={{ padding: '0.5rem 0.75rem' }}>{m.mpn ?? '—'}</td>
                  <td style={{ padding: '0.5rem 0.75rem' }}>{m.mapping_status ?? '—'}</td>
                  <td style={{ padding: '0.5rem 0.75rem' }}>{m.lookup_mode ?? '—'}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      {review.length > 100 && (
        <p style={{ marginTop: '0.5rem', color: '#8b949e', fontSize: '0.8125rem' }}>Showing first 100. Use CSV download for full list.</p>
      )}
    </div>
  );
}
