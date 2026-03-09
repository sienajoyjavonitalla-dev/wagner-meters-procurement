/**
 * Procurement app API (Phase 4.1).
 * - API base URL: from window.__PROCUREMENT__.apiBase (Laravel Blade injects url('/')).
 * - Auth: session cookie (credentials: 'include'); user from __PROCUREMENT__.user; logout via POST to logoutUrl with CSRF.
 */
const config = window.__PROCUREMENT__ || {};

export function getDisplayName() {
  const user = config.user;
  if (! user) return 'Account';
  const name = [user.first_name, user.last_name].filter(Boolean).join(' ');
  return name || user.email || 'Account';
}

export function getUser() {
  return config.user || null;
}

export function isAdminUser() {
  const role = config.user?.role || 'viewer';
  return role === 'admin' || role === 'super_admin';
}

export function isSuperAdminUser() {
  return (config.user?.role || 'viewer') === 'super_admin';
}

export function logout() {
  const form = document.getElementById('procurement-logout-form');
  if (form) {
    form.submit();
    return;
  }
  const url = config.logoutUrl || '/logout';
  const token = config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content;
  const formData = new FormData();
  if (token) formData.append('_token', token);
  fetch(url, { method: 'POST', body: formData, credentials: 'same-origin' }).then(() => {
    window.location.href = config.loginUrl || '/login';
  }).catch(() => {
    window.location.href = url;
  });
}

export function apiBase() {
  return config.apiBase ?? '';
}

export async function apiGet(path) {
  const base = apiBase();
  const url = path.startsWith('http') ? path : `${base}${path}`;
  const res = await fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } });
  if (res.status === 401) {
    window.location.href = config.loginUrl || '/login';
    throw new Error('Unauthorized');
  }
  if (! res.ok) throw new Error(await res.text() || `HTTP ${res.status}`);
  return res.json();
}

/** Prefer XSRF-TOKEN cookie (Laravel sets this; sending it back in X-XSRF-TOKEN matches the session). */
function getCsrfTokenFromCookie() {
  const name = 'XSRF-TOKEN';
  const match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]*)'));
  const value = match ? decodeURIComponent(match[2]) : '';
  return value || '';
}

export function getCsrfToken() {
  return config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/** POST form data (e.g. multipart) to a URL; expects JSON response or 422 with JSON errors. */
export async function apiPostFormData(urlPath, formData) {
  const base = apiBase();
  const url = urlPath.startsWith('http') ? urlPath : `${base}${urlPath}`;
  const token = getCsrfToken();
  const cookieToken = getCsrfTokenFromCookie();
  if (token && !formData.has('_token')) formData.append('_token', token);
  const res = await fetch(url, {
    method: 'POST',
    body: formData,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(token ? { 'X-CSRF-TOKEN': token } : {}),
      ...(cookieToken ? { 'X-XSRF-TOKEN': cookieToken } : {}),
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
  if (res.status === 401) {
    window.location.href = config.loginUrl || '/login';
    throw new Error('Unauthorized');
  }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw { status: res.status, errors: data.errors || data.message || data };
  return data;
}

/** POST form data to auth endpoints (login, register, etc.). Redirects on success; returns errors object on 422. */
export async function apiPostAuth(urlPath, formData) {
  const base = apiBase();
  const url = urlPath.startsWith('http') ? urlPath : `${base}${urlPath}`;
  const token = getCsrfToken();
  const cookieToken = getCsrfTokenFromCookie();
  if (token && !formData.has('_token')) formData.append('_token', token);
  const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
  if (token) headers['X-CSRF-TOKEN'] = token;
  if (cookieToken) headers['X-XSRF-TOKEN'] = cookieToken; // Laravel decrypts XSRF-TOKEN cookie and accepts this
  const res = await fetch(url, {
    method: 'POST',
    body: formData,
    credentials: 'include',
    redirect: 'manual',
    headers,
  });

  console.log('[apiPostAuth] response status:', res.status, 'ok:', res.ok, 'url:', urlPath);

  // status 0 often means opaque response (e.g. redirect with redirect:'manual') – treat as success and go to dashboard
  if (res.status === 0) {
    const dashboardUrl = base.replace(/\/$/, '') + '/dashboard';
    console.log('[apiPostAuth] status 0 (opaque/redirect), redirecting to', dashboardUrl);
    window.location.href = dashboardUrl;
    return { redirect: true };
  }

  if (res.status === 302 || res.status === 303) {
    const dashboardUrl = base.replace(/\/$/, '') + '/dashboard';
    console.log('[apiPostAuth] redirect branch 302/303 ->', dashboardUrl);
    window.location.href = dashboardUrl;
    return { redirect: true };
  }

  const data = await res.json().catch((err) => {
    console.log('[apiPostAuth] JSON parse failed:', err);
    return {};
  });
  console.log('[apiPostAuth] response data:', data);

  if (!res.ok) {
    console.log('[apiPostAuth] not ok, returning errors');
    return { ok: false, errors: data.errors || {}, message: data.message };
  }
  // Laravel may return 200 with redirect URL when Accept: application/json
  if (res.ok && (data.redirect || data.url)) {
    const target = data.redirect || data.url;
    const finalUrl = target.startsWith('http') ? target : (base.replace(/\/$/, '') + (target.startsWith('/') ? target : '/' + target));
    console.log('[apiPostAuth] redirect from body ->', finalUrl);
    window.location.href = finalUrl;
    return { redirect: true };
  }
  console.log('[apiPostAuth] returning ok: true, no redirect in body');
  return { ok: true, data };
}

/** POST JSON to a URL. */
export async function apiPost(path, body) {
  const base = apiBase();
  const url = path.startsWith('http') ? path : `${base}${path}`;
  const token = getCsrfToken();
  const cookieToken = getCsrfTokenFromCookie();
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { 'X-CSRF-TOKEN': token } : {}),
      ...(cookieToken ? { 'X-XSRF-TOKEN': cookieToken } : {}),
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify(body || {}),
  });
  if (res.status === 401) {
    window.location.href = config.loginUrl || '/login';
    throw new Error('Unauthorized');
  }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw { status: res.status, errors: data.errors || data };
  return data;
}

/** PATCH JSON to a URL. */
export async function apiPatch(path, body) {
  const base = apiBase();
  const url = path.startsWith('http') ? path : `${base}${path}`;
  const token = getCsrfToken();
  const cookieToken = getCsrfTokenFromCookie();
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { 'X-CSRF-TOKEN': token } : {}),
      ...(cookieToken ? { 'X-XSRF-TOKEN': cookieToken } : {}),
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify({ ...body, _method: 'PATCH' }),
  });
  if (res.status === 401) {
    window.location.href = config.loginUrl || '/login';
    throw new Error('Unauthorized');
  }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw { status: res.status, errors: data.errors || data };
  return data;
}

/** DELETE to a URL. */
export async function apiDelete(path) {
  const base = apiBase();
  const url = path.startsWith('http') ? path : `${base}${path}`;
  const token = getCsrfToken();
  const cookieToken = getCsrfTokenFromCookie();
  const res = await fetch(url, {
    method: 'DELETE',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(token ? { 'X-CSRF-TOKEN': token } : {}),
      ...(cookieToken ? { 'X-XSRF-TOKEN': cookieToken } : {}),
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
  if (res.status === 401) {
    window.location.href = config.loginUrl || '/login';
    throw new Error('Unauthorized');
  }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw { status: res.status, errors: data.errors || data };
  return data;
}
