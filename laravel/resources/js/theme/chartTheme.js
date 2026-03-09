/**
 * Chart theme for Phase 4.1 / 4.2 – matches layout 4.0 (dark: #0d1117, #161b22, #30363d, #e6edf3, #8b949e, #58a6ff).
 * Use with Recharts to avoid conflicting with app theme.
 */
export const chartColors = [
  '#58a6ff', // primary blue
  '#3fb950', // green
  '#d29922', // yellow/amber
  '#f85149', // red
  '#a371f7', // purple
  '#79c0ff', // light blue
  '#7ee787', // light green
  '#8b949e', // muted
];

export const chartTheme = {
  text: '#e6edf3',
  tick: '#8b949e',
  grid: '#30363d',
  tooltip: {
    contentStyle: { background: '#161b22', border: '1px solid #30363d', borderRadius: 6 },
    labelStyle: { color: '#e6edf3' },
  },
  cartesianGrid: { stroke: '#30363d' },
  bar: { fill: '#58a6ff' },
  line: { stroke: '#58a6ff' },
  pie: chartColors,
};

export default { chartColors, chartTheme };
