import React, { useEffect, useState } from 'react';
import { PieChart, Pie, Cell, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, LineChart, Line } from 'recharts';
import { apiGet } from '../api';
import ChartContainer from '../components/charts/ChartContainer';
import { chartTheme, chartColors } from '../theme/chartTheme';

const API_SUMMARY = '/api/procurement/summary';
const API_HEALTH = '/api/procurement/system-health';
const API_ANALYTICS = '/api/procurement/analytics';

function formatSavings(n) {
  if (n == null || Number.isNaN(n)) return '—';
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
}

function formatTime(iso) {
  if (!iso) return '—';
  try {
    const d = new Date(iso);
    return d.toLocaleString();
  } catch {
    return iso;
  }
}

export default function Dashboard() {
  const [summary, setSummary] = useState(null);
  const [health, setHealth] = useState(null);
  const [analytics, setAnalytics] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    Promise.all([apiGet(API_SUMMARY), apiGet(API_HEALTH), apiGet(API_ANALYTICS)])
      .then(([s, h, a]) => {
        if (!cancelled) {
          setSummary(s);
          setHealth(h);
          setAnalytics(a);
        }
      })
      .catch((err) => {
        if (!cancelled) setError(err?.message || 'Failed to load overview');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  }, []);

  if (loading) {
    return (
      <div className="app-main-inner" style={{ padding: '1.5rem' }}>
        <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Dashboard</h1>
        <p style={{ color: '#8b949e' }}>Loading overview…</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="app-main-inner" style={{ padding: '1.5rem' }}>
        <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Dashboard</h1>
        <p style={{ color: '#f85149' }}>{error}</p>
      </div>
    );
  }

  const queueStatusCounts = summary?.queue_status_counts ?? {};
  const total = Number(queueStatusCounts.researched ?? 0) + Number(queueStatusCounts.pending ?? 0);
  const researched = Number(queueStatusCounts.researched ?? 0);
  const pending = Number(queueStatusCounts.pending ?? 0);
  const queueProcessedPct = total > 0 ? Math.round((researched / total) * 100) : 0;

  const queuePieData = Object.entries(queueStatusCounts).map(([name, count]) => ({ name, value: Number(count) })).filter((d) => d.value > 0);
  const providerBarData = Object.entries(summary?.provider_hit_counts ?? {}).map(([name, count]) => ({ name, count: Number(count) }));
  const savingsTrend = analytics?.daily_modeled_savings ?? [];
  const savingsPerVendor = summary?.savings_potential_per_vendor ?? analytics?.top_suppliers_by_savings ?? [];

  return (
    <div className="app-main-inner" style={{ padding: '1.5rem' }}>
      <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem', color: '#e6edf3' }}>Dashboard</h1>
      <p style={{ color: '#8b949e', marginBottom: '1rem' }}>
        Last research run: {formatTime(health?.last_research_run_at)}
      </p>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))', gap: '1rem', marginBottom: '1.5rem' }}>
        <div style={{ background: '#161b22', border: '1px solid #30363d', borderRadius: 8, padding: '1rem' }}>
          <div style={{ fontSize: '0.75rem', color: '#8b949e', marginBottom: 4 }}>Queue processed</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 600, color: '#e6edf3' }}>{queueProcessedPct}%</div>
        </div>
        <div style={{ background: '#161b22', border: '1px solid #30363d', borderRadius: 8, padding: '1rem' }}>
          <div style={{ fontSize: '0.75rem', color: '#8b949e', marginBottom: 4 }}>Needs research</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 600, color: '#e6edf3' }}>{pending}</div>
        </div>
        <div style={{ background: '#161b22', border: '1px solid #30363d', borderRadius: 8, padding: '1rem' }}>
          <div style={{ fontSize: '0.75rem', color: '#8b949e', marginBottom: 4 }}>Provider hits (Gemini)</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 600, color: '#e6edf3' }}>{Object.values(summary?.provider_hit_counts ?? {}).reduce((a, b) => a + Number(b), 0) || '—'}</div>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1.2fr)', gap: '1.5rem' }}>
        <ChartContainer title="Queue status">
          {queuePieData.length > 0 ? (
            <ResponsiveContainer width="100%" height={220}>
              <PieChart>
                <Pie
                  data={queuePieData}
                  dataKey="value"
                  nameKey="name"
                  cx="50%"
                  cy="50%"
                  outerRadius={80}
                  label={({ name, value }) => `${name}: ${value}`}
                >
                  {queuePieData.map((_, i) => (
                    <Cell key={i} fill={chartColors[i % chartColors.length]} />
                  ))}
                </Pie>
                <Tooltip contentStyle={chartTheme.tooltip.contentStyle} labelStyle={chartTheme.tooltip.labelStyle} itemStyle={chartTheme.tooltip.itemStyle} />
              </PieChart>
            </ResponsiveContainer>
          ) : (
            <p style={{ color: '#8b949e', fontSize: '0.875rem' }}>No queue data. Run a data import and run research.</p>
          )}
        </ChartContainer>
        <ChartContainer title="Provider hits (Gemini)">
          {providerBarData.length > 0 ? (
            <ResponsiveContainer width="100%" height={220}>
              <BarChart data={providerBarData} margin={{ top: 8, right: 8, left: 8, bottom: 8 }}>
                <CartesianGrid strokeDasharray="3 3" stroke={chartTheme.cartesianGrid.stroke} />
                <XAxis dataKey="name" tick={{ fill: chartTheme.tick }} />
                <YAxis tick={{ fill: chartTheme.tick }} />
                <Tooltip contentStyle={chartTheme.tooltip.contentStyle} labelStyle={chartTheme.tooltip.labelStyle} itemStyle={chartTheme.tooltip.itemStyle} />
                <Bar dataKey="count" fill={chartTheme.bar.fill} radius={[4, 4, 0, 0]} name="Hits" />
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <p style={{ color: '#8b949e', fontSize: '0.875rem' }}>No provider hits yet.</p>
          )}
        </ChartContainer>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.2fr) minmax(0, 1fr)', gap: '1.5rem' }}>
        <ChartContainer title="Savings trend (by day)">
          {savingsTrend.length > 0 ? (
            <ResponsiveContainer width="100%" height={220}>
              <LineChart data={savingsTrend} margin={{ top: 8, right: 8, left: 8, bottom: 8 }}>
                <CartesianGrid strokeDasharray="3 3" stroke={chartTheme.cartesianGrid.stroke} />
                <XAxis dataKey="day" tick={{ fill: chartTheme.tick }} />
                <YAxis tick={{ fill: chartTheme.tick }} />
                <Tooltip contentStyle={chartTheme.tooltip.contentStyle} labelStyle={chartTheme.tooltip.labelStyle} itemStyle={chartTheme.tooltip.itemStyle} />
                <Line type="monotone" dataKey="savings_total" stroke={chartTheme.line.stroke} strokeWidth={2} dot={false} />
              </LineChart>
            </ResponsiveContainer>
          ) : (
            <p style={{ color: '#8b949e', fontSize: '0.875rem' }}>No savings trend data yet.</p>
          )}
        </ChartContainer>
        <ChartContainer title="Savings potential per vendor">
          {savingsPerVendor.length > 0 ? (
            <div style={{ overflowX: 'auto' }}>
              <table style={{ width: '100%', borderCollapse: 'collapse', color: '#e6edf3', fontSize: '0.875rem' }}>
                <thead>
                  <tr style={{ borderBottom: '1px solid #30363d' }}>
                    <th style={{ textAlign: 'left', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Vendor</th>
                    <th style={{ textAlign: 'right', padding: '0.5rem 0.75rem', color: '#8b949e' }}>Savings potential</th>
                  </tr>
                </thead>
                <tbody>
                  {savingsPerVendor.map((row) => (
                    <tr key={row.vendor_name ?? row.supplier_name} style={{ borderBottom: '1px solid #30363d' }}>
                      <td style={{ padding: '0.5rem 0.75rem' }}>{row.vendor_name ?? row.supplier_name}</td>
                      <td style={{ padding: '0.5rem 0.75rem', textAlign: 'right' }}>{formatSavings(row.savings_total)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p style={{ color: '#8b949e', fontSize: '0.875rem' }}>No vendor savings data yet.</p>
          )}
        </ChartContainer>
      </div>
    </div>
  );
}
