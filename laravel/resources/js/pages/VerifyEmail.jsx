import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { getUser, apiBase, getCsrfToken } from '../api';

const cardStyle = { background: '#161b22', border: '1px solid #30363d', borderRadius: 8, padding: '2rem', maxWidth: 420, width: '100%' };
const titleStyle = { fontSize: '1.25rem', fontWeight: 600, color: '#e6edf3', marginBottom: '0.5rem' };
const textStyle = { color: '#8b949e', fontSize: '0.9375rem', marginBottom: '1rem' };
const linkStyle = { color: '#58a6ff', fontSize: '0.875rem' };

export default function VerifyEmail() {
  const navigate = useNavigate();
  const [status, setStatus] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  if (!getUser()) {
    navigate('/login', { replace: true });
    return null;
  }

  async function handleResend(e) {
    e.preventDefault();
    setSubmitting(true);
    const formData = new FormData();
    const token = getCsrfToken();
    if (token) formData.append('_token', token);
    const base = apiBase();
    const res = await fetch(`${base.replace(/\/$/, '')}/email/verification-notification`, {
      method: 'POST',
      body: formData,
      credentials: 'include',
      headers: { Accept: 'application/json' },
    });
    setSubmitting(false);
    if (res.ok) setStatus('Verification link sent. Check your email.');
  }

  return (
    <div style={{ minHeight: '100vh', background: '#0d1117', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '2rem' }}>
      <div style={cardStyle}>
        <h1 style={titleStyle}>Verify your email</h1>
        <p style={textStyle}>Thanks for signing up. Click the link we sent to your email to verify your address.</p>
        {status && <p style={{ fontSize: '0.875rem', color: '#3fb950', marginBottom: '1rem' }}>{status}</p>}
        <button type="button" onClick={handleResend} disabled={submitting} style={{ padding: '0.5rem 1rem', fontSize: '0.875rem', fontWeight: 500, color: '#fff', background: '#238636', border: '1px solid #2ea043', borderRadius: 6, cursor: submitting ? 'wait' : 'pointer' }}>
          {submitting ? 'Sending…' : 'Resend verification email'}
        </button>
        <p style={{ marginTop: '1.5rem', fontSize: '0.875rem', color: '#8b949e' }}>
          <Link to="/dashboard" style={linkStyle}>Go to dashboard</Link>
        </p>
      </div>
    </div>
  );
}
