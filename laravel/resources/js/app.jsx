import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import RequireAuth from './components/RequireAuth';
import AuthenticatedLayout from './layouts/AuthenticatedLayout';
import Dashboard from './pages/Dashboard';
import DataImport from './pages/DataImport';
import Profile from './pages/Profile';
import Welcome from './pages/Welcome';
import Login from './pages/Login';
import Register from './pages/Register';
import ForgotPassword from './pages/ForgotPassword';
import ResetPassword from './pages/ResetPassword';
import VerifyEmail from './pages/VerifyEmail';
import ConfirmPassword from './pages/ConfirmPassword';

function Home() {
  const user = (window.__PROCUREMENT__ || {}).user;
  return user ? <Navigate to="/dashboard" replace /> : <Welcome />;
}

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="login" element={<Login />} />
        <Route path="register" element={<Register />} />
        <Route path="forgot-password" element={<ForgotPassword />} />
        <Route path="reset-password/:token" element={<ResetPassword />} />
        <Route path="verify-email" element={<VerifyEmail />} />
        <Route path="confirm-password" element={<ConfirmPassword />} />
        <Route path="*" element={<RequireAuth />}>
          <Route path="*" element={<AuthenticatedLayout />}>
            <Route index element={<Navigate to="/dashboard" replace />} />
            <Route path="dashboard" element={<Dashboard />} />
            <Route path="dashboard/research-queue" element={<Placeholder title="Research Queue" />} />
            <Route path="dashboard/price-comparison" element={<Placeholder title="Price Comparison" />} />
            <Route path="dashboard/vendor-progress" element={<Placeholder title="Vendor Progress" />} />
            <Route path="dashboard/mapping-review" element={<Placeholder title="Mapping Review" />} />
            <Route path="dashboard/run-controls" element={<Placeholder title="Run Controls" />} />
            <Route path="data-import" element={<DataImport />} />
            <Route path="profile" element={<Profile />} />
          </Route>
        </Route>
      </Routes>
    </BrowserRouter>
  );
}

function Placeholder({ title }) {
  return (
    <div className="app-main-inner" style={{ padding: '1.5rem' }}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>{title}</h1>
      <p style={{ color: '#8b949e' }}>View coming in Phase 4.2.</p>
    </div>
  );
}

const container = document.getElementById('app');
if (container) {
  try {
    const root = createRoot(container);
    root.render(<App />);
  } catch (err) {
    container.innerHTML = '<div style="padding:1.5rem;color:#e6edf3;"><p>Dashboard failed to load.</p><p style="color:#8b949e;font-size:0.875rem;">Open the browser console (F12) for errors. Ensure you ran <code>npm run dev</code> or <code>npm run build</code>.</p></div>';
    console.error('Procurement app mount error:', err);
  }
}
