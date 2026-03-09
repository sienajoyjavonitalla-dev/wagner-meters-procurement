import React from 'react';

/**
 * Shared icon wrapper for action buttons.
 * Keeps icon sizing and spacing consistent across pages.
 */
export default function ButtonIcon({ children, size = 16, marginRight = '0.35rem' }) {
  return (
    <span
      className="employees-btn-icon"
      aria-hidden="true"
      style={{ width: size, height: size, display: 'inline-flex', marginRight }}
    >
      {children}
    </span>
  );
}
