import React, { useEffect, useState } from 'react';
import { apiGet } from '../api';

const API = '/api/procurement/vendor-progress';

export default function VendorProgress() {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    apiGet(API)
      .then((res) => {
        if (!cancelled) setData(res.data ?? []);
      })
      .catch((err) => {
        if (!cancelled) setError(err?.message || 'Failed to load vendor progress');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  }, []);

  if (loading) {
    return (
      <div className="app-main-inner" style={{ padding: '1.5rem' }}>
        <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Vendor Progress</h1>
        <p style={{ color: '#8b949e' }}>Loading…</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="app-main-inner" style={{ padding: '1.5rem' }}>
        <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Vendor Progress</h1>
        <p style={{ color: '#f85149' }}>{error}</p>
      </div>
    );
  }

  return (
    <div className="app-main-inner" style={{ padding: '1.5rem' }}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Vendor Progress</h1>
      <p style={{ color: '#8b949e', marginBottom: '1rem' }}>Per-vendor task counts and processed percentage.</p>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '1rem', marginBottom: '1.5rem' }}>
        {data.map((row) => (
          <div
            key={row.supplier_id}
            style={{
              background: '#161b22',
              border: '1px solid #30363d',
              borderRadius: 8,
              padding: '1rem',
            }}
          >
            <div style={{ fontSize: '0.875rem', fontWeight: 600, color: '#e6edf3', marginBottom: '0.5rem' }}>
              {row.supplier_name || 'Unknown'}
            </div>
            <div style={{ fontSize: '0.8125rem', color: '#8b949e' }}>
              {row.task_processed} / {row.task_total} tasks ({row.processed_pct}% done)
            </div>
          </div>
        ))}
      </div>

      <div style={{ overflowX: 'auto' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse', color: '#e6edf3', fontSize: '0.875rem' }}>
          <thead>
            <tr style={{ borderBottom: '1px solid #30363d' }}>
              <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Vendor</th>
              <th style={{ textAlign: 'right', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Total tasks</th>
              <th style={{ textAlign: 'right', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Processed</th>
              <th style={{ textAlign: 'right', padding: '0.5rem 0.75rem', color: '#8b949e' }}>%</th>
            </tr>
          </thead>
          <tbody>
            {data.length === 0 ? (
              <tr>
                <td colSpan={4} style={{ padding: '1rem', color: '#8b949e' }}>No vendor data. Run a data import and build the queue.</td>
              </tr>
            ) : (
              data.map((row) => (
                <tr key={row.supplier_id} style={{ borderBottom: '1px solid #30363d' }}>
                  <td style={{ padding: '0.5rem 0.75rem' }}>{row.supplier_name}</td>
                  <td style={{ padding: '0.5rem 0.75rem', textAlign: 'right' }}>{row.task_total}</td>
                  <td style={{ padding: '0.5rem 0.75rem', textAlign: 'right' }}>{row.task_processed}</td>
                  <td style={{ padding: '0.5rem 0.75rem', textAlign: 'right' }}>{row.processed_pct}%</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
