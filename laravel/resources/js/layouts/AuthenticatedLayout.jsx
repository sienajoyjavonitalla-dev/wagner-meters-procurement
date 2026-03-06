import { Outlet, NavLink, useNavigate } from 'react-router-dom';
import { getDisplayName, logout } from '../api';
import './AuthenticatedLayout.css';

const NavIcon = ({ children, size = 20 }) => (
  <span className="app-nav-icon" style={{ width: size, height: size }} aria-hidden>
    {children}
  </span>
);

export default function AuthenticatedLayout() {
  const navigate = useNavigate();
  const displayName = getDisplayName();

  function handleLogout() {
    logout();
    navigate('/login', { replace: true });
  }

  return (
    <div className="authenticated-layout">
      <aside className="app-nav">
        <div className="app-nav-brand">
          <span className="app-nav-logo-text">Procurement</span>
        </div>

        <div className="app-nav-profile">
          <NavIcon>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="12" cy="12" r="10" />
              <path d="M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
              <path d="M6.168 18.849A4 4 0 0 1 10 16h4a4 4 0 0 1 3.834 2.855" />
            </svg>
          </NavIcon>
          <span className="app-nav-profile-name">{displayName}</span>
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
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
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
          <NavLink to="/profile" className={({ isActive }) => `app-nav-link ${isActive ? 'active' : ''}`} end>
            <NavIcon>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                <path d="M6.168 18.849A4 4 0 0 1 10 16h4a4 4 0 0 1 3.834 2.855" />
              </svg>
            </NavIcon>
            Profile
          </NavLink>
        </nav>

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
