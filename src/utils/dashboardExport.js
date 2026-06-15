import {
	formatCollectorName,
	formatMetricName,
	formatTimestamp,
	formatValue,
	formatSimpleValue,
	slugify,
} from './dashboardFormat.js'
import { sortedMapRows } from './dashboardCharts.js'

function esc(s) {
	return String(s ?? '').replace(/[&<>"']/g, c => ({
		'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
	}[c]))
}

export function exportFilenameBase({ instanceLabel, shortUrl }) {
	const label = slugify(instanceLabel || shortUrl || 'nextcloud')
	const date = new Date().toISOString().slice(0, 10)
	return `${label}_dashboard_${date}`
}

export function downloadBlob(content, filename, mime) {
	const blob = new Blob([content], { type: mime })
	const url = URL.createObjectURL(blob)
	const link = document.createElement('a')
	link.href = url
	link.download = filename
	document.body.appendChild(link)
	link.click()
	document.body.removeChild(link)
	setTimeout(() => URL.revokeObjectURL(url), 1000)
}

function csvCell(v) {
	if (v === null || v === undefined) return ''
	if (typeof v === 'object') return JSON.stringify(v)
	return String(v)
}

export function exportJson({ stats, instanceLabel, shortUrl }) {
	if (!stats) return
	const filename = `${exportFilenameBase({ instanceLabel, shortUrl })}.json`
	downloadBlob(
		JSON.stringify(stats, null, 2),
		filename,
		'application/json',
	)
}

export function exportCsv({ stats, instanceLabel, shortUrl }) {
	if (!stats?.collectors) return
	const rows = [['collector', 'metric', 'key', 'value']]
	for (const [collectorId, metrics] of Object.entries(stats.collectors)) {
		for (const [metricId, value] of Object.entries(metrics)) {
			if (value === null || value === undefined) continue
			if (typeof value === 'object' && !Array.isArray(value)) {
				for (const [k, v] of Object.entries(value)) {
					rows.push([collectorId, metricId, k, csvCell(v)])
				}
			} else {
				rows.push([collectorId, metricId, '', csvCell(value)])
			}
		}
	}
	const csv = rows.map(r => r.map(c => {
		const s = String(c).replace(/"/g, '""')
		return /[",\n]/.test(s) ? `"${s}"` : s
	}).join(',')).join('\n')
	downloadBlob(csv, `${exportFilenameBase({ instanceLabel, shortUrl })}.csv`, 'text/csv;charset=utf-8')
}

// Build a self-contained printable HTML report from the snapshot data,
// open it in a popup, and trigger print. Doesn't depend on cloning the
// rendered DOM; just renders the data fresh with inline styles so it's
// guaranteed visible in the print dialog.
export function exportPrint({ stats, instanceLabel, shortUrl, ncVersion }) {
	if (!stats) return

	const title = `${instanceLabel || 'Stats Collector'} Dashboard`
	const date = new Date().toLocaleString()
	const collectors = stats.collectors || {}

	let body = ''
	for (const [collectorId, metrics] of Object.entries(collectors)) {
		const scalars = []
		const maps = []
		for (const [metricId, value] of Object.entries(metrics)) {
			if (value === null || value === undefined) continue
			if (typeof value === 'object' && !Array.isArray(value)) {
				maps.push([metricId, value])
			} else {
				scalars.push([metricId, value])
			}
		}

		body += `<section class="sc"><h2>${esc(formatCollectorName(collectorId))}</h2>`

		if (scalars.length > 0) {
			body += '<div class="kpi-grid">'
			for (const [mid, v] of scalars) {
				body += `<div class="kpi"><div class="kpi-label">${esc(formatMetricName(mid))}</div><div class="kpi-value">${esc(formatValue(v, mid))}</div></div>`
			}
			body += '</div>'
		}

		for (const [mid, mapVal] of maps) {
			const sorted = sortedMapRows(mapVal)
			body += `<div class="map"><h3>${esc(formatMetricName(mid))}</h3><table>`
			for (const row of sorted) {
				body += `<tr><td>${esc(row.key)}</td><td class="num">${esc(formatSimpleValue(row.value, mid))}</td></tr>`
			}
			body += '</table></div>'
		}

		body += '</section>'
	}

	const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>${esc(title)}</title>
<style>
	@page { size: A4; margin: 14mm; }
	* { box-sizing: border-box; }
	html, body { margin: 0; padding: 0; }
	body {
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
		color: #1a1d22;
		background: white;
		font-size: 12pt;
		line-height: 1.45;
		padding: 0;
	}
	header.report {
		border-bottom: 2px solid #0082c9;
		padding-bottom: 12px;
		margin-bottom: 18px;
	}
	header.report h1 {
		margin: 0 0 4px;
		font-size: 22pt;
		font-weight: 700;
		color: #1a1d22;
	}
	header.report .meta {
		color: #666;
		font-size: 10pt;
	}
	section.sc {
		margin-bottom: 22px;
		break-inside: avoid;
		page-break-inside: avoid;
	}
	section.sc h2 {
		font-size: 14pt;
		font-weight: 600;
		margin: 0 0 10px;
		color: #0082c9;
		border-bottom: 1px solid #e5e7eb;
		padding-bottom: 4px;
	}
	.kpi-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
		gap: 8px;
		margin-bottom: 12px;
	}
	.kpi {
		border: 1px solid #e5e7eb;
		border-radius: 6px;
		padding: 10px 12px;
		background: #fafbfc;
	}
	.kpi-label {
		font-size: 8pt;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: #6b7280;
		margin-bottom: 4px;
	}
	.kpi-value {
		font-size: 16pt;
		font-weight: 700;
		color: #1a1d22;
	}
	.map {
		break-inside: avoid;
		page-break-inside: avoid;
		margin-bottom: 12px;
	}
	.map h3 {
		font-size: 11pt;
		font-weight: 600;
		margin: 8px 0 6px;
		color: #374151;
	}
	.map table {
		width: 100%;
		border-collapse: collapse;
		font-size: 10pt;
	}
	.map td {
		padding: 4px 8px;
		border-bottom: 1px solid #f0f1f3;
	}
	.map td.num {
		text-align: right;
		font-variant-numeric: tabular-nums;
		font-weight: 600;
		white-space: nowrap;
	}
	footer.report {
		margin-top: 24px;
		padding-top: 10px;
		border-top: 1px solid #e5e7eb;
		font-size: 9pt;
		color: #6b7280;
		text-align: center;
	}
</style>
</head>
<body>
<header class="report">
	<h1>${esc(instanceLabel || 'Nextcloud')}</h1>
	<div class="meta">
		${esc(shortUrl ? shortUrl + ' · ' : '')}Nextcloud ${esc(ncVersion || '')} · Snapshot: ${esc(formatTimestamp(stats.timestamp || ''))}
	</div>
</header>
${body}
<footer class="report">
	Generated ${esc(date)}
</footer>
</body>
</html>`

	const win = window.open('', '_blank', 'width=900,height=1100')
	if (!win) {
		window.alert('Could not open print window. Please allow pop-ups for this site.')
		return
	}
	win.document.open()
	win.document.write(html)
	win.document.close()

	setTimeout(() => {
		win.focus()
		win.print()
		setTimeout(() => win.close(), 500)
	}, 300)
}
