import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { getUser, apiBase, getCsrfToken } from '../api';

const cardStyle = { background: '#161b22', border: '1px solid #30363d', borderRadius: 8, padding: '2rem', maxWidth: 420, width: '100%' };
const titleStyle = { fontSize: '1.25rem', fontWeight: 600, color: '#e6edf3', marginBottom: '0.5rem' };
const textStyle = { color: '#8b949e', fontSize: '0.9375rem', marginBottom: '1rem' };
const labelStyle = { display: 'block', fontSize: '0.875rem', fontWeight: 500, color: '#e6edf3', marginBottom: '0.375rem' };
const inputStyle = { width: '100%', padding: '0.5rem', fontSize: '0.875rem', color: '#e6edf3', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, boxSizing: 'border-box' };
const errorStyle = { fontSize: '0.875rem', color: '#f85149', marginTop: '0.25rem' };
const successStyle = { fontSize: '0.875rem', color: '#3fb950', marginTop: '0.5rem' };
const linkStyle = { color: '#58a6ff', fontSize: '0.875rem' };

export default function ForgotPassword() {
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [errors, setErrors] = useState({});
  const [status, setStatus] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  if (getUser()) {
    navigate('/dashboard', { replace: true });
    return null;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setErrors({});
    setStatus(null);
    setSubmitting(true);
    const formData = new FormData();
    formData.set('email', email);
    const token = getCsrfToken();
    if (token) formData.append('_token', token);
    const base = apiBase();
    const res = await fetch(`${base.replace(/\/$/, '')}/forgot-password`, {
      method: 'POST',
      body: formData,
      credentials: 'include',
      redirect: 'manual',
      headers: { Accept: 'application/json' },
    });
    setSubmitting(false);
    if (res.status === 302) {
      setStatus('Password reset link sent. Check your email.');
      return;
    }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) setErrors(data.errors || { email: [data.message || 'Something went wrong.'] });
    else setStatus('Password reset link sent. Check your email.');
  }

  return (
    <div style={{ minHeight: '100vh', background: '#0d1117', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '2rem' }}>
      <div style={cardStyle}>
        <h1 style={titleStyle}>Forgot password</h1>
        <p style={textStyle}>Enter your email and we’ll send you a link to choose a new password.</p>
        {status && <p style={successStyle}>{status}</p>}
        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <div>
            <label style={labelStyle}>Email</label>
            <input type="email" name="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoFocus style={inputStyle} />
            {errors.email && <p style={errorStyle}>{Array.isArray(errors.email) ? errors.email[0] : errors.email}</p>}
          </div>
          <button type="submit" disabled={submitting} style={{ padding: '0.5rem 1rem', fontSize: '0.875rem', fontWeight: 500, color: '#fff', background: '#238636', border: '1px solid #2ea043', borderRadius: 6, cursor: submitting ? 'wait' : 'pointer' }}>
            {submitting ? 'Sending…' : 'Email password reset link'}
          </button>
        </form>
        <p style={{ marginTop: '1.5rem', fontSize: '0.875rem', color: '#8b949e' }}>
          <Link to="/login" style={linkStyle}>Back to log in</Link>
        </p>
      </div>
    </div>
  );
}
