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
          <NavLink to="/dashboard/research-queue" className={({ isActive }) => `app-nav-link ${isActive ? 'active' : ''}`} end>
            <NavIcon>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="11" cy="11" r="8" />
                <line x1="21" y1="21" x2="16.65" y2="16.65" />
              </svg>
            </NavIcon>
            Research Queue
          </NavLink>
          <NavLink to="/dashboard/research-evidence" className={({ isActive }) => `app-nav-link ${isActive ? 'active' : ''}`} end>
            <NavIcon>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
                <line x1="16" y1="13" x2="8" y2="13" />
                <line x1="16" y1="17" x2="8" y2="17" />
              </svg>
            </NavIcon>
            Research Evidence
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
          <NavLink to="/dashboard/vendor-progress" className={({ isActive }) => `app-nav-link ${isActive ? 'active' : ''}`} end>
            <NavIcon>
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="none" stroke="white" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
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
                  <path d="M11,0 L22,6 L11,12 L0,6 Z" /> <path d="M0,6 L11,12 L11,24 L0,18 Z" /> <path d="M11,12 L22,6 L22,18 L11,24 Z" /> </g>
              </svg>
            </NavIcon>
            Vendor Progress
          </NavLink>
          <NavLink to="/dashboard/mapping-review" className={({ isActive }) => `app-nav-link ${isActive ? 'active' : ''}`} end>
            <NavIcon>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
                <line x1="16" y1="13" x2="8" y2="13" />
                <line x1="16" y1="17" x2="8" y2="17" />
              </svg>
            </NavIcon>
            Mapping Review
          </NavLink>
          <NavLink to="/dashboard/run-controls" className={({ isActive }) => `app-nav-link ${isActive ? 'active' : ''}`} end>
            <NavIcon>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <polygon points="5 3 19 12 5 21 5 3" />
              </svg>
            </NavIcon>
            Run Controls
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
