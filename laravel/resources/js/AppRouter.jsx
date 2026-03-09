import React from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import RequireAuth from './components/RequireAuth';
import AuthenticatedLayout from './layouts/AuthenticatedLayout';
import Dashboard from './pages/Dashboard';
import DataImport from './pages/DataImport';
import Profile from './pages/Profile';
import ResearchQueue from './pages/ResearchQueue';
import PriceComparison from './pages/PriceComparison';
import ResearchEvidence from './pages/ResearchEvidence';
import VendorProgress from './pages/VendorProgress';
import MappingReview from './pages/MappingReview';
import RunControls from './pages/RunControls';
import BeginnerGuide from './pages/BeginnerGuide';
import Users from './pages/Users';
import Welcome from './pages/Welcome';
import Login from './pages/Login';
import ForgotPassword from './pages/ForgotPassword';
import ResetPassword from './pages/ResetPassword';
import VerifyEmail from './pages/VerifyEmail';
import ConfirmPassword from './pages/ConfirmPassword';

function Home() {
  const user = (window.__PROCUREMENT__ || {}).user;
  return user ? <Navigate to="/dashboard" replace /> : <Welcome />;
}

export default function AppRouter() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="login" element={<Login />} />
        <Route path="forgot-password" element={<ForgotPassword />} />
        <Route path="reset-password/:token" element={<ResetPassword />} />
        <Route path="verify-email" element={<VerifyEmail />} />
        <Route path="confirm-password" element={<ConfirmPassword />} />
        <Route path="*" element={<RequireAuth />}>
          <Route path="*" element={<AuthenticatedLayout />}>
            <Route index element={<Navigate to="/dashboard" replace />} />
            <Route path="dashboard" element={<Dashboard />} />
            <Route path="dashboard/research-queue" element={<ResearchQueue />} />
            <Route path="dashboard/price-comparison" element={<PriceComparison />} />
            <Route path="dashboard/research-evidence" element={<ResearchEvidence />} />
            <Route path="dashboard/vendor-progress" element={<VendorProgress />} />
            <Route path="dashboard/mapping-review" element={<MappingReview />} />
            <Route path="dashboard/run-controls" element={<RunControls />} />
            <Route path="dashboard/how-to-use" element={<BeginnerGuide />} />
            <Route path="dashboard/users" element={<Users />} />
            <Route path="data-import" element={<DataImport />} />
            <Route path="profile" element={<Profile />} />
          </Route>
        </Route>
      </Routes>
    </BrowserRouter>
  );
}
