import React, { useEffect, useState } from 'react';
import { apiGet } from '../api';

const API_QUEUE = '/api/procurement/queue';
const API_EVIDENCE = '/api/procurement/evidence';

function formatNum(n) {
  if (n == null || Number.isNaN(n)) return '—';
  return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 });
}

export default function ResearchEvidence() {
  const [queue, setQueue] = useState([]);
  const [selectedTaskId, setSelectedTaskId] = useState('');
  const [evidence, setEvidence] = useState(null);
  const [loadingQueue, setLoadingQueue] = useState(true);
  const [loadingEvidence, setLoadingEvidence] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    setLoadingQueue(true);
    apiGet(API_QUEUE + '?per_page=100')
      .then((res) => {
        if (!cancelled) setQueue(res.data ?? []);
      })
      .catch(() => {
        if (!cancelled) setQueue([]);
      })
      .finally(() => {
        if (!cancelled) setLoadingQueue(false);
      });
    return () => { cancelled = true; };
  }, []);

  useEffect(() => {
    if (!selectedTaskId) {
      setEvidence(null);
      return;
    }
    let cancelled = false;
    setLoadingEvidence(true);
    setError(null);
    apiGet(`${API_EVIDENCE}?task_id=${encodeURIComponent(selectedTaskId)}`)
      .then((res) => {
        if (!cancelled) setEvidence(res);
      })
      .catch((err) => {
        if (!cancelled) setError(err?.message || 'Failed to load evidence');
        if (!cancelled) setEvidence(null);
      })
      .finally(() => {
        if (!cancelled) setLoadingEvidence(false);
      });
    return () => { cancelled = true; };
  }, [selectedTaskId]);

  return (
    <div className="app-main-inner" style={{ padding: '1.5rem' }}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Research Evidence</h1>

      <div style={{ marginBottom: '1rem' }}>
        <label style={{ display: 'block', marginBottom: '0.35rem', color: '#8b949e', fontSize: '0.875rem' }}>
          Select task
        </label>
        <select
          value={selectedTaskId}
          onChange={(e) => setSelectedTaskId(e.target.value)}
          disabled={loadingQueue}
          style={{
            padding: '0.35rem 0.5rem',
            background: '#161b22',
            border: '1px solid #30363d',
            borderRadius: 6,
            color: '#e6edf3',
            minWidth: 280,
          }}
        >
          <option value="">— Choose a task —</option>
          {queue.map((t) => (
            <option key={t.id} value={t.id}>
              #{t.id} – {t.item?.internal_part_number ?? '?'} – {t.supplier?.name ?? '?'}
            </option>
          ))}
        </select>
      </div>

      {error && <p style={{ color: '#f85149', marginBottom: '1rem' }}>{error}</p>}

      {loadingEvidence && <p style={{ color: '#8b949e' }}>Loading evidence…</p>}

      {evidence && !loadingEvidence && (
        <>
          <div style={{ background: '#161b22', border: '1px solid #30363d', borderRadius: 8, padding: '1rem', marginBottom: '1rem' }}>
            <h3 style={{ fontSize: '0.9375rem', fontWeight: 600, color: '#e6edf3', marginBottom: '0.5rem' }}>Task</h3>
            <table style={{ fontSize: '0.875rem', color: '#e6edf3' }}>
              <tbody>
                <tr><td style={{ paddingRight: '1rem', color: '#8b949e' }}>ID</td><td>{evidence.task?.id}</td></tr>
                <tr><td style={{ paddingRight: '1rem', color: '#8b949e' }}>Status</td><td>{evidence.task?.status}</td></tr>
                <tr><td style={{ paddingRight: '1rem', color: '#8b949e' }}>Type</td><td>{evidence.task?.task_type}</td></tr>
                <tr><td style={{ paddingRight: '1rem', color: '#8b949e' }}>Item</td><td>{evidence.task?.item?.internal_part_number} – {evidence.task?.item?.description}</td></tr>
                <tr><td style={{ paddingRight: '1rem', color: '#8b949e' }}>Supplier</td><td>{evidence.task?.supplier?.name}</td></tr>
                <tr><td style={{ paddingRight: '1rem', color: '#8b949e' }}>Notes</td><td>{evidence.task?.notes ?? '—'}</td></tr>
              </tbody>
            </table>
          </div>

          <h3 style={{ fontSize: '0.9375rem', fontWeight: 600, color: '#e6edf3', marginBottom: '0.5rem' }}>Price findings</h3>
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', color: '#e6edf3', fontSize: '0.875rem' }}>
              <thead>
                <tr style={{ borderBottom: '1px solid #30363d' }}>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Provider</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Matched MPN</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Currency</th>
                  <th style={{ textAlign: 'right', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Min unit price</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Match score</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Accepted</th>
                </tr>
              </thead>
              <tbody>
                {(evidence.price_findings ?? []).length === 0 ? (
                  <tr>
                    <td colSpan={6} style={{ padding: '1rem', color: '#8b949e' }}>No findings.</td>
                  </tr>
                ) : (
                  evidence.price_findings.map((f) => (
                    <tr key={f.id} style={{ borderBottom: '1px solid #30363d' }}>
                      <td style={{ padding: '0.5rem 0.75rem' }}>{f.provider}</td>
                      <td style={{ padding: '0.5rem 0.75rem' }}>{f.matched_mpn ?? '—'}</td>
                      <td style={{ padding: '0.5rem 0.75rem' }}>{f.currency ?? '—'}</td>
                      <td style={{ padding: '0.5rem 0.75rem', textAlign: 'right' }}>{formatNum(f.min_unit_price)}</td>
                      <td style={{ padding: '0.5rem 0.75rem' }}>{f.match_score != null ? f.match_score : '—'}</td>
                      <td style={{ padding: '0.5rem 0.75rem' }}>{f.accepted ? 'Yes' : 'No'}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}
