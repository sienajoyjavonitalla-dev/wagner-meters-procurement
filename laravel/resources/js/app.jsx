import React from 'react';
import { createRoot } from 'react-dom/client';
import AppRouter from './AppRouter';

const container = document.getElementById('app');
if (container) {
  try {
    const root = createRoot(container);
    root.render(<AppRouter />);
  } catch (err) {
    container.innerHTML = '<div style="padding:1.5rem;color:#e6edf3;"><p>Dashboard failed to load.</p><p style="color:#8b949e;font-size:0.875rem;">Open the browser console (F12) for errors. Ensure you ran <code>npm run dev</code> or <code>npm run build</code>.</p></div>';
    console.error('Procurement app mount error:', err);
  }
}
