import { useState } from 'react';
import { Outlet, NavLink, useNavigate } from 'react-router-dom';
import { getDisplayName, isSuperAdminUser, logout } from '../api';
import './AuthenticatedLayout.css';

const NavIcon = ({ children, size = 20 }) => (
  <span className="app-nav-icon" style={{ width: size, height: size }} aria-hidden>
    {children}
  </span>
);

export default function AuthenticatedLayout() {
  const navigate = useNavigate();
  const displayName = getDisplayName();
  const canManageUsers = isSuperAdminUser();
  const [logoError, setLogoError] = useState(false);

  function handleLogout() {
    logout();
    navigate('/login', { replace: true });
  }

  return (
    <div className="authenticated-layout">
      <aside className="app-nav">
        <div className="app-nav-brand">
          {!logoError && (
            <img
              src="/images/logo.png"
              alt="Wagner"
              className="app-nav-logo"
              onError={() => setLogoError(true)}
            />
          )}
        </div>

        <div className="app-nav-profile">
          <NavIcon>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="12" cy="12" r="10" />
              <path d="M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
              <path d="M6.168 18.849A4 4 0 0 1 10 16h4a4 4 0 0 1 3.834 2.855" />
            </svg>
          </NavIcon>
          <NavLink to="/profile" className="app-nav-profile-name">
            {displayName}
          </NavLink>
        </div>

        <nav className="app-nav-links">
          <NavLink to="/dashboard" className={({ isActive }) => `app-nav-link ${isActive ? 'active' : ''}`} end>
            <NavIcon>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="3" y="3" width="7" height="7" />
                <rect x="14" y="3" width="7" height="7" />
                <rect x="14" y="14" width="7" height="7" />
                <rect x="3" y="14" width="7" height="7" />
              </svg>
            </NavIcon>
            Dashboard
          </NavLink>
          <NavLink to="/data-import" className={({ isActive }) => `app-nav-link ${isActive ? 'active' : ''}`} end>
            <NavIcon>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" x2="12" y1="3" y2="15" />
              </svg>
            </NavIcon>
            Data Import
          </NavLink>
          <NavLink to="/dashboard/price-comparison" className={({ isActive }) => `app-nav-link ${isActive ? 'active' : ''}`} end>
            <NavIcon>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                <polyline points="17 6 23 6 23 12" />
              </svg>
            </NavIcon>
            Price Comparison
          </NavLink>
          <NavLink to="/dashboard/run-controls" className={({ isActive }) => `app-nav-link ${isActive ? 'active' : ''}`} end>
            <NavIcon>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <polygon points="5 3 19 12 5 21 5 3" />
              </svg>
            </NavIcon>
            Run Controls
          </NavLink>
          <NavLink to="/dashboard/inventories" className={({ isActive }) => `app-nav-link ${isActive ? 'active' : ''}`} end>
            <NavIcon>
              <svg viewBox="0 0 256 252" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden>
                <path d="M67.182,200H16.063v-51.12h19.708v16.323h11.703V148.88h19.708V200z M134.063,200H82.944v-51.12h19.708  v16.323h11.703V148.88h19.708V200z M101.022,133.12H49.903V82h19.708v16.323h11.703V82h19.708V133.12z M198.608,91.221  c-1.756-1.828-1.698-4.734,0.13-6.491s4.734-1.698,6.491,0.13s1.698,4.734-0.13,6.491S200.364,93.049,198.608,91.221z   M229.263,82.637c1.828-1.756,1.887-4.662,0.13-6.491s-4.662-1.887-6.491-0.13c-1.828,1.756-1.887,4.662-0.13,6.491  S227.434,84.394,229.263,82.637z M171.883,56.775c1.828-1.756,1.887-4.662,0.13-6.491c-1.756-1.828-4.662-1.887-6.491-0.13  s-1.887,4.662-0.13,6.491S170.055,58.532,171.883,56.775z M239.094,60.108c1.828-1.756,1.887-4.662,0.13-6.491  s-4.662-1.887-6.491-0.13s-1.887,4.662-0.13,6.491C234.36,61.806,237.266,61.864,239.094,60.108z M204.274,24.598  c1.828-1.756,1.887-4.662,0.13-6.491c-1.756-1.828-4.662-1.887-6.491-0.13s-1.887,4.662-0.13,6.491  C199.54,26.296,202.445,26.354,204.274,24.598z M229.857,35.032c1.828-1.756,1.887-4.662,0.13-6.491  c-1.756-1.828-4.662-1.887-6.491-0.13s-1.887,4.662-0.13,6.491C225.123,36.73,228.029,36.788,229.857,35.032z M182.436,34.261  c1.828-1.756,1.887-4.662,0.13-6.491c-1.756-1.828-4.662-1.887-6.491-0.13s-1.887,4.662-0.13,6.491S180.607,36.017,182.436,34.261z   M190.179,61.832l-16.368,15.724l-1.58,6.822l6.88-1.305l16.368-15.724c4.637,2.618,10.782,2.02,15.012-2.044  c5.15-4.947,5.309-12.88,0.362-18.03s-12.88-5.309-18.03-0.362C188.593,50.978,187.573,56.91,190.179,61.832z M253.968,54.72  c0,21.672-13.201,40.348-31.968,48.417V119h-15.232v88.777c0,17.087-13.772,30.858-30.859,30.858h-26.12v11.181H2V215h148v13.484  l26.675-0.05c11.476,0,20.657-9.181,20.657-20.657V119H182v-15.814c-18.828-8.041-32.083-26.75-32.083-48.466  c0-29.073,23.463-52.536,52.536-52.536C231.271,2.184,254.989,25.646,253.968,54.72z M241.747,34.102  c-11.375-21.68-38.232-30.052-59.912-18.677c-21.68,11.375-30.052,38.232-18.677,59.912c11.375,21.68,38.232,30.052,59.912,18.677  C244.75,82.639,253.122,55.782,241.747,34.102z"/>
              </svg>
            </NavIcon>
            Inventories
          </NavLink>
          <NavLink to="/dashboard/researched-mpn" className={({ isActive }) => `app-nav-link ${isActive ? 'active' : ''}`} end>
            <NavIcon>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="3" y="3" width="7" height="7" />
                <rect x="14" y="3" width="7" height="7" />
                <rect x="14" y="14" width="7" height="7" />
                <rect x="3" y="14" width="7" height="7" />
              </svg>
            </NavIcon>
            Researched MPN
          </NavLink>
          {canManageUsers && (
            <NavLink to="/dashboard/users" className={({ isActive }) => `app-nav-link ${isActive ? 'active' : ''}`} end>
              <NavIcon>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                  <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
              </NavIcon>
              Users
            </NavLink>
          )}
        </nav>
        <NavLink to="/dashboard/how-to-use" className={({ isActive }) => `app-nav-link app-nav-guide-link ${isActive ? 'active' : ''}`} end>
          <NavIcon>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="12" cy="12" r="10" />
              <path d="M9.09 9a3 3 0 1 1 5.83 1c0 2-3 2-3 4" />
              <line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
          </NavIcon>
          How to use
        </NavLink>
        <div className="app-nav-footer">

          <button type="button" className="app-nav-logout" onClick={handleLogout}>
            <NavIcon>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" x2="9" y1="12" y2="12" />
              </svg>
            </NavIcon>
            Log out
          </button>
        </div>
      </aside>
      <main className="app-main">
        <Outlet />
      </main>
    </div>
  );
}
