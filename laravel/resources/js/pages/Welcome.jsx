import React from 'react';
import { Link } from 'react-router-dom';

const cardStyle = {
  background: '#161b22',
  border: '1px solid #30363d',
  borderRadius: 8,
  padding: '2rem',
  maxWidth: 420,
  width: '100%',
};
const titleStyle = { fontSize: '1.5rem', fontWeight: 600, color: '#e6edf3', marginBottom: '0.5rem' };
const textStyle = { color: '#8b949e', fontSize: '0.9375rem', marginBottom: '1.5rem' };
const linkStyle = { color: '#58a6ff', textDecoration: 'none', marginRight: '1rem' };
const linkButtonStyle = { ...linkStyle, display: 'inline-block', padding: '0.5rem 1rem', background: 'rgba(88,166,255,0.12)', borderRadius: 6 };

export default function Welcome() {
  return (
    <div style={{ minHeight: '100vh', background: '#0d1117', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '2rem' }}>
      <div style={cardStyle}>
        <h1 style={titleStyle}>Procurement</h1>
        <p style={textStyle}>
          Research and compare vendor pricing. Upload data, run research, and review results in one place.
        </p>
        <div>
          <Link to="/login" style={linkButtonStyle}>Log in</Link>
          <Link to="/register" style={linkStyle}>Register</Link>
        </div>
      </div>
    </div>
  );
}
