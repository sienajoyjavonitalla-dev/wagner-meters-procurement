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
        This page explains the normal workflow from upload to reviewed savings actions.
      </p>

      <h2 style={{ fontSize: '1.125rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Workflow</h2>

      <div style={stepStyle}>
        <h3 style={{ fontSize: '1rem', marginBottom: '0.35rem', color: '#e6edf3' }}>1) Import files</h3>
        <p style={{ color: '#8b949e', margin: 0 }}>
          Open <strong>Data Import</strong>, upload inventory, vendor priority, item spread, and optional MPN map, then submit.
        </p>
      </div>

      <div style={stepStyle}>
        <h3 style={{ fontSize: '1rem', marginBottom: '0.35rem', color: '#e6edf3' }}>2) Build queue + run research</h3>
        <p style={{ color: '#8b949e', margin: 0 }}>
          Go to <strong>Run Controls</strong>, enable <strong>Build queue first</strong>, choose batch size and fallback mode, then click <strong>Start run</strong>.
        </p>
      </div>

      <div style={stepStyle}>
        <h3 style={{ fontSize: '1rem', marginBottom: '0.35rem', color: '#e6edf3' }}>3) Monitor run status</h3>
        <p style={{ color: '#8b949e', margin: 0 }}>
          Stay on <strong>Run Controls</strong> and watch the latest run status (pending, running, completed, failed).
        </p>
      </div>

      <div style={stepStyle}>
        <h3 style={{ fontSize: '1rem', marginBottom: '0.35rem', color: '#e6edf3' }}>4) Review dashboard KPIs</h3>
        <p style={{ color: '#8b949e', margin: 0 }}>
          Open <strong>Dashboard</strong> to check queue progress, mapping counts, provider hits, and modeled savings trends.
        </p>
      </div>

      <div style={stepStyle}>
        <h3 style={{ fontSize: '1rem', marginBottom: '0.35rem', color: '#e6edf3' }}>5) Work through operational views</h3>
        <p style={{ color: '#8b949e', margin: '0 0 0.5rem' }}>
          Use <strong>Research Queue</strong>, <strong>Price Comparison</strong>, <strong>Research Evidence</strong>, <strong>Vendor Progress</strong>, and <strong>Mapping Review</strong> for follow-up actions.
        </p>
        <ol style={{ margin: 0, paddingLeft: '1.25rem', color: '#8b949e', lineHeight: 1.65 }}>
          <li><strong>Research Queue</strong>: monitor queued tasks and track processing backlog.</li>
          <li><strong>Price Comparison</strong>: compare current cost vs best price and review estimated savings.</li>
          <li><strong>Research Evidence</strong>: verify recommendation proof (provider, matched MPN, score, price).</li>
          <li><strong>Vendor Progress</strong>: track completion by supplier and prioritize follow-up.</li>
          <li><strong>Mapping Review</strong>: fix missing/weak mappings to improve research quality.</li>
        </ol>
      </div>

      <div style={stepStyle}>
        <h3 style={{ fontSize: '1rem', marginBottom: '0.35rem', color: '#e6edf3' }}>6) Repeat as new data arrives</h3>
        <p style={{ color: '#8b949e', margin: 0 }}>
          Re-import updated files, trigger a new run, and compare updated recommendations before procurement decisions.
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
          <li>Check queue processed %, needs research, and mapping totals.</li>
          <li>Review queue and provider charts for current run health.</li>
          <li>Use this page first after each completed run.</li>
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
          <li>Upload inventory, vendor priority, and item spread files.</li>
          <li>Optionally upload MPN map for better catalog matching.</li>
          <li>Submit and confirm import success before running research.</li>
        </ol>
      </div>

      <div style={stepStyle}>
        <h3 style={headingRowStyle}>
          <svg style={iconStyle} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          Research Queue
        </h3>
        <ol style={{ margin: 0, paddingLeft: '1.25rem', color: '#8b949e', lineHeight: 1.65 }}>
          <li>Filter by status, vendor, or item to focus work.</li>
          <li>Review pending and needs-mapping tasks before reruns.</li>
          <li>Use pagination to inspect all queued records.</li>
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
          <li>Filter by vendor/item and minimum savings threshold.</li>
          <li>Review current vs best unit price and estimated savings.</li>
          <li>Export CSV for procurement review and negotiations.</li>
        </ol>
      </div>

      <div style={stepStyle}>
        <h3 style={headingRowStyle}>
          <svg style={iconStyle} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14 2 14 8 20 8" />
            <line x1="16" y1="13" x2="8" y2="13" />
            <line x1="16" y1="17" x2="8" y2="17" />
          </svg>
          Research Evidence
        </h3>
        <ol style={{ margin: 0, paddingLeft: '1.25rem', color: '#8b949e', lineHeight: 1.65 }}>
          <li>Select a task from the dropdown.</li>
          <li>Review finding details (provider, MPN, score, price).</li>
          <li>Use evidence to confirm recommendation quality.</li>
        </ol>
      </div>

      <div style={stepStyle}>
        <h3 style={headingRowStyle}>
          <svg style={iconStyle} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="none" stroke="white" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10,35 L90,35 L80,15 L20,15 Z" />
            <path d="M10,35 Q10,48 23.3,48 Q36.6,48 36.6,35 Q36.6,48 50,48 Q63.4,48 63.4,35 Q63.4,48 76.7,48 Q90,48 90,35" />
            <line x1="36.6" y1="16" x2="36.6" y2="35" />
            <line x1="63.4" y1="16" x2="63.4" y2="35" />

            <line x1="18" y1="35" x2="18" y2="80" />
            <line x1="82" y1="35" x2="82" y2="80" />

            <rect x="10" y="82" width="80" height="8" rx="4" />

            <circle cx="50" cy="58" r="8" />
            <path d="M34,82 C34,70 66,70 66,82" />
            <circle cx="41" cy="80" r="0.5" fill="black" stroke="none" />
            <circle cx="59" cy="80" r="0.5" fill="black" stroke="none" />

            <g transform="translate(71, 52)">
              <path d="M11,0 L22,6 L22,18 L11,24 L0,18 L0,6 Z" fill="white" />
              <path d="M11,0 L22,6 L11,12 L0,6 Z" /> <path d="M0,6 L11,12 L11,24 L0,18 Z" /> <path d="M11,12 L22,6 L22,18 L11,24 Z" />
            </g>
          </svg>
          Vendor Progress
        </h3>
        <ol style={{ margin: 0, paddingLeft: '1.25rem', color: '#8b949e', lineHeight: 1.65 }}>
          <li>Check processed % by vendor.</li>
          <li>Identify vendors with low completion or high backlog.</li>
          <li>Use this to prioritize follow-up work by supplier.</li>
        </ol>
      </div>

      <div style={stepStyle}>
        <h3 style={headingRowStyle}>
          <svg style={iconStyle} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14 2 14 8 20 8" />
            <line x1="16" y1="13" x2="8" y2="13" />
            <line x1="16" y1="17" x2="8" y2="17" />
          </svg>
          Mapping Review
        </h3>
        <ol style={{ margin: 0, paddingLeft: '1.25rem', color: '#8b949e', lineHeight: 1.65 }}>
          <li>Review items missing or weak MPN mapping.</li>
          <li>Download CSV and enrich mapping data externally if needed.</li>
          <li>Re-import updated mapping data and rerun research.</li>
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
          <li>Set build queue, fallback mode, and batch size.</li>
          <li>Click <strong>Start run</strong> and monitor status.</li>
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
