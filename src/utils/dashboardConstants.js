import { isObject, formatMetricName, formatValue, formatCollectorName } from './dashboardFormat.js'

export const DEFAULT_PREFERENCES = Object.freeze({
	hidden_sections: [],
	section_order: [],
	hero_pinned: [],
	default_spotlight: '',
	density: 'comfortable',
})

export function normalizePreferences(input) {
	const base = { ...DEFAULT_PREFERENCES }
	if (!input || typeof input !== 'object') return base
	if (Array.isArray(input.hidden_sections)) {
		base.hidden_sections = input.hidden_sections.filter(s => typeof s === 'string')
	}
	if (Array.isArray(input.section_order)) {
		base.section_order = input.section_order.filter(s => typeof s === 'string')
	}
	if (Array.isArray(input.hero_pinned)) {
		base.hero_pinned = input.hero_pinned
			.filter(s => typeof s === 'string' && s.includes('.'))
			.slice(0, 4)
	}
	if (typeof input.default_spotlight === 'string') {
		base.default_spotlight = input.default_spotlight
	}
	if (['comfortable', 'compact', 'dense'].includes(input.density)) {
		base.density = input.density
	}
	return base
}

// Heuristics: which scalar metrics deserve a spot in the hero summary row.
// Order matters; first matches win, capped to 4.
export const HERO_PRIORITY = [
	{ collector: 'users', metric: 'total_users', label: 'Users' },
	{ collector: 'files', metric: 'total_files', label: 'Files' },
	{ collector: 'files', metric: 'total_storage_bytes', label: 'Storage' },
	{ collector: 'shares', metric: 'total_shares', label: 'Shares' },
	{ collector: 'calendar', metric: 'total_events', label: 'Events' },
	{ collector: 'talk', metric: 'total_rooms', label: 'Talk rooms' },
	{ collector: 'deck', metric: 'total_cards', label: 'Deck cards' },
	{ collector: 'richdocuments', metric: 'active_sessions', label: 'Active docs' },
]

// Per-collector "primary" metric promoted into the section header
// (so the section's headline number is inline text, not a card).
export const SECTION_PRIMARY = {
	users: 'total_users',
	files: 'total_storage_bytes',
	shares: 'total_shares',
	system: 'php_version',
	talk: 'total_rooms',
	deck: 'total_cards',
	mail: 'total_messages',
	calendar: 'total_events',
	activity: 'total_activities',
	forms: 'total_forms',
	contacts: 'total_contacts',
	richdocuments: 'active_sessions',
}

export const RANGE_PRESETS = [
	{ key: '24h', label: '24h' },
	{ key: '7d', label: '7 days' },
	{ key: '30d', label: '30 days' },
	{ key: '90d', label: '90 days' },
	{ key: 'all', label: 'All' },
]

export const DENSITY_OPTIONS = [
	{ key: 'comfortable', label: 'Comfortable' },
	{ key: 'compact', label: 'Compact' },
	{ key: 'dense', label: 'Dense' },
]

// Build the hero KPI strip. Pinned preferences win; falls back to HERO_PRIORITY.
export function buildHeroCards(stats, pinned) {
	if (!stats?.collectors) return []
	if (Array.isArray(pinned) && pinned.length > 0) {
		const cards = []
		for (const key of pinned) {
			const [collectorId, metricId] = key.split('.')
			if (!collectorId || !metricId) continue
			const c = stats.collectors[collectorId]
			if (!c) continue
			const v = c[metricId]
			if (v === undefined || v === null || isObject(v) || Array.isArray(v)) continue
			cards.push({ label: formatMetricName(metricId), value: formatValue(v, metricId) })
			if (cards.length >= 4) break
		}
		if (cards.length > 0) return cards
	}
	const cards = []
	for (const def of HERO_PRIORITY) {
		const c = stats.collectors[def.collector]
		if (!c) continue
		const v = c[def.metric]
		if (v === undefined || v === null || isObject(v) || Array.isArray(v)) continue
		cards.push({ label: def.label, value: formatValue(v, def.metric) })
		if (cards.length >= 4) break
	}
	return cards
}

// All numeric scalar metrics across collectors, grouped by collector,
// for spotlight + customize-drawer dropdowns.
export function buildNumericMetricOptions(stats) {
	if (!stats?.collectors) return []
	const groups = []
	for (const [collectorId, data] of Object.entries(stats.collectors)) {
		const metrics = []
		for (const [metricId, value] of Object.entries(data)) {
			if (typeof value === 'number') {
				metrics.push({ value: collectorId + '.' + metricId, label: formatMetricName(metricId) })
			}
		}
		if (metrics.length > 0) {
			metrics.sort((a, b) => a.label.localeCompare(b.label))
			groups.push({ collectorId, metrics })
		}
	}
	return groups
}

// Resolve the spotlight metric key into a series + a subtitle string.
export function buildSpotlightSeries(spotlightKey, history) {
	const [collectorId, metricId] = (spotlightKey || '').split('.')
	if (!collectorId || !metricId) return [{ name: '', data: [] }]
	const points = history
		.map(snap => {
			const v = snap.collectors?.[collectorId]?.[metricId]
			if (typeof v !== 'number') return null
			return [new Date(snap.timestamp).getTime(), v]
		})
		.filter(p => p !== null)
	return [{ name: formatMetricName(metricId), data: points }]
}

export function buildSpotlightSubtitle(spotlightKey, range, presets) {
	const [collectorId, metricId] = (spotlightKey || '').split('.')
	if (!collectorId || !metricId) return ''
	const presetLabel = presets.find(p => p.key === range)?.label || range
	return `${formatCollectorName(collectorId)} · last ${presetLabel}`
}

export function buildSpotlightHeadline(spotlightKey) {
	const [, metricId] = (spotlightKey || '').split('.')
	if (!metricId) return 'Trend over time'
	return formatMetricName(metricId)
}

// Apply user-defined section ordering on top of the backend-provided list
// of collectors. Newly enabled collectors append at the end.
export function buildOrderedSectionIds(stats, savedOrder) {
	if (!stats?.collectors) return []
	const all = Object.keys(stats.collectors)
	const order = Array.isArray(savedOrder) ? savedOrder : []
	const known = new Set(all)
	const ordered = order.filter(id => known.has(id))
	const seen = new Set(ordered)
	for (const id of all) {
		if (!seen.has(id)) ordered.push(id)
	}
	return ordered
}

// Filter stats by hidden_sections + section_order, return [id, data] pairs.
export function buildCollectorEntries(stats, orderedIds, hiddenSections) {
	if (!stats?.collectors) return []
	const hidden = new Set(hiddenSections || [])
	return orderedIds
		.filter(id => !hidden.has(id))
		.map(id => [id, stats.collectors[id]])
}

export function metricExists(stats, key) {
	if (!key || !stats?.collectors) return false
	const [collectorId, metricId] = String(key).split('.')
	if (!collectorId || !metricId) return false
	const c = stats.collectors[collectorId]
	if (!c) return false
	return typeof c[metricId] === 'number'
}
