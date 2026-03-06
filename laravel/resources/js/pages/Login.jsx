import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { getUser, apiPostAuth, apiBase } from '../api';

const cardStyle = { background: '#161b22', border: '1px solid #30363d', borderRadius: 8, padding: '2rem', maxWidth: 420, width: '100%' };
const titleStyle = { fontSize: '1.25rem', fontWeight: 600, color: '#e6edf3', marginBottom: '1rem' };
const labelStyle = { display: 'block', fontSize: '0.875rem', fontWeight: 500, color: '#e6edf3', marginBottom: '0.375rem' };
const inputStyle = { width: '100%', padding: '0.5rem', fontSize: '0.875rem', color: '#e6edf3', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, boxSizing: 'border-box' };
const errorStyle = { fontSize: '0.875rem', color: '#f85149', marginTop: '0.25rem' };
const linkStyle = { color: '#58a6ff', fontSize: '0.875rem' };

export default function Login() {
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(false);
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  if (getUser()) {
    navigate('/dashboard', { replace: true });
    return null;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setErrors({});
    setSubmitting(true);
    const formData = new FormData();
    formData.set('email', email);
    formData.set('password', password);
    if (remember) formData.set('remember', '1');
    const result = await apiPostAuth('/login', formData);
    console.log('[Login] apiPostAuth result:', result);
    setSubmitting(false);
    if (result.redirect) {
      console.log('[Login] result.redirect is true, returning (redirect should happen in apiPostAuth)');
      return;
    }
    if (!result.ok) {
      console.log('[Login] result not ok, setting errors');
      setErrors(result.errors || { email: result.message ? [result.message] : [] });
      return;
    }
    if (result.ok) {
      const base = apiBase().replace(/\/$/, '');
      const dashboardUrl = base + '/dashboard';
      console.log('[Login] result.ok, redirecting to', dashboardUrl);
      window.location.href = dashboardUrl;
    } else {
      console.log('[Login] no redirect branch matched, result:', result);
    }
  }

  return (
    <div style={{ minHeight: '100vh', background: '#0d1117', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '2rem' }}>
      <div style={cardStyle}>
        <h1 style={titleStyle}>Log in</h1>
        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <div>
            <label style={labelStyle}>Email</label>
            <input type="email" name="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoFocus autoComplete="username" style={inputStyle} />
            {errors.email && <p style={errorStyle}>{Array.isArray(errors.email) ? errors.email[0] : errors.email}</p>}
          </div>
          <div>
            <label style={labelStyle}>Password</label>
            <input type="password" name="password" value={password} onChange={(e) => setPassword(e.target.value)} required autoComplete="current-password" style={inputStyle} />
            {errors.password && <p style={errorStyle}>{Array.isArray(errors.password) ? errors.password[0] : errors.password}</p>}
          </div>
          <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.875rem', color: '#8b949e' }}>
            <input type="checkbox" checked={remember} onChange={(e) => setRemember(e.target.checked)} name="remember" />
            Remember me
          </label>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '0.5rem' }}>
            <Link to="/forgot-password" style={linkStyle}>Forgot your password?</Link>
            <button type="submit" disabled={submitting} style={{ padding: '0.5rem 1rem', fontSize: '0.875rem', fontWeight: 500, color: '#fff', background: '#238636', border: '1px solid #2ea043', borderRadius: 6, cursor: submitting ? 'wait' : 'pointer' }}>
              {submitting ? 'Logging in…' : 'Log in'}
            </button>
          </div>
        </form>
        <p style={{ marginTop: '1.5rem', fontSize: '0.875rem', color: '#8b949e' }}>
          <Link to="/register" style={linkStyle}>Register</Link> if you don’t have an account.
        </p>
      </div>
    </div>
  );
}
