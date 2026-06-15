import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

// Fetch the current personal dashboard snapshot. Returns either:
//   { stats: object, empty: false }
//   { stats: null, empty: true }
// Throws on network error.
export async function fetchDashboard() {
	const { data } = await axios.get(generateUrl('/apps/stats_collector/api/personal/dashboard'))
	if (data?.empty) return { stats: null, empty: true }
	return { stats: data, empty: false }
}

// Fetch the history snapshots for a given range. Returns an array (possibly
// empty); never throws (errors collapse to []).
export async function fetchHistory(range) {
	try {
		const { data } = await axios.get(
			generateUrl('/apps/stats_collector/api/personal/history'),
			{ params: { range } },
		)
		return Array.isArray(data?.snapshots) ? data.snapshots : []
	} catch (e) {
		return []
	}
}

// Extract a numeric series for one metric out of a history array.
export function seriesFor(history, collectorId, metricId) {
	return history
		.map(snap => {
			const v = snap.collectors?.[collectorId]?.[metricId]
			return typeof v === 'number' ? v : null
		})
		.filter(v => v !== null)
}

// Compute first-vs-last trend for a metric (or null if too short / no change).
export function trendFor(history, collectorId, metricId) {
	const series = seriesFor(history, collectorId, metricId)
	if (series.length < 2) return null
	const first = series[0]
	const last = series[series.length - 1]
	if (first === last) return null
	const diff = last - first
	const percent = first !== 0 ? Math.abs(Math.round((diff / first) * 100)) : null
	if (percent === null || percent === 0) return null
	return diff > 0
		? { dir: 'up', icon: '↑', percent }
		: { dir: 'down', icon: '↓', percent }
}
