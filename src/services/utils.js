export const ICON_MAP = {
	users: '👥',
	files: '📁',
	shares: '🔗',
	system: '⚙️',
	talk: '💬',
	deck: '📋',
	mail: '📧',
	calendar: '📅',
	activity: '📊',
	forms: '📝',
	contacts: '👤',
	richdocuments: '📄',
}

export const COLORS = [
	'#0082c9', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6',
	'#1abc9c', '#e67e22', '#3498db', '#e91e63', '#00bcd4',
]

export function getIcon(collectorId) {
	return ICON_MAP[collectorId] || '📦'
}

export function formatCollectorName(id) {
	return id.charAt(0).toUpperCase() + id.slice(1)
}

export function formatMetricName(id) {
	return id
		.replace(/_/g, ' ')
		.replace(/\b\w/g, c => c.toUpperCase())
		.replace(/24h/i, '24h')
		.replace(/7d/i, '7d')
		.replace(/30d/i, '30d')
		.replace(/Nc /i, 'NC ')
		.replace(/Php /i, 'PHP ')
		.replace(/Db /i, 'DB ')
}

export function formatBytes(bytes) {
	if (!bytes && bytes !== 0) return '—'
	if (bytes === 0) return '0 B'
	const units = ['B', 'KB', 'MB', 'GB', 'TB']
	const i = Math.floor(Math.log(Math.abs(bytes)) / Math.log(1024))
	const val = bytes / Math.pow(1024, i)
	return val.toFixed(i > 0 ? 1 : 0) + ' ' + units[i]
}

export function formatNumber(val) {
	if (val === null || val === undefined) return '—'
	return Number(val).toLocaleString()
}

export function formatDate(dateStr) {
	if (!dateStr) return ''
	return new Date(dateStr).toLocaleString()
}

export function formatValue(value, metricId) {
	if (value === null || value === undefined) return '—'
	if (Array.isArray(value)) return value.length + ' items'
	if (typeof value === 'object') return Object.keys(value).length + ' items'
	if (metricId && (metricId.includes('bytes') || metricId.includes('storage') || metricId.includes('disk_space') || metricId.includes('db_size'))) {
		return formatBytes(value)
	}
	if (typeof value === 'number') return value.toLocaleString()
	return String(value)
}

export function timeAgo(dateStr) {
	if (!dateStr) return ''
	const now = new Date()
	const then = new Date(dateStr)
	const diff = Math.floor((now - then) / 1000)

	if (diff < 60) return 'just now'
	if (diff < 3600) return Math.floor(diff / 60) + 'm ago'
	if (diff < 86400) return Math.floor(diff / 3600) + 'h ago'
	return Math.floor(diff / 86400) + 'd ago'
}

export function confirm(message) {
	return window.confirm(message)
}
