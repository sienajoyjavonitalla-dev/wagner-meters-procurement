import React, { useState } from 'react';
import { getUser, getCsrfToken } from '../api';

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
const boxStyle = { padding: '1.5rem', background: '#161b22', border: '1px solid #30363d', borderRadius: 8 };

export default function Profile() {
  const user = getUser() || {};
  const [first_name, setFirst_name] = useState(user.first_name ?? '');
  const [last_name, setLast_name] = useState(user.last_name ?? '');
  const [email, setEmail] = useState(user.email ?? '');
  const [saved, setSaved] = useState(false);
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setErrors({});
    setSaved(false);
    setSubmitting(true);
    const form = e.target;
    const formData = new FormData(form);
    formData.set('_method', 'PATCH');
    try {
      const base = window.__PROCUREMENT__?.apiBase ?? '';
      const res = await fetch(`${base}/profile`, {
        method: 'POST',
        body: formData,
        credentials: 'include',
        redirect: 'manual',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await res.json().catch(() => ({}));
      if (res.status === 302 || res.status === 303 || res.ok) {
        setSaved(true);
        setTimeout(() => setSaved(false), 2000);
        return;
      }
      const errs = data.errors || {};
      if (Object.keys(errs).length === 0 && data.message) errs.form = data.message;
      if (Object.keys(errs).length === 0) errs.form = 'Failed to save.';
      setErrors(errs);
    } catch (err) {
      setErrors({ form: err.message || 'Failed to save.' });
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="app-main-inner" style={{ padding: '1.5rem 2rem' }}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '1.5rem', color: '#e6edf3' }}>Profile</h1>
      <div style={{ maxWidth: '42rem', display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
        <div style={boxStyle}>
          <h2 style={{ fontSize: '1rem', fontWeight: 600, color: '#e6edf3', marginBottom: '0.5rem' }}>Profile Information</h2>
          <p style={{ fontSize: '0.875rem', color: '#8b949e', marginBottom: '1rem' }}>Update your account's profile information and email address.</p>
          <form onSubmit={handleSubmit}>
            <input type="hidden" name="_token" value={getCsrfToken()} />
            <div style={{ marginBottom: '1rem' }}>
              <label style={labelStyle}>First Name</label>
              <input type="text" name="first_name" value={first_name} onChange={(e) => setFirst_name(e.target.value)} required autoComplete="given-name" style={inputStyle} />
              {errors.first_name && <p style={{ fontSize: '0.875rem', color: '#f85149', marginTop: '0.25rem' }}>{Array.isArray(errors.first_name) ? errors.first_name[0] : errors.first_name}</p>}
            </div>
            <div style={{ marginBottom: '1rem' }}>
              <label style={labelStyle}>Last Name</label>
              <input type="text" name="last_name" value={last_name} onChange={(e) => setLast_name(e.target.value)} required autoComplete="family-name" style={inputStyle} />
              {errors.last_name && <p style={{ fontSize: '0.875rem', color: '#f85149', marginTop: '0.25rem' }}>{Array.isArray(errors.last_name) ? errors.last_name[0] : errors.last_name}</p>}
            </div>
            <div style={{ marginBottom: '1rem' }}>
              <label style={labelStyle}>Email</label>
              <input type="email" name="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="username" style={inputStyle} />
              {errors.email && <p style={{ fontSize: '0.875rem', color: '#f85149', marginTop: '0.25rem' }}>{Array.isArray(errors.email) ? errors.email[0] : errors.email}</p>}
            </div>
            {errors.form && <p style={{ fontSize: '0.875rem', color: '#f85149', marginBottom: '0.5rem' }}>{Array.isArray(errors.form) ? errors.form[0] : errors.form}</p>}
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <button
                type="submit"
                disabled={submitting}
                style={{ padding: '0.5rem 1rem', fontSize: '0.875rem', fontWeight: 500, color: '#fff', background: '#238636', border: '1px solid #2ea043', borderRadius: 6, cursor: submitting ? 'wait' : 'pointer' }}
              >
                {submitting ? 'Saving…' : 'Save'}
              </button>
              {saved && <span style={{ fontSize: '0.875rem', color: '#3fb950' }}>Saved.</span>}
            </div>
          </form>
        </div>
        <div style={boxStyle}>
          <h2 style={{ fontSize: '1rem', fontWeight: 600, color: '#e6edf3', marginBottom: '0.5rem' }}>Password &amp; account</h2>
          <p style={{ fontSize: '0.875rem', color: '#8b949e' }}>To change your password or delete your account, use the link below (opens in the same tab).</p>
          <a href="/profile/full" style={{ marginTop: '0.75rem', display: 'inline-block', fontSize: '0.875rem', color: '#58a6ff' }}>Open full profile page (password, delete account)</a>
        </div>
      </div>
    </div>
  );
}
