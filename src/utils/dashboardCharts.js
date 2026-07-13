import { COLORS } from '../services/utils.js'
import { formatBytes, isBytesMetric } from './dashboardFormat.js'

// Chart chrome (axis labels, grid, donut labels) uses Nextcloud's own theme
// variables so it tracks light/dark/custom themes exactly like the rest of the
// UI. ApexCharts writes these strings straight into SVG fill/stroke attributes,
// and the browser resolves the var() at paint time.
const TEXT_MUTED = 'var(--color-text-maxcontrast)'
const TEXT_MAIN = 'var(--color-main-text)'
const GRID_LINE = 'var(--color-border)'

export function buildSparkOptions(trend) {
	const color = trend?.dir === 'down' ? '#e9322d' : (trend?.dir === 'up' ? '#46ba61' : '#0082c9')
	return {
		chart: {
			type: 'area',
			sparkline: { enabled: true },
			animations: { enabled: false },
			parentHeightOffset: 0,
		},
		colors: [color],
		stroke: { curve: 'smooth', width: 2 },
		fill: {
			type: 'gradient',
			gradient: { opacityFrom: 0.5, opacityTo: 0, stops: [0, 100] },
		},
		tooltip: { enabled: false },
		dataLabels: { enabled: false },
		markers: { size: 0 },
	}
}

export function buildSpotlightOptions(metricId) {
	const isBytes = metricId && (metricId.includes('bytes') || metricId.includes('storage') || metricId.includes('disk_space') || metricId.includes('db_size'))
	return {
		chart: {
			type: 'area',
			fontFamily: 'inherit',
			background: 'transparent',
			toolbar: { show: false },
			zoom: { enabled: false },
			animations: {
				enabled: true,
				speed: 350,
				easing: 'easeout',
				animateGradually: { enabled: false },
				dynamicAnimation: { enabled: true, speed: 350 },
			},
		},
		colors: ['#0082c9'],
		stroke: { curve: 'smooth', width: 2.5 },
		fill: {
			type: 'gradient',
			gradient: {
				shadeIntensity: 1,
				opacityFrom: 0.32,
				opacityTo: 0.02,
				stops: [0, 100],
			},
		},
		dataLabels: { enabled: false },
		markers: { size: 0, strokeWidth: 0, hover: { size: 6 } },
		xaxis: {
			type: 'datetime',
			labels: {
				datetimeUTC: false,
				style: { fontSize: '11px', colors: TEXT_MUTED },
			},
			axisBorder: { show: false },
			axisTicks: { show: false },
		},
		yaxis: {
			labels: {
				style: { fontSize: '11px', colors: TEXT_MUTED },
				formatter: (val) => {
					if (val === null || val === undefined) return ''
					if (isBytes) return formatBytes(val)
					if (Math.abs(val) >= 1e9) return (val / 1e9).toFixed(1) + 'G'
					if (Math.abs(val) >= 1e6) return (val / 1e6).toFixed(1) + 'M'
					if (Math.abs(val) >= 1e3) return (val / 1e3).toFixed(1) + 'K'
					return Number.isInteger(val) ? val.toLocaleString() : val.toFixed(1)
				},
			},
		},
		tooltip: {
			x: { format: 'dd MMM yyyy HH:mm' },
			y: {
				formatter: (val) => isBytes ? formatBytes(val) : Number(val).toLocaleString(),
			},
			theme: 'dark',
		},
		grid: {
			borderColor: GRID_LINE,
			strokeDashArray: 3,
			xaxis: { lines: { show: false } },
			yaxis: { lines: { show: true } },
			padding: { top: 0, right: 8, bottom: 0, left: 8 },
		},
	}
}

export function buildDonutOptions(rows, metricId) {
	const isBytes = isBytesMetric(metricId)
	return {
		chart: {
			type: 'donut',
			fontFamily: 'inherit',
			background: 'transparent',
			animations: { enabled: true, speed: 350, easing: 'easeout' },
			toolbar: { show: false },
		},
		labels: rows.map(r => r.key),
		colors: COLORS,
		stroke: { width: 2, colors: ['var(--color-main-background, #fff)'] },
		plotOptions: {
			pie: {
				donut: {
					size: '70%',
					labels: {
						show: true,
						name: {
							show: true,
							fontSize: '12px',
							fontWeight: 500,
							color: TEXT_MUTED,
							offsetY: -4,
						},
						value: {
							show: true,
							fontSize: '22px',
							fontWeight: 700,
							offsetY: 6,
							color: TEXT_MAIN,
							formatter: (val) => isBytes ? formatBytes(Number(val)) : Number(val).toLocaleString(),
						},
						total: {
							show: true,
							showAlways: true,
							label: 'Total',
							fontSize: '12px',
							fontWeight: 500,
							color: TEXT_MUTED,
							formatter: (w) => {
								const sum = w.globals.seriesTotals.reduce((a, b) => a + b, 0)
								return isBytes ? formatBytes(sum) : sum.toLocaleString()
							},
						},
					},
				},
			},
		},
		dataLabels: { enabled: false },
		legend: {
			position: 'right',
			fontSize: '13px',
			fontWeight: 500,
			labels: { colors: TEXT_MAIN },
			itemMargin: { horizontal: 6, vertical: 4 },
			markers: { size: 7, shape: 'circle', offsetX: -4 },
			formatter: (label, opts) => {
				const v = opts.w.globals.series[opts.seriesIndex]
				const formatted = isBytes ? formatBytes(v) : Number(v).toLocaleString()
				return `${label} <span style="color:var(--color-text-maxcontrast)">${formatted}</span>`
			},
		},
		tooltip: {
			y: {
				formatter: (val) => isBytes ? formatBytes(val) : Number(val).toLocaleString(),
			},
			theme: 'dark',
		},
		responsive: [{
			breakpoint: 600,
			options: {
				legend: { position: 'bottom' },
			},
		}],
	}
}

export function sortedMapRows(map) {
	return Object.entries(map)
		.map(([key, value]) => ({
			key,
			value,
			numericValue: typeof value === 'number' ? value : 0,
		}))
		.sort((a, b) => {
			if (typeof a.value === 'number' && typeof b.value === 'number') {
				return b.value - a.value
			}
			if (typeof a.value === 'number') return -1
			if (typeof b.value === 'number') return 1
			return 0
		})
}

export function donutSeries(map) {
	return sortedMapRows(map)
		.filter(r => typeof r.numericValue === 'number' && r.numericValue > 0)
		.map(r => r.numericValue)
}

export function useDonut(map) {
	const numericEntries = Object.values(map).filter(v => typeof v === 'number' && v > 0)
	return numericEntries.length >= 2 && numericEntries.length <= 7
}

export function barWidth(numericValue, map) {
	if (!numericValue || numericValue <= 0) return 2
	const max = Math.max(
		...Object.values(map).map(v => (typeof v === 'number' && v > 0 ? v : 0)),
	)
	if (max <= 0) return 2
	return Math.max(4, Math.round((numericValue / max) * 100))
}
