import React, { useEffect, useMemo, useState } from 'react';
import { apiDelete, apiGet, apiPatch, apiPost, getUser } from '../api';
import ButtonIcon from '../components/ui/ButtonIcon';
import './Users.css';

const API_USERS = '/api/procurement/users';

function roleLabel(role) {
  if (role === 'super_admin') return 'Super Admin';
  if (role === 'admin') return 'Admin';
  return 'Viewer';
}

export default function Users() {
  const currentUser = getUser();
  const [rows, setRows] = useState([]);
  const [roles, setRoles] = useState(['super_admin', 'admin', 'viewer']);
  const [loading, setLoading] = useState(true);
  const [notice, setNotice] = useState(null); // { type: 'success' | 'error', text: string }
  const [saving, setSaving] = useState(false);
  const [canAssignSuperAdmin, setCanAssignSuperAdmin] = useState(false);
  const [modalMode, setModalMode] = useState(null); // 'create' | 'edit' | null
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [editingUserId, setEditingUserId] = useState(null);
  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'viewer',
  });

  const roleOptions = useMemo(
    () => roles.filter((r) => canAssignSuperAdmin || r !== 'super_admin'),
    [roles, canAssignSuperAdmin]
  );

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setNotice(null);
    apiGet(API_USERS)
      .then((res) => {
        if (cancelled) return;
        setRows(res.data ?? []);
        setRoles(res.roles ?? ['super_admin', 'admin', 'viewer']);
        setCanAssignSuperAdmin(!!res.can_assign_super_admin);
      })
      .catch((err) => {
        if (!cancelled) setNotice({ type: 'error', text: err?.message || 'Failed to load users' });
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  }, []);

  const openCreateModal = () => {
    setModalMode('create');
    setEditingUserId(null);
    setNotice(null);
    setForm({
      first_name: '',
      last_name: '',
      email: '',
      password: '',
      password_confirmation: '',
      role: roleOptions.includes('viewer') ? 'viewer' : roleOptions[0] || 'admin',
    });
  };

  const openEditModal = (u) => {
    setModalMode('edit');
    setEditingUserId(u.id);
    setNotice(null);
    setForm({
      first_name: u.first_name || '',
      last_name: u.last_name || '',
      email: u.email || '',
      password: '',
      password_confirmation: '',
      role: u.role || 'viewer',
    });
  };

  const closeModal = () => {
    setModalMode(null);
    setEditingUserId(null);
    setForm({ first_name: '', last_name: '', email: '', password: '', password_confirmation: '', role: 'viewer' });
  };

  const handleSaveModal = (e) => {
    e.preventDefault();
    if (!modalMode) return;
    if (form.password && form.password !== form.password_confirmation) {
      setNotice({ type: 'error', text: 'Password confirmation does not match.' });
      return;
    }
    setSaving(true);
    setNotice(null);
    const request = modalMode === 'create'
      ? apiPost(API_USERS, form)
      : apiPatch(`/api/procurement/users/${editingUserId}`, {
        first_name: form.first_name,
        last_name: form.last_name,
        email: form.email,
        role: form.role,
        ...(form.password ? { password: form.password, password_confirmation: form.password_confirmation } : {}),
      });

    request
      .then(() => {
        closeModal();
        setNotice({ type: 'success', text: modalMode === 'create' ? 'User added.' : 'User updated.' });
        return apiGet(API_USERS).catch(() => null);
      })
      .then((res) => {
        if (res?.data) setRows(res.data);
      })
      .catch((err) => {
        setNotice({ type: 'error', text: err?.errors?.error || err?.errors?.message || err?.message || `Failed to ${modalMode === 'create' ? 'create' : 'update'} user` });
      })
      .finally(() => setSaving(false));
  };

  const openDeleteModal = (u) => setDeleteTarget(u);

  const closeDeleteModal = () => setDeleteTarget(null);

  const confirmDelete = () => {
    if (!deleteTarget) return;
    setSaving(true);
    setNotice(null);
    apiDelete(`/api/procurement/users/${deleteTarget.id}`)
      .then(() => {
        setRows((prev) => prev.filter((x) => x.id !== deleteTarget.id));
        setNotice({ type: 'success', text: 'User deleted.' });
      })
      .catch((err) => {
        setNotice({ type: 'error', text: err?.errors?.error || err?.errors?.message || err?.message || 'Failed to delete user' });
      })
      .finally(() => {
        setSaving(false);
        closeDeleteModal();
      });
  };

  return (
    <div className="app-main-inner" style={{ padding: '1.5rem' }}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Users</h1>
      <p style={{ color: '#8b949e', marginBottom: '1rem' }}>
        Manage user roles: Super Admin, Admin, and Viewer.
      </p>

      {notice && (
        <div
          style={{
            marginBottom: '0.75rem',
            padding: '0.6rem 0.75rem',
            borderRadius: 6,
            border: notice.type === 'success' ? '1px solid #1f6f43' : '1px solid #a40e26',
            background: notice.type === 'success' ? 'rgba(35, 134, 54, 0.22)' : 'rgba(248, 81, 73, 0.18)',
            color: notice.type === 'success' ? '#3fb950' : '#f85149',
            fontSize: '0.875rem',
          }}
        >
          {notice.text}
        </div>
      )}

      {loading ? (
        <p style={{ color: '#8b949e' }}>Loading users…</p>
      ) : (
        <div>
          <div style={{ marginBottom: '1rem' }}>
            <button
              type="button"
              onClick={openCreateModal}
              className="employees-btn employees-btn--create"
              style={{
                padding: '0.45rem 0.85rem',
                background: '#238636',
                border: '1px solid #2ea043',
                borderRadius: 6,
                color: '#fff',
                cursor: 'pointer',
                display: 'inline-flex',
                alignItems: 'center',
                gap: '0.35rem',
                lineHeight: 1,
              }}
            >
              <ButtonIcon size={18} marginRight="0">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <line x1="12" y1="5" x2="12" y2="19"></line>
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
              </ButtonIcon>
              Create user
            </button>
          </div>

          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', color: '#e6edf3', fontSize: '0.875rem' }}>
              <thead>
                <tr style={{ borderBottom: '1px solid #30363d' }}>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Name</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Email</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Role</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Created</th>
                  <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((u) => {
                  const editingSelf = currentUser?.id === u.id;
                  return (
                    <tr key={u.id} style={{ borderBottom: '1px solid #30363d' }}>
                      <td style={{ padding: '0.5rem 0.75rem' }}>{u.name}{editingSelf && <span style={{ marginLeft: '0.5rem', color: '#8b949e', fontSize: '0.75rem' }}>(you)</span>}</td>
                      <td style={{ padding: '0.5rem 0.75rem' }}>{u.email}</td>
                      <td style={{ padding: '0.5rem 0.75rem' }}>{roleLabel(u.role)}</td>
                      <td style={{ padding: '0.5rem 0.75rem', color: '#8b949e' }}>
                        {u.created_at ? new Date(u.created_at).toLocaleDateString() : '—'}
                      </td>
                      <td style={{ padding: '0.5rem 0.75rem' }}>
                        <div style={{ display: 'flex', gap: '0.4rem' }}>
                          <button type="button" onClick={() => openEditModal(u)} disabled={saving} className="employees-btn employees-btn--edit" style={{ padding: '0.3rem 0.55rem', background: 'transparent', border: '1px solid #2ea043', borderRadius: 6, color: '#3fb950' }}>
                            <ButtonIcon>
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                              </svg>
                            </ButtonIcon>
                            Edit
                          </button>
                          <button type="button" onClick={() => openDeleteModal(u)} disabled={saving || editingSelf} className="employees-btn employees-btn--delete" style={{ padding: '0.3rem 0.55rem', background: 'transparent', border: '1px solid #f85149', borderRadius: 6, color: '#f85149', cursor: editingSelf ? 'not-allowed' : 'pointer' }}>
                            <ButtonIcon>
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M3 6h18"></path>
                                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                <line x1="10" x2="10" y1="11" y2="17"></line>
                                <line x1="14" x2="14" y1="11" y2="17"></line>
                              </svg>
                            </ButtonIcon>
                            Delete
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {modalMode && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(1,4,9,0.65)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem', zIndex: 50 }}>
          <form onSubmit={handleSaveModal} style={{ width: '100%', maxWidth: 560, background: '#161b22', border: '1px solid #30363d', borderRadius: 10, padding: '1rem' }}>
            <h2 style={{ margin: '0 0 0.75rem', fontSize: '1rem', color: '#e6edf3' }}>{modalMode === 'create' ? 'Create user' : 'Edit user'}</h2>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem', marginBottom: '0.5rem' }}>
              <input value={form.first_name} onChange={(e) => setForm((f) => ({ ...f, first_name: e.target.value }))} placeholder="First name" style={{ padding: '0.4rem 0.5rem', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, color: '#e6edf3' }} />
              <input value={form.last_name} onChange={(e) => setForm((f) => ({ ...f, last_name: e.target.value }))} placeholder="Last name" style={{ padding: '0.4rem 0.5rem', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, color: '#e6edf3' }} />
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem', marginBottom: '0.5rem' }}>
              <input required type="email" value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} placeholder="Email" style={{ padding: '0.4rem 0.5rem', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, color: '#e6edf3' }} />
              <select value={form.role} onChange={(e) => setForm((f) => ({ ...f, role: e.target.value }))} style={{ padding: '0.4rem 0.5rem', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, color: '#e6edf3' }}>
                {roleOptions.map((r) => <option key={r} value={r}>{roleLabel(r)}</option>)}
              </select>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem', marginBottom: '0.75rem' }}>
              <input
                type="password"
                required={modalMode === 'create'}
                value={form.password}
                onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
                placeholder={modalMode === 'create' ? 'Password (min 8)' : 'New password (optional)'}
                style={{ width: '100%', padding: '0.4rem 0.5rem', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, color: '#e6edf3' }}
              />
              <input
                type="password"
                required={modalMode === 'create' || !!form.password}
                value={form.password_confirmation}
                onChange={(e) => setForm((f) => ({ ...f, password_confirmation: e.target.value }))}
                placeholder="Confirm password"
                style={{ width: '100%', padding: '0.4rem 0.5rem', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, color: '#e6edf3' }}
              />
            </div>
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem' }}>
              <button type="button" onClick={closeModal} className="employees-btn employees-btn--cancel" style={{ padding: '0.4rem 0.75rem', background: 'transparent', border: '1px solid #30363d', borderRadius: 6, color: '#e6edf3' }}>
                <ButtonIcon>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <line x1="18" x2="6" y1="6" y2="18" />
                    <line x1="6" x2="18" y1="6" y2="18" />
                  </svg>
                </ButtonIcon>
                Cancel
              </button>
              <button type="submit" disabled={saving} className="employees-btn employees-btn--save" style={{ padding: '0.4rem 0.75rem', background: '#1f6feb', border: '1px solid #388bfd', borderRadius: 6, color: '#fff' }}>
                <ButtonIcon>
                  {saving ? (
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <circle cx="12" cy="12" r="9" />
                    </svg>
                  ) : (
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <polyline points="20 6 9 17 4 12" />
                    </svg>
                  )}
                </ButtonIcon>
                {saving ? 'Saving…' : modalMode === 'create' ? 'Create' : 'Save'}
              </button>
            </div>
          </form>
        </div>
      )}

      {deleteTarget && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(1,4,9,0.65)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem', zIndex: 60 }}>
          <div style={{ width: '100%', maxWidth: 460, background: '#161b22', border: '1px solid #30363d', borderRadius: 10, padding: '1rem' }}>
            <h2 style={{ margin: '0 0 0.5rem', fontSize: '1rem', color: '#e6edf3' }}>Delete user</h2>
            <p style={{ margin: '0 0 0.75rem', color: '#8b949e' }}>
              Delete <strong>{deleteTarget.email}</strong>? This action cannot be undone.
            </p>
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem' }}>
              <button type="button" onClick={closeDeleteModal} className="employees-btn employees-btn--cancel" style={{ padding: '0.4rem 0.75rem', background: 'transparent', border: '1px solid #30363d', borderRadius: 6, color: '#e6edf3' }}>
                <ButtonIcon>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <line x1="18" x2="6" y1="6" y2="18" />
                    <line x1="6" x2="18" y1="6" y2="18" />
                  </svg>
                </ButtonIcon>
                Cancel
              </button>
              <button type="button" onClick={confirmDelete} disabled={saving} className="employees-btn employees-btn--delete" style={{ padding: '0.4rem 0.75rem', background: 'transparent', border: '1px solid #f85149', borderRadius: 6, color: '#f85149' }}>
                <ButtonIcon>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M3 6h18"></path>
                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                    <line x1="10" x2="10" y1="11" y2="17"></line>
                    <line x1="14" x2="14" y1="11" y2="17"></line>
                  </svg>
                </ButtonIcon>
                {saving ? 'Deleting…' : 'Delete'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
