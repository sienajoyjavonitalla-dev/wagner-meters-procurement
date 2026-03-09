import React from 'react';
import { chartTheme } from '../../theme/chartTheme';

/**
 * Wrapper for Recharts that applies dark theme. Use for Phase 4.2 Overview and other views.
 */
export default function ChartContainer({ title, children, style = {} }) {
  return (
    <div
      style={{
        background: '#161b22',
        border: '1px solid #30363d',
        borderRadius: 8,
        padding: '1.5rem',
        marginBottom: '1rem',
        ...style,
      }}
    >
      {title && (
        <h3 style={{ fontSize: '1rem', fontWeight: 600, color: chartTheme.text, marginBottom: '1rem' }}>
          {title}
        </h3>
      )}
      <div style={{ color: chartTheme.text }}>{children}</div>
    </div>
  );
}
