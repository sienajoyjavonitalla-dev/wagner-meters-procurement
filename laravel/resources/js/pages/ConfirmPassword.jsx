import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { getUser, apiBase, getCsrfToken } from '../api';

const cardStyle = { background: '#161b22', border: '1px solid #30363d', borderRadius: 8, padding: '2rem', maxWidth: 420, width: '100%' };
const titleStyle = { fontSize: '1.25rem', fontWeight: 600, color: '#e6edf3', marginBottom: '1rem' };
const labelStyle = { display: 'block', fontSize: '0.875rem', fontWeight: 500, color: '#e6edf3', marginBottom: '0.375rem' };
const inputStyle = { width: '100%', padding: '0.5rem', fontSize: '0.875rem', color: '#e6edf3', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, boxSizing: 'border-box' };
const errorStyle = { fontSize: '0.875rem', color: '#f85149', marginTop: '0.25rem' };
const linkStyle = { color: '#58a6ff', fontSize: '0.875rem' };

export default function ConfirmPassword() {
  const navigate = useNavigate();
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  if (!getUser()) {
    navigate('/login', { replace: true });
    return null;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setErrors({});
    setSubmitting(true);
    const formData = new FormData();
    formData.set('password', password);
    const token = getCsrfToken();
    if (token) formData.append('_token', token);
    const base = apiBase();
    const res = await fetch(`${base.replace(/\/$/, '')}/confirm-password`, {
      method: 'POST',
      body: formData,
      credentials: 'include',
      redirect: 'manual',
      headers: { Accept: 'application/json' },
    });
    setSubmitting(false);
    if (res.status === 302) {
      const loc = res.headers.get('Location') || '/dashboard';
      window.location.href = loc.startsWith('http') ? loc : (base.replace(/\/$/, '') + (loc.startsWith('/') ? loc : '/' + loc));
      return;
    }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) setErrors(data.errors || { password: [data.message || 'Wrong password.'] });
  }

  return (
    <div style={{ minHeight: '100vh', background: '#0d1117', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '2rem' }}>
      <div style={cardStyle}>
        <h1 style={titleStyle}>Confirm password</h1>
        <p style={{ color: '#8b949e', fontSize: '0.9375rem', marginBottom: '1rem' }}>Please confirm your password before continuing.</p>
        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <div>
            <label style={labelStyle}>Password</label>
            <input type="password" name="password" value={password} onChange={(e) => setPassword(e.target.value)} required autoFocus autoComplete="current-password" style={inputStyle} />
            {errors.password && <p style={errorStyle}>{Array.isArray(errors.password) ? errors.password[0] : errors.password}</p>}
          </div>
          <button type="submit" disabled={submitting} style={{ padding: '0.5rem 1rem', fontSize: '0.875rem', fontWeight: 500, color: '#fff', background: '#238636', border: '1px solid #2ea043', borderRadius: 6, cursor: submitting ? 'wait' : 'pointer' }}>
            {submitting ? 'Confirming…' : 'Confirm'}
          </button>
        </form>
        <p style={{ marginTop: '1.5rem', fontSize: '0.875rem', color: '#8b949e' }}>
          <Link to="/dashboard" style={linkStyle}>Back to dashboard</Link>
        </p>
      </div>
    </div>
  );
}
