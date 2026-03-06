import React from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { getUser } from '../api';

export default function RequireAuth() {
  const user = getUser();
  const location = useLocation();
  if (!user) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }
  return <Outlet />;
}
