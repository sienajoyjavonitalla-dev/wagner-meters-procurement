import React from 'react';

export default function BeginnerGuide() {
  const stepStyle = {
    background: '#161b22',
    border: '1px solid #30363d',
    borderRadius: 8,
    padding: '1rem',
    marginBottom: '0.75rem',
  };
  const headingRowStyle = {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    marginBottom: '0.35rem',
    color: '#e6edf3',
  };
  const iconStyle = {
    width: 18,
    height: 18,
    color: '#8b949e',
    flexShrink: 0,
  };

  return (
    <div className="app-main-inner" style={{ padding: '1.5rem', maxWidth: 980 }}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Beginner&apos;s Guide</h1>
      <p style={{ color: '#8b949e', marginBottom: '1rem' }}>
        This page explains the workflow from inventory import to price comparison and savings review.
      </p>

      <h2 style={{ fontSize: '1.125rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Workflow</h2>

      <div style={stepStyle}>
        <h3 style={{ fontSize: '1rem', marginBottom: '0.35rem', color: '#e6edf3' }}>1) Single inventory file import</h3>
        <p style={{ color: '#8b949e', margin: 0 }}>
          Open <strong>Data Import</strong>, upload one inventory file (Excel or CSV). Columns A–V are stored as inventory rows; Mfg Part Number 1–5 (W–AA) are stored as MPNs. Submit and wait for the import to complete.
        </p>
      </div>

      <div style={stepStyle}>
        <h3 style={{ fontSize: '1rem', marginBottom: '0.35rem', color: '#e6edf3' }}>2) Run research (API + Gemini)</h3>
        <p style={{ color: '#8b949e', margin: 0 }}>
          Go to <strong>Run Controls</strong>, set the batch size (default 5), then click <strong>Start run</strong>. For each item, <strong>current vendor</strong> pricing: if the vendor is DigiKey or Mouser, the app uses that vendor’s API first (tries MPNs in order until one returns a price); otherwise it uses Gemini. <strong>Alternative vendors</strong> are always researched via Gemini (other vendors) plus DigiKey and Mouser APIs when they are not the current vendor.
        </p>
      </div>

      <div style={stepStyle}>
        <h3 style={{ fontSize: '1rem', marginBottom: '0.35rem', color: '#e6edf3' }}>3) Monitor run status</h3>
        <p style={{ color: '#8b949e', margin: 0 }}>
          Stay on <strong>Run Controls</strong> and watch the latest run status (pending, running, completed, failed). Polling updates the status automatically.
        </p>
      </div>

      <div style={stepStyle}>
        <h3 style={{ fontSize: '1rem', marginBottom: '0.35rem', color: '#e6edf3' }}>4) Dashboard: queue and savings</h3>
        <p style={{ color: '#8b949e', margin: 0 }}>
          Open <strong>Dashboard</strong> to check queue processed (researched vs pending), needs research count, provider hits (Gemini), and <strong>savings potential per vendor</strong> (based on lowest price per item). Use this to prioritize follow-up and negotiations.
        </p>
      </div>

      <div style={stepStyle}>
        <h3 style={{ fontSize: '1rem', marginBottom: '0.35rem', color: '#e6edf3' }}>5) Item Price Comparison</h3>
        <p style={{ color: '#8b949e', margin: 0 }}>
          Use <strong>Price Comparison</strong> to compare items in three sections: <strong>Item</strong> (Item ID, MPN list, Unit cost, Quantity, Total Cost), <strong>Current Vendor</strong> (Vendor link, Current Site Price, Savings vs Total Cost), and <strong>Alternative Vendor with Lowest Price</strong> (Vendor link, Lowest Price, Savings vs Total Cost). Savings are Total Cost minus (price × quantity). Click the view (eye) icon in the alt-vendor column to open a popup listing all alternative vendors with vendor, price, and savings. Filter by vendor, item ID, or minimum savings; export CSV for procurement review.
        </p>
      </div>

      <div style={stepStyle}>
        <h3 style={{ fontSize: '1rem', marginBottom: '0.35rem', color: '#e6edf3' }}>6) Users and Profile</h3>
        <p style={{ color: '#8b949e', margin: 0 }}>
          <strong>Users</strong> (super admin only) and <strong>Profile</strong> are unchanged. Re-import when you have new inventory data, trigger another research run, and compare updated recommendations before procurement decisions.
        </p>
      </div>

      <h2 style={{ fontSize: '1.125rem', marginBottom: '0.5rem', color: '#e6edf3', marginTop: '1rem' }}>How to use steps (per app page)</h2>

      <div style={stepStyle}>
        <h3 style={headingRowStyle}>
          <svg style={iconStyle} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="3" width="7" height="7" />
            <rect x="14" y="3" width="7" height="7" />
            <rect x="14" y="14" width="7" height="7" />
            <rect x="3" y="14" width="7" height="7" />
          </svg>
          Dashboard
        </h3>
        <ol style={{ margin: 0, paddingLeft: '1.25rem', color: '#8b949e', lineHeight: 1.65 }}>
          <li>Check queue processed (researched vs pending), needs research count, and provider hits (Gemini).</li>
          <li>Review queue status and provider hits.</li>
          <li>Use savings potential per vendor for follow-up and procurement decisions.</li>
        </ol>
      </div>

      <div style={stepStyle}>
        <h3 style={headingRowStyle}>
          <svg style={iconStyle} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
            <polyline points="17 8 12 3 7 8" />
            <line x1="12" x2="12" y1="3" y2="15" />
          </svg>
          Data Import
        </h3>
        <ol style={{ margin: 0, paddingLeft: '1.25rem', color: '#8b949e', lineHeight: 1.65 }}>
          <li>Upload a single inventory file (Excel or CSV) with columns A–V (inventory) and W–AA (MPNs).</li>
          <li>Submit and confirm import success before running research.</li>
        </ol>
      </div>

      <div style={stepStyle}>
        <h3 style={headingRowStyle}>
          <svg style={iconStyle} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
            <polyline points="17 6 23 6 23 12" />
          </svg>
          Price Comparison
        </h3>
        <ol style={{ margin: 0, paddingLeft: '1.25rem', color: '#8b949e', lineHeight: 1.65 }}>
          <li>Use filters: vendor, item ID, or minimum savings.</li>
          <li>Review Item (ID, MPN list, Unit cost, Quantity, Total Cost), Current Vendor (vendor link, site price, savings vs total cost), and Alternative Vendor (vendor link, lowest price, savings vs total cost). Savings = Total Cost − (price × quantity).</li>
          <li>Click the view (eye) icon in the alt-vendor column to see all alternative vendors for that item in a popup (vendor, price, savings).</li>
          <li>Export CSV for procurement review and negotiations.</li>
        </ol>
      </div>

      <div style={stepStyle}>
        <h3 style={headingRowStyle}>
          <svg style={iconStyle} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <polygon points="5 3 19 12 5 21 5 3" />
          </svg>
          Run Controls
        </h3>
        <ol style={{ margin: 0, paddingLeft: '1.25rem', color: '#8b949e', lineHeight: 1.65 }}>
          <li>Set batch size (default 5); optionally save research settings (e.g. Batch size (Gemini)).</li>
          <li>Click <strong>Start run</strong> to trigger a research run (DigiKey/Mouser API when applicable, then Gemini for current and alt vendors). Monitor status (pending, running, completed, failed).</li>
          <li>Adjust settings only if you understand the impact.</li>
        </ol>
      </div>

      <div style={stepStyle}>
        <h3 style={headingRowStyle}>
          <svg style={iconStyle} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
          Users
        </h3>
        <ol style={{ margin: 0, paddingLeft: '1.25rem', color: '#8b949e', lineHeight: 1.65 }}>
          <li>Open this page as <strong>super admin</strong> to manage user access.</li>
          <li>Create users and assign the correct role (Super Admin, Admin, or Viewer).</li>
          <li>Use Edit to update profile details or role, and Delete to remove inactive accounts.</li>
        </ol>
      </div>

      <div style={stepStyle}>
        <h3 style={headingRowStyle}>
          <svg style={iconStyle} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="10" />
            <path d="M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
            <path d="M6.168 18.849A4 4 0 0 1 10 16h4a4 4 0 0 1 3.834 2.855" />
          </svg>
          Profile
        </h3>
        <ol style={{ margin: 0, paddingLeft: '1.25rem', color: '#8b949e', lineHeight: 1.65 }}>
          <li>Confirm your account information is correct.</li>
          <li>Use this page for account maintenance.</li>
          <li>Contact an admin if role/access changes are needed.</li>
        </ol>
      </div>
    </div>
  );
}
