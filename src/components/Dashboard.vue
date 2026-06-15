<template>
	<div class="settings-section">
		<div class="section-header">
			<h2>Dashboard</h2>
			<button class="btn btn-tertiary" :disabled="loading" @click="loadStats">
				{{ loading ? 'Loading...' : 'Refresh' }}
			</button>
		</div>

		<div v-if="loading && !stats" class="loading">
			Loading...
		</div>

		<div v-else-if="error" class="dash-error">
			{{ error }}
		</div>

		<template v-else-if="stats">
			<!-- Instance info bar -->
			<div class="instance-bar">
				<span v-if="stats.instance_label" class="instance-label">{{ stats.instance_label }}</span>
				<span class="instance-detail">{{ stats.instance_url }}</span>
				<span class="instance-detail">NC {{ stats.nextcloud_version }}</span>
				<span class="instance-detail">{{ formatDate(stats.timestamp) }}</span>
				<span v-if="dataAge" :class="['instance-detail', 'data-age', { stale: isStale }]">{{ dataAge }}</span>
			</div>

			<!-- Collector sections -->
			<div v-for="(collectorData, collectorId) in stats.collectors" :key="collectorId" class="dash-collector">
				<div class="dash-collector-header">
					<span class="collector-icon">{{ getIcon(collectorId) }}</span>
					<h3>{{ formatCollectorName(collectorId) }}</h3>
				</div>

				<div class="dash-metrics-grid">
					<template v-for="(value, metricId) in collectorData" :key="metricId">
						<!-- Object/map values: render as collapsible list card -->
						<div v-if="isObject(value)" class="dash-metric-card dash-metric-card-wide">
							<div class="dash-map-header" @click="toggleMap(collectorId + '.' + metricId)">
								<span class="dash-metric-label">{{ formatMetricName(metricId) }}</span>
								<span class="dash-map-toggle">
									<span class="badge">{{ Object.keys(value).length }}</span>
									<span class="expand-icon">{{ isMapOpen(collectorId + '.' + metricId) ? '▾' : '▸' }}</span>
								</span>
							</div>
							<div v-if="isMapOpen(collectorId + '.' + metricId)" class="dash-map-list">
								<div v-for="(v, k) in value" :key="k" class="dash-map-row">
									<span class="dash-map-key">{{ k }}</span>
									<span class="dash-map-value">{{ formatSimpleValue(v, metricId) }}</span>
								</div>
								<div v-if="Object.keys(value).length === 0" class="dash-map-empty">No data</div>
							</div>
						</div>
						<!-- Scalar values: normal card -->
						<div v-else class="dash-metric-card">
							<div class="dash-metric-value">
								{{ formatValue(value, metricId) }}
								<span v-if="getTrend(collectorId, metricId)" :class="['trend', getTrend(collectorId, metricId).dir]"
									:title="getTrend(collectorId, metricId).label">
									{{ getTrend(collectorId, metricId).icon }}
								</span>
							</div>
							<div class="dash-metric-label">{{ formatMetricName(metricId) }}</div>
						</div>
					</template>
				</div>
			</div>

			<div v-if="Object.keys(stats.collectors || {}).length === 0" class="empty-state">
				No metrics enabled yet. Enable some in the Collectors tab, then click Refresh.
			</div>
		</template>
	</div>
</template>

<script>
import api from '../services/api.js'
import { ICON_MAP, timeAgo } from '../services/utils.js'

export default {
	name: 'StatsDashboard',
	data() {
		return {
			stats: null,
			previousStats: null,
			loading: false,
			error: null,
			openMaps: {},
		}
	},
	computed: {
		dataAge() {
			if (!this.stats?.timestamp) return ''
			return timeAgo(this.stats.timestamp)
		},
		isStale() {
			if (!this.stats?.timestamp) return false
			const age = Date.now() - new Date(this.stats.timestamp).getTime()
			return age > 2 * 60 * 60 * 1000 // > 2 hours
		},
	},
	async mounted() {
		await this.loadCached()
		this.loadPrevious()
	},
	methods: {
		async loadCached() {
			this.loading = true
			this.error = null
			try {
				const { data } = await api.getDashboard()
				if (data.empty) {
					this.stats = null
				} else {
					this.stats = data
				}
			} catch (e) {
				this.error = 'Failed to load dashboard: ' + e.message
			} finally {
				this.loading = false
			}
		},

		async loadStats() {
			this.loading = true
			this.error = null
			try {
				const { data } = await api.collectNow()
				this.stats = data
				this.loadPrevious()
			} catch (e) {
				this.error = 'Failed to collect statistics: ' + e.message
			} finally {
				this.loading = false
			}
		},

		async loadPrevious() {
			try {
				// Get the second-most-recent snapshot for trend comparison
				const { data } = await api.getSnapshots({ limit: 2, include_payload: 'true' })
				if (Array.isArray(data) && data.length >= 2 && data[1].payload) {
					this.previousStats = data[1].payload
				}
			} catch (e) {
				// Not critical, just no trend indicators
			}
		},

		getTrend(collectorId, metricId) {
			if (!this.previousStats?.collectors) return null
			const prev = this.previousStats.collectors[collectorId]?.[metricId]
			const curr = this.stats?.collectors?.[collectorId]?.[metricId]
			if (typeof prev !== 'number' || typeof curr !== 'number') return null
			if (prev === curr) return null
			const diff = curr - prev
			const pct = prev !== 0 ? Math.abs(Math.round((diff / prev) * 100)) : null
			const pctLabel = pct !== null ? ` (${pct}%)` : ''
			if (diff > 0) {
				return { dir: 'up', icon: '↑', label: `+${diff.toLocaleString()}${pctLabel} since last collection` }
			}
			return { dir: 'down', icon: '↓', label: `${diff.toLocaleString()}${pctLabel} since last collection` }
		},

		toggleMap(key) {
			this.openMaps[key] = !this.openMaps[key]
		},

		isMapOpen(key) {
			return !!this.openMaps[key]
		},

		isObject(value) {
			return value !== null && typeof value === 'object' && !Array.isArray(value)
		},

		getIcon(collectorId) {
			return ICON_MAP[collectorId] || '📦'
		},

		formatCollectorName(id) {
			return id.charAt(0).toUpperCase() + id.slice(1)
		},

		formatMetricName(id) {
			return id
				.replace(/_/g, ' ')
				.replace(/\b\w/g, c => c.toUpperCase())
				.replace(/24h/i, '24h')
				.replace(/7d/i, '7d')
				.replace(/30d/i, '30d')
				.replace(/Nc /i, 'NC ')
				.replace(/Php /i, 'PHP ')
				.replace(/Db /i, 'DB ')
		},

		formatSimpleValue(value, metricId) {
			if (value === null || value === undefined) return '—'
			if (typeof value === 'number') {
				if (metricId.includes('bytes') || metricId.includes('storage') || metricId.includes('disk_space') || metricId.includes('db_size')) {
					return this.formatBytes(value)
				}
				return value.toLocaleString()
			}
			return String(value)
		},

		formatValue(value, metricId) {
			if (value === null || value === undefined) return '—'

			if (Array.isArray(value)) {
				return value.length + ' items'
			}

			// Bytes formatting
			if (metricId.includes('bytes') || metricId.includes('storage') || metricId.includes('disk_space') || metricId.includes('db_size')) {
				return this.formatBytes(value)
			}

			// Numbers
			if (typeof value === 'number') {
				return value.toLocaleString()
			}

			// Strings (versions, etc.)
			return String(value)
		},

		formatBytes(bytes) {
			if (bytes === 0) return '0 B'
			const units = ['B', 'KB', 'MB', 'GB', 'TB']
			const i = Math.floor(Math.log(Math.abs(bytes)) / Math.log(1024))
			const val = bytes / Math.pow(1024, i)
			return val.toFixed(i > 0 ? 1 : 0) + ' ' + units[i]
		},

		formatDate(dateStr) {
			if (!dateStr) return ''
			return new Date(dateStr).toLocaleString()
		},
	},
}
</script>

<style scoped>
.loading,
.empty-state {
	padding: 48px 20px;
	text-align: center;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
}

.dash-error {
	padding: 14px 18px;
	background: var(--color-error);
	color: white;
	border-radius: var(--border-radius-large);
	font-size: 14px;
}

/* Instance info bar */
.instance-bar {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 6px 16px;
	padding: 12px 16px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius-large);
	margin-bottom: 28px;
	font-size: 13px;
}

.instance-label {
	font-weight: 600;
	color: var(--color-main-text);
}

.instance-detail {
	color: var(--color-text-maxcontrast);
}

.data-age {
	font-weight: 500;
}

.data-age.stale {
	color: var(--color-warning) !important;
}

.instance-detail::before {
	content: '·';
	margin-right: 6px;
	color: var(--color-text-maxcontrast);
	opacity: 0.5;
}

/* Collector sections */
.dash-collector {
	margin-bottom: 32px;
	padding-bottom: 28px;
	border-bottom: 1px solid var(--color-border);
}

.dash-collector:last-child {
	border-bottom: none;
	margin-bottom: 0;
	padding-bottom: 0;
}

.dash-collector-header {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 16px;
}

.dash-collector-header .collector-icon {
	font-size: 22px;
	line-height: 1;
}

.dash-collector-header h3 {
	margin: 0;
	font-size: 17px;
	font-weight: 600;
	color: var(--color-main-text);
}

/* Metric cards grid */
.dash-metrics-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
	gap: 12px;
}

.dash-metric-card {
	padding: 16px 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	transition: border-color 0.15s;
}

.dash-metric-card:hover {
	border-color: var(--color-border-dark, var(--color-text-maxcontrast));
}

.dash-metric-card-wide {
	grid-column: 1 / -1;
	background: var(--color-background-dark);
	border: none;
	border-radius: var(--border-radius-large);
	padding: 14px 18px;
}

.dash-metric-value {
	font-size: 24px;
	font-weight: 700;
	color: var(--color-main-text);
	margin-bottom: 6px;
	word-break: break-word;
	line-height: 1.2;
}

.trend {
	font-size: 14px;
	font-weight: 600;
	margin-left: 6px;
	vertical-align: middle;
}

.trend.up { color: var(--color-success, #46ba61); }
.trend.down { color: var(--color-error, #e9322d); }

.dash-metric-label {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	line-height: 1.4;
	text-transform: uppercase;
	letter-spacing: 0.02em;
}

/* Collapsible map cards */
.dash-map-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	cursor: pointer;
	padding: 2px 0;
	user-select: none;
}

.dash-map-header .dash-metric-label {
	font-size: 13px;
	font-weight: 500;
	text-transform: none;
	letter-spacing: 0;
	color: var(--color-main-text);
}

.dash-map-toggle {
	display: flex;
	align-items: center;
	gap: 8px;
}

.dash-map-toggle .badge {
	font-size: 11px;
	font-weight: 600;
	padding: 2px 10px;
	border-radius: 999px;
	background: var(--color-main-background);
	color: var(--color-text-maxcontrast);
}

.expand-icon {
	font-size: 14px;
	color: var(--color-text-maxcontrast);
	transition: transform 0.15s;
}

.dash-map-list {
	margin-top: 10px;
	padding-top: 10px;
	border-top: 1px solid var(--color-border);
}

.dash-map-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 6px 4px;
	border-radius: var(--border-radius);
	font-size: 13px;
}

.dash-map-row:nth-child(odd) {
	background: var(--color-main-background);
}

.dash-map-key {
	color: var(--color-main-text);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	margin-right: 16px;
}

.dash-map-value {
	font-weight: 600;
	color: var(--color-main-text);
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
}

.dash-map-empty {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-style: italic;
	padding: 4px;
}
</style>
