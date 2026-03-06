import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { getUser, apiPostAuth } from '../api';

const cardStyle = { background: '#161b22', border: '1px solid #30363d', borderRadius: 8, padding: '2rem', maxWidth: 420, width: '100%' };
const titleStyle = { fontSize: '1.25rem', fontWeight: 600, color: '#e6edf3', marginBottom: '1rem' };
const labelStyle = { display: 'block', fontSize: '0.875rem', fontWeight: 500, color: '#e6edf3', marginBottom: '0.375rem' };
const inputStyle = { width: '100%', padding: '0.5rem', fontSize: '0.875rem', color: '#e6edf3', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, boxSizing: 'border-box' };
const errorStyle = { fontSize: '0.875rem', color: '#f85149', marginTop: '0.25rem' };
const linkStyle = { color: '#58a6ff', fontSize: '0.875rem' };

export default function Register() {
  const navigate = useNavigate();
  const [first_name, setFirst_name] = useState('');
  const [last_name, setLast_name] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [password_confirmation, setPassword_confirmation] = useState('');
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
    formData.set('first_name', first_name);
    formData.set('last_name', last_name);
    formData.set('email', email);
    formData.set('password', password);
    formData.set('password_confirmation', password_confirmation);
    const result = await apiPostAuth('/register', formData);
    setSubmitting(false);
    if (result.redirect) return;
    if (!result.ok) {
      setErrors(result.errors || {});
      return;
    }
  }

  return (
    <div style={{ minHeight: '100vh', background: '#0d1117', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '2rem' }}>
      <div style={cardStyle}>
        <h1 style={titleStyle}>Register</h1>
        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <div>
            <label style={labelStyle}>First name</label>
            <input type="text" name="first_name" value={first_name} onChange={(e) => setFirst_name(e.target.value)} required autoFocus autoComplete="given-name" style={inputStyle} />
            {errors.first_name && <p style={errorStyle}>{Array.isArray(errors.first_name) ? errors.first_name[0] : errors.first_name}</p>}
          </div>
          <div>
            <label style={labelStyle}>Last name</label>
            <input type="text" name="last_name" value={last_name} onChange={(e) => setLast_name(e.target.value)} required autoComplete="family-name" style={inputStyle} />
            {errors.last_name && <p style={errorStyle}>{Array.isArray(errors.last_name) ? errors.last_name[0] : errors.last_name}</p>}
          </div>
          <div>
            <label style={labelStyle}>Email</label>
            <input type="email" name="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="username" style={inputStyle} />
            {errors.email && <p style={errorStyle}>{Array.isArray(errors.email) ? errors.email[0] : errors.email}</p>}
          </div>
          <div>
            <label style={labelStyle}>Password</label>
            <input type="password" name="password" value={password} onChange={(e) => setPassword(e.target.value)} required autoComplete="new-password" style={inputStyle} />
            {errors.password && <p style={errorStyle}>{Array.isArray(errors.password) ? errors.password[0] : errors.password}</p>}
          </div>
          <div>
            <label style={labelStyle}>Confirm password</label>
            <input type="password" name="password_confirmation" value={password_confirmation} onChange={(e) => setPassword_confirmation(e.target.value)} required autoComplete="new-password" style={inputStyle} />
            {errors.password_confirmation && <p style={errorStyle}>{Array.isArray(errors.password_confirmation) ? errors.password_confirmation[0] : errors.password_confirmation}</p>}
          </div>
          <button type="submit" disabled={submitting} style={{ marginTop: '0.5rem', padding: '0.5rem 1rem', fontSize: '0.875rem', fontWeight: 500, color: '#fff', background: '#238636', border: '1px solid #2ea043', borderRadius: 6, cursor: submitting ? 'wait' : 'pointer' }}>
            {submitting ? 'Registering…' : 'Register'}
          </button>
        </form>
        <p style={{ marginTop: '1.5rem', fontSize: '0.875rem', color: '#8b949e' }}>
          <Link to="/login" style={linkStyle}>Already registered? Log in</Link>
        </p>
      </div>
    </div>
  );
}
