import React, { useEffect, useState } from 'react';
import { apiGet, apiPost, isAdminUser } from '../api';
import ButtonIcon from '../components/ui/ButtonIcon';

const API_RUN = '/api/procurement/run';
const API_STATUS = '/api/procurement/run-status';
const API_SETTINGS = '/api/procurement/settings';

function formatTime(iso) {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}

export default function RunControls() {
  const isAdmin = isAdminUser();
  const [build, setBuild] = useState(true);
  const [agentFallback, setAgentFallback] = useState('claude');
  const [batchSize, setBatchSize] = useState(50);
  const [runId, setRunId] = useState(null);
  const [status, setStatus] = useState(null);
  const [triggering, setTriggering] = useState(false);
  const [triggerError, setTriggerError] = useState(null);
  const [polling, setPolling] = useState(false);
  const [settings, setSettings] = useState(null);
  const [settingsSaving, setSettingsSaving] = useState(false);
  const [settingsMessage, setSettingsMessage] = useState('');

  useEffect(() => {
    apiGet(API_SETTINGS).then((res) => {
      const s = res?.research || {};
      setSettings({
        strict_mapping: !!s.strict_mapping,
        min_match_score: Number(s.min_match_score ?? 0.9),
        claude_batch_size: Number(s.claude_batch_size ?? 50),
        top_vendors: Number(s.top_vendors ?? 20),
        items_per_vendor: Number(s.items_per_vendor ?? 50),
        top_spread_items: Number(s.top_spread_items ?? 100),
        nightly_enabled: !!s.nightly_enabled,
        nightly_time: s.nightly_time || '01:00',
      });
    }).catch(() => setSettings(null));
  }, []);

  // Load latest run status on mount
  useEffect(() => {
    if (runId) return;
    apiGet(API_STATUS + '?latest=1').then((s) => setStatus(s)).catch(() => setStatus(null));
  }, [runId]);

  // Poll status while a run is active
  useEffect(() => {
    if (!runId || !polling) return;
    let cancelled = false;
    const poll = () => {
      apiGet(`${API_STATUS}?run_id=${runId}`)
        .then((s) => {
          if (!cancelled) setStatus(s);
          if (!cancelled && (s.status === 'pending' || s.status === 'running')) setTimeout(poll, 2000);
        })
        .catch(() => {
          if (!cancelled) setPolling(false);
        });
    };
    poll();
    return () => { cancelled = true; };
  }, [runId, polling]);

  // When status is completed/failed, stop polling
  useEffect(() => {
    if (status?.status === 'completed' || status?.status === 'failed') setPolling(false);
  }, [status?.status]);

  const handleTrigger = () => {
    if (!isAdmin) return;
    setTriggerError(null);
    setTriggering(true);
    apiPost(API_RUN, {
      build: build,
      agent_fallback: agentFallback,
      batch_size: batchSize,
    })
      .then((res) => {
        setRunId(res.run_id);
        setPolling(true);
        setStatus({ run_id: res.run_id, status: 'pending', message: 'Job queued.', created_at: new Date().toISOString() });
      })
      .catch((err) => {
        setTriggerError(err?.errors?.error || err?.message || 'Failed to trigger run');
      })
      .finally(() => setTriggering(false));
  };

  const handleRefreshStatus = () => {
    if (runId) {
      apiGet(`${API_STATUS}?run_id=${runId}`).then(setStatus).catch(() => {});
    } else {
      apiGet(API_STATUS + '?latest=1').then(setStatus).catch(() => {});
    }
  };

  const handleSaveSettings = () => {
    if (!isAdmin || !settings) return;
    setSettingsSaving(true);
    setSettingsMessage('');
    apiPost(API_SETTINGS, settings)
      .then((res) => {
        setSettings(res.research || settings);
        setSettingsMessage('Settings saved.');
      })
      .catch((err) => {
        const msg = err?.errors?.message || err?.errors?.error || err?.message || 'Failed to save settings';
        setSettingsMessage(msg);
      })
      .finally(() => setSettingsSaving(false));
  };

  return (
    <div className="app-main-inner" style={{ padding: '1.5rem' }}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Run Controls</h1>
      <p style={{ color: '#8b949e', marginBottom: '1rem' }}>Trigger a research run and view last run status.</p>
      {!isAdmin && (
        <p style={{ color: '#d29922', marginBottom: '1rem', fontSize: '0.875rem' }}>
          Viewer mode: only admins can trigger runs or update settings.
        </p>
      )}

      <div style={{ background: '#161b22', border: '1px solid #30363d', borderRadius: 8, padding: '1.25rem', marginBottom: '1.5rem' }}>
        <h2 style={{ fontSize: '1rem', fontWeight: 600, color: '#e6edf3', marginBottom: '0.75rem' }}>Research settings</h2>
        {settings ? (
          <>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '1rem', alignItems: 'center' }}>
              <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', color: '#e6edf3', fontSize: '0.875rem' }}>
                <input type="checkbox" checked={settings.strict_mapping} disabled={!isAdmin} onChange={(e) => setSettings((s) => ({ ...s, strict_mapping: e.target.checked }))} />
                Strict mapping
              </label>
              <label style={{ color: '#8b949e', fontSize: '0.875rem' }}>
                Min match score
                <input type="number" min={0} max={1} step={0.01} disabled={!isAdmin} value={settings.min_match_score} onChange={(e) => setSettings((s) => ({ ...s, min_match_score: Number(e.target.value || 0.9) }))} style={{ marginLeft: '0.5rem', width: 90, padding: '0.35rem 0.5rem', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, color: '#e6edf3' }} />
              </label>
              <label style={{ color: '#8b949e', fontSize: '0.875rem' }}>
                Claude batch
                <input type="number" min={1} max={500} disabled={!isAdmin} value={settings.claude_batch_size} onChange={(e) => setSettings((s) => ({ ...s, claude_batch_size: Number(e.target.value || 50) }))} style={{ marginLeft: '0.5rem', width: 80, padding: '0.35rem 0.5rem', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, color: '#e6edf3' }} />
              </label>
            </div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '1rem', alignItems: 'center', marginTop: '0.75rem' }}>
              <label style={{ color: '#8b949e', fontSize: '0.875rem' }}>
                Top vendors
                <input type="number" min={1} max={200} disabled={!isAdmin} value={settings.top_vendors} onChange={(e) => setSettings((s) => ({ ...s, top_vendors: Number(e.target.value || 20) }))} style={{ marginLeft: '0.5rem', width: 80, padding: '0.35rem 0.5rem', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, color: '#e6edf3' }} />
              </label>
              <label style={{ color: '#8b949e', fontSize: '0.875rem' }}>
                Items/vendor
                <input type="number" min={1} max={500} disabled={!isAdmin} value={settings.items_per_vendor} onChange={(e) => setSettings((s) => ({ ...s, items_per_vendor: Number(e.target.value || 50) }))} style={{ marginLeft: '0.5rem', width: 90, padding: '0.35rem 0.5rem', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, color: '#e6edf3' }} />
              </label>
              <label style={{ color: '#8b949e', fontSize: '0.875rem' }}>
                Top spread items
                <input type="number" min={1} max={1000} disabled={!isAdmin} value={settings.top_spread_items} onChange={(e) => setSettings((s) => ({ ...s, top_spread_items: Number(e.target.value || 100) }))} style={{ marginLeft: '0.5rem', width: 100, padding: '0.35rem 0.5rem', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, color: '#e6edf3' }} />
              </label>
            </div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '1rem', alignItems: 'center', marginTop: '0.75rem' }}>
              <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', color: '#e6edf3', fontSize: '0.875rem' }}>
                <input type="checkbox" checked={settings.nightly_enabled} disabled={!isAdmin} onChange={(e) => setSettings((s) => ({ ...s, nightly_enabled: e.target.checked }))} />
                Nightly schedule enabled
              </label>
              <label style={{ color: '#8b949e', fontSize: '0.875rem' }}>
                Nightly time
                <input type="time" disabled={!isAdmin} value={settings.nightly_time} onChange={(e) => setSettings((s) => ({ ...s, nightly_time: e.target.value }))} style={{ marginLeft: '0.5rem', padding: '0.35rem 0.5rem', background: '#0d1117', border: '1px solid #30363d', borderRadius: 6, color: '#e6edf3' }} />
              </label>
              <button type="button" onClick={handleSaveSettings} disabled={!isAdmin || settingsSaving} style={{ padding: '0.35rem 0.75rem', background: !isAdmin || settingsSaving ? '#30363d' : '#1f6feb', border: `1px solid ${!isAdmin || settingsSaving ? '#30363d' : '#388bfd'}`, borderRadius: 6, color: '#fff', cursor: !isAdmin || settingsSaving ? 'not-allowed' : 'pointer', fontSize: '0.875rem', display: 'inline-flex', alignItems: 'center', lineHeight: 1 }}>
                <ButtonIcon>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                    <polyline points="17 21 17 13 7 13 7 21" />
                    <polyline points="7 3 7 8 15 8" />
                  </svg>
                </ButtonIcon>
                {settingsSaving ? 'Saving…' : 'Save settings'}
              </button>
            </div>
          </>
        ) : (
          <p style={{ color: '#8b949e', fontSize: '0.875rem' }}>Loading settings…</p>
        )}
        {settingsMessage && <p style={{ color: settingsMessage === 'Settings saved.' ? '#3fb950' : '#f85149', marginTop: '0.75rem', fontSize: '0.875rem' }}>{settingsMessage}</p>}
      </div>

      <div style={{ background: '#161b22', border: '1px solid #30363d', borderRadius: 8, padding: '1.25rem', marginBottom: '1.5rem' }}>
        <h2 style={{ fontSize: '1rem', fontWeight: 600, color: '#e6edf3', marginBottom: '0.75rem' }}>Trigger research run</h2>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '1rem', alignItems: 'center' }}>
          <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', color: '#e6edf3', fontSize: '0.875rem' }}>
            <input type="checkbox" checked={build} onChange={(e) => setBuild(e.target.checked)} />
            Build queue first
          </label>
          <label style={{ color: '#8b949e', fontSize: '0.875rem' }}>
            Agent fallback
            <select
              value={agentFallback}
              onChange={(e) => setAgentFallback(e.target.value)}
              style={{
                marginLeft: '0.5rem',
                padding: '0.35rem 0.5rem',
                background: '#0d1117',
                border: '1px solid #30363d',
                borderRadius: 6,
                color: '#e6edf3',
              }}
            >
              <option value="claude">Claude</option>
              <option value="none">None (API only)</option>
            </select>
          </label>
          <label style={{ color: '#8b949e', fontSize: '0.875rem' }}>
            Batch size
            <input
              type="number"
              min={1}
              max={500}
              value={batchSize}
              onChange={(e) => setBatchSize(parseInt(e.target.value, 10) || 50)}
              style={{
                marginLeft: '0.5rem',
                width: 70,
                padding: '0.35rem 0.5rem',
                background: '#0d1117',
                border: '1px solid #30363d',
                borderRadius: 6,
                color: '#e6edf3',
              }}
            />
          </label>
          <button
            type="button"
            onClick={handleTrigger}
            disabled={triggering || !isAdmin}
            style={{
              padding: '0.5rem 1rem',
              background: (triggering || !isAdmin) ? '#30363d' : '#238636',
              border: `1px solid ${(triggering || !isAdmin) ? '#30363d' : '#2ea043'}`,
              borderRadius: 6,
              color: '#fff',
              cursor: (triggering || !isAdmin) ? 'not-allowed' : 'pointer',
              fontSize: '0.875rem',
              display: 'inline-flex',
              alignItems: 'center',
              lineHeight: 1,
            }}
          >
            <ButtonIcon>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <polygon points="5 3 19 12 5 21 5 3" />
              </svg>
            </ButtonIcon>
            {triggering ? 'Starting…' : 'Start run'}
          </button>
        </div>
        {triggerError && <p style={{ color: '#f85149', marginTop: '0.75rem', fontSize: '0.875rem' }}>{triggerError}</p>}
      </div>

      <div style={{ background: '#161b22', border: '1px solid #30363d', borderRadius: 8, padding: '1.25rem' }}>
        <h2 style={{ fontSize: '1rem', fontWeight: 600, color: '#e6edf3', marginBottom: '0.75rem' }}>
          Last run status
          <button
            type="button"
            onClick={handleRefreshStatus}
            style={{
              marginLeft: '0.75rem',
              padding: '0.25rem 0.5rem',
              background: 'transparent',
              border: '1px solid #30363d',
              borderRadius: 4,
              color: '#8b949e',
              cursor: 'pointer',
              fontSize: '0.8125rem',
            }}
          >
            Refresh
          </button>
        </h2>
        {status ? (
          <table style={{ fontSize: '0.875rem', color: '#e6edf3' }}>
            <tbody>
              <tr><td style={{ paddingRight: '1rem', color: '#8b949e' }}>Run ID</td><td>{status.run_id}</td></tr>
              <tr><td style={{ paddingRight: '1rem', color: '#8b949e' }}>Status</td><td>{status.status}</td></tr>
              <tr><td style={{ paddingRight: '1rem', color: '#8b949e' }}>Message</td><td>{status.message ?? '—'}</td></tr>
              <tr><td style={{ paddingRight: '1rem', color: '#8b949e' }}>Created</td><td>{formatTime(status.created_at)}</td></tr>
              <tr><td style={{ paddingRight: '1rem', color: '#8b949e' }}>Completed</td><td>{formatTime(status.completed_at)}</td></tr>
            </tbody>
          </table>
        ) : (
          <p style={{ color: '#8b949e' }}>No run status yet. Trigger a run above.</p>
        )}
        {polling && <p style={{ color: '#58a6ff', marginTop: '0.5rem', fontSize: '0.8125rem' }}>Polling for updates…</p>}
      </div>
    </div>
  );
}
