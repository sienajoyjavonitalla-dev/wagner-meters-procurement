import React, { useState } from 'react';
import { apiPostFormData } from '../api';

const inputStyle = {
  width: '100%',
  padding: '0.5rem',
  fontSize: '0.875rem',
  color: '#e6edf3',
  background: '#0d1117',
  border: '1px solid #30363d',
  borderRadius: '6px',
};
const labelStyle = { display: 'block', fontSize: '0.875rem', fontWeight: 500, color: '#e6edf3', marginBottom: '0.375rem' };
const hintStyle = { fontSize: '0.75rem', color: '#8b949e', marginTop: '0.25rem' };

export default function DataImport() {
  const [success, setSuccess] = useState(null);
  const [errors, setErrors] = useState([]);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setErrors([]);
    setSuccess(null);
    const form = e.target;
    const formData = new FormData(form);
    setSubmitting(true);
    try {
      await apiPostFormData('/data-import', formData);
      setSuccess('Import queued. Refresh the page in a moment to see status.');
      form.reset();
    } catch (err) {
      if (err.errors) {
        const list = typeof err.errors === 'object'
          ? Object.entries(err.errors).flatMap(([k, v]) => (Array.isArray(v) ? v : [v]).map((m) => `${k}: ${m}`))
          : [String(err.errors)];
        setErrors(list);
      } else {
        setErrors([err.message || 'Upload failed.']);
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="app-main-inner" style={{ padding: '1.5rem 2rem' }}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Data Import</h1>
      <p style={{ color: '#8b949e', marginBottom: '1.5rem', fontSize: '0.9375rem' }}>
        Upload inventory, vendor priority, item spread, and optional MPN map. This replaces the previous snapshot.
      </p>

      {success && (
        <div style={{ marginBottom: '1rem', padding: '0.75rem 1rem', borderRadius: 6, background: 'rgba(46, 160, 67, 0.15)', color: '#3fb950', fontSize: '0.875rem' }}>
          {success}
        </div>
      )}
      {errors.length > 0 && (
        <ul style={{ marginBottom: '1rem', paddingLeft: '1.25rem', color: '#f85149', fontSize: '0.875rem' }}>
          {errors.map((msg, i) => <li key={i}>{msg}</li>)}
        </ul>
      )}

      <div style={{ background: '#161b22', border: '1px solid #30363d', borderRadius: 8, padding: '1.5rem' }}>
        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
          <input type="hidden" name="_token" value={window.__PROCUREMENT__?.csrfToken || ''} />
          <div>
            <label style={labelStyle}>Inventory (Excel) *</label>
            <input type="file" name="inventory" accept=".xlsx,.xls" required style={inputStyle} />
            <p style={hintStyle}>Required columns: Transaction Date, Vendor Name, Item ID, Description, Ext. Cost, Unit Cost, Quantity</p>
          </div>
          <div>
            <label style={labelStyle}>Vendor priority (CSV/Excel) *</label>
            <input type="file" name="vendor_priority" accept=".csv,.xlsx,.xls" required style={inputStyle} />
            <p style={hintStyle}>Required columns: Vendor Name, priority_rank</p>
          </div>
          <div>
            <label style={labelStyle}>Item spread (CSV/Excel) *</label>
            <input type="file" name="item_spread" accept=".csv,.xlsx,.xls" required style={inputStyle} />
            <p style={hintStyle}>Required column: Item ID</p>
          </div>
          <div>
            <label style={labelStyle}>MPN map (CSV/Excel, optional)</label>
            <input type="file" name="mpn_map" accept=".csv,.xlsx,.xls" style={inputStyle} />
            <p style={hintStyle}>Columns: Item ID, mpn</p>
          </div>
          <button
            type="submit"
            disabled={submitting}
            style={{ alignSelf: 'flex-start', padding: '0.5rem 1rem', fontSize: '0.875rem', fontWeight: 500, color: '#fff', background: '#238636', border: '1px solid #2ea043', borderRadius: 6, cursor: submitting ? 'wait' : 'pointer' }}
          >
            {submitting ? 'Uploading…' : 'Upload and import'}
          </button>
        </form>
      </div>
    </div>
  );
}
