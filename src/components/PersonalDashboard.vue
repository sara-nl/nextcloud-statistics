<template>
	<div ref="scrollRoot" :class="['sc-dashboard-scroll', densityClass]">
		<div ref="captureRoot" class="sc-dashboard">
			<DashboardHero
				:instance-label="instanceLabel"
				:instance-url="instanceUrl"
				:short-url="shortUrl"
				:nc-version="ncVersion"
				:timestamp="stats?.timestamp || ''"
				:hero-cards="heroCards"
				:loading="loading"
				:error="error"
				:stats="stats"
				:save-status="saveStatus"
				:save-status-label="saveStatusLabel"
				:export-menu-open="exportMenuOpen"
				@open-customize="openCustomize"
				@update:export-menu-open="exportMenuOpen = $event"
				@export="runExport" />

			<DashboardRangeBar
				v-if="!loading && !error && stats"
				:range="range"
				:presets="rangePresets"
				:history-loading="historyLoading"
				:history-count="history.length"
				@update:range="setRange" />

			<DashboardStates
				v-if="loading || error || !stats || collectorEntries.length === 0"
				:loading="loading"
				:error="error"
				:empty="!loading && !error && (!stats || collectorEntries.length === 0)" />

			<template v-else>
				<DashboardSpotlight
					v-if="numericMetricOptions.length > 0"
					:metric-key="spotlightKey"
					:metric-options="numericMetricOptions"
					:series="spotlightSeries"
					:options="spotlightOptions"
					:history-loading="historyLoading"
					:revealed="spotlightRevealed"
					:headline="spotlightHeadline"
					:subtitle="spotlightSubtitle"
					@update:metric-key="spotlightKey = $event" />

				<div class="sc-sections">
					<DashboardSection
						v-for="[collectorId, collectorData] in collectorEntries"
						:key="collectorId"
						:collector-id="collectorId"
						:data="collectorData"
						:series-for="seriesFor"
						:has-series-data="hasSeriesData"
						:trend-for="trendFor"
						:trends-animated="trendsAnimated"
						@select-metric="spotlightKey = $event" />
				</div>

				<footer class="sc-footer">
					<span v-if="stats?.timestamp">
						Snapshot taken {{ formatTimestamp(stats.timestamp) }}
					</span>
					<span class="sc-footer-spacer" />
					<span v-if="history.length" class="sc-footer-muted">
						{{ history.length }} historical snapshot{{ history.length === 1 ? '' : 's' }}
					</span>
				</footer>
			</template>
		</div>

		<CustomizeDrawer
			:open="customizeOpen"
			:preferences="preferences"
			:numeric-metric-options="numericMetricOptions"
			:ordered-section-ids="orderedSectionIds"
			@update:open="customizeOpen = $event"
			@update:preferences="onUpdatePreferences"
			@reset="onResetPreferences" />
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import DashboardHero from './dashboard/DashboardHero.vue'
import DashboardRangeBar from './dashboard/DashboardRangeBar.vue'
import DashboardSpotlight from './dashboard/DashboardSpotlight.vue'
import DashboardSection from './dashboard/DashboardSection.vue'
import DashboardStates from './dashboard/DashboardStates.vue'
import CustomizeDrawer from './dashboard/CustomizeDrawer.vue'

import { formatTimestamp } from '../utils/dashboardFormat.js'
import {
	RANGE_PRESETS,
	buildHeroCards,
	buildNumericMetricOptions,
	buildSpotlightSeries,
	buildSpotlightSubtitle,
	buildSpotlightHeadline,
	buildOrderedSectionIds,
	buildCollectorEntries,
	metricExists,
} from '../utils/dashboardConstants.js'
import { buildSpotlightOptions } from '../utils/dashboardCharts.js'
import { exportPrint, exportCsv, exportJson } from '../utils/dashboardExport.js'
import { createPreferencesController } from '../composables/usePreferences.js'
import { fetchDashboard, fetchHistory, seriesFor, trendFor } from '../composables/useDashboardData.js'

export default {
	name: 'PersonalDashboard',
	components: {
		DashboardHero,
		DashboardRangeBar,
		DashboardSpotlight,
		DashboardSection,
		DashboardStates,
		CustomizeDrawer,
	},
	data() {
		return {
			stats: null,
			loading: true,
			error: null,
			instanceLabel: loadState('stats_collector', 'instance_label', ''),
			range: '7d',
			rangePresets: RANGE_PRESETS,
			history: [],
			historyLoading: false,
			spotlightKey: '',
			exportMenuOpen: false,
			// One-shot reveal flags; flipped after the first paint so range
			// changes / metric swaps don't re-trigger the entrance animation.
			spotlightRevealed: false,
			trendsAnimated: false,
			prefs: createPreferencesController(loadState('stats_collector', 'preferences', null)),
			customizeOpen: false,
		}
	},
	computed: {
		instanceUrl() {
			return this.stats?.instance_url || ''
		},
		ncVersion() {
			return this.stats?.nextcloud_version
				|| this.stats?.collectors?.system?.nc_version
				|| ''
		},
		shortUrl() {
			if (!this.instanceUrl) return ''
			try {
				return new URL(this.instanceUrl).host
			} catch (e) {
				return this.instanceUrl
			}
		},
		preferences() {
			return this.prefs.state.preferences
		},
		saveStatus() {
			return this.prefs.state.saveStatus
		},
		orderedSectionIds() {
			return buildOrderedSectionIds(this.stats, this.preferences.section_order)
		},
		collectorEntries() {
			return buildCollectorEntries(this.stats, this.orderedSectionIds, this.preferences.hidden_sections)
		},
		heroCards() {
			return buildHeroCards(this.stats, this.preferences.hero_pinned)
		},
		densityClass() {
			return 'sc-density-' + (this.preferences.density || 'comfortable')
		},
		saveStatusLabel() {
			if (this.saveStatus === 'saving') return 'Saving customization'
			if (this.saveStatus === 'saved') return 'Customization saved'
			if (this.saveStatus === 'error') return "Couldn't save customization"
			return 'Customize dashboard'
		},
		numericMetricOptions() {
			return buildNumericMetricOptions(this.stats)
		},
		spotlightHeadline() {
			return buildSpotlightHeadline(this.spotlightKey)
		},
		spotlightSubtitle() {
			return buildSpotlightSubtitle(this.spotlightKey, this.range, this.rangePresets)
		},
		spotlightSeries() {
			return buildSpotlightSeries(this.spotlightKey, this.history)
		},
		spotlightOptions() {
			const [, metricId] = (this.spotlightKey || '').split('.')
			return buildSpotlightOptions(metricId)
		},
	},
	watch: {
		range() {
			this.loadHistory()
		},
	},
	async mounted() {
		document.addEventListener('click', this.closeExportMenu)
		document.addEventListener('keydown', this.onGlobalKeydown)
		try {
			const { stats, empty } = await fetchDashboard()
			if (empty) {
				this.stats = null
			} else {
				this.stats = stats
				const desired = this.preferences.default_spotlight
				if (desired && metricExists(this.stats, desired)) {
					this.spotlightKey = desired
				} else {
					const firstGroup = this.numericMetricOptions[0]
					if (firstGroup && firstGroup.metrics[0]) {
						this.spotlightKey = firstGroup.metrics[0].value
					}
				}
				this.loadHistory()
			}
		} catch (e) {
			this.error = e?.response?.data?.error || 'The dashboard service is currently unreachable.'
		} finally {
			this.loading = false
			this.$nextTick(() => {
				requestAnimationFrame(() => {
					this.spotlightRevealed = true
					this.trendsAnimated = true
				})
			})
		}
	},
	beforeUnmount() {
		document.removeEventListener('click', this.closeExportMenu)
		document.removeEventListener('keydown', this.onGlobalKeydown)
		this.prefs.dispose()
	},
	methods: {
		formatTimestamp,

		setRange(key) {
			this.range = key
		},
		closeExportMenu() {
			this.exportMenuOpen = false
		},
		onGlobalKeydown(e) {
			if (e.key === 'Escape' && this.customizeOpen) {
				this.closeCustomize()
			}
		},
		openCustomize() {
			this.customizeOpen = true
		},
		closeCustomize() {
			this.customizeOpen = false
		},

		runExport(format) {
			this.exportMenuOpen = false
			const ctx = {
				stats: this.stats,
				instanceLabel: this.instanceLabel,
				shortUrl: this.shortUrl,
				ncVersion: this.ncVersion,
			}
			if (format === 'pdf') exportPrint(ctx)
			else if (format === 'json') exportJson(ctx)
			else if (format === 'csv') exportCsv(ctx)
		},

		async loadHistory() {
			this.historyLoading = true
			try {
				this.history = await fetchHistory(this.range)
			} finally {
				this.historyLoading = false
			}
		},

		seriesFor(collectorId, metricId) {
			return seriesFor(this.history, collectorId, metricId)
		},
		hasSeriesData(collectorId, metricId) {
			return seriesFor(this.history, collectorId, metricId).length >= 2
		},
		trendFor(collectorId, metricId) {
			return trendFor(this.history, collectorId, metricId)
		},

		onUpdatePreferences(next) {
			this.prefs.update(next)
		},
		async onResetPreferences() {
			if (!window.confirm('Reset all dashboard customizations?')) return
			await this.prefs.reset()
			const firstGroup = this.numericMetricOptions[0]
			if (firstGroup && firstGroup.metrics[0]) {
				this.spotlightKey = firstGroup.metrics[0].value
			}
		},
	},
}
</script>

<style scoped>
/* Design tokens: 4pt spacing scale + density scaler. Shared with all child
   dashboard components via CSS-variable cascade. */
.sc-dashboard-scroll {
	--sc-density: 1;
	--space-2xs: calc(4px * var(--sc-density));
	--space-xs: calc(8px * var(--sc-density));
	--space-sm: calc(12px * var(--sc-density));
	--space-md: calc(16px * var(--sc-density));
	--space-lg: calc(24px * var(--sc-density));
	--space-xl: calc(36px * var(--sc-density));
	--space-2xl: calc(56px * var(--sc-density));
	--space-3xl: calc(88px * var(--sc-density));
	--radius-sm: 8px;
	--radius-md: 12px;
	--radius-lg: 18px;
	/* Density-aware sizing tokens. Defaults match Comfortable. The
	   .sc-density-{compact,dense} blocks below override these for stronger
	   layout differences than spacing-only scaling can deliver. */
	--sc-kpi-min: 180px;
	--sc-kpi-value-size: 24px;
	--sc-kpi-feature-size: 36px;
	--sc-kpi-label-size: 11px;
	--sc-hero-title-size: clamp(24px, 2.4vw, 32px);
	--sc-hero-stat-size: 26px;
	--sc-hero-stat-primary-size: 34px;
	--sc-section-headline-size: clamp(20px, 2.2vw, 26px);
	--sc-section-primary-size: 22px;
	--sc-section-title-size: 14px;
	--sc-spark-h: 56px;
	--sc-spark-feature-h: 72px;
	--sc-spotlight-h: 380px;
	--sc-spotlight-empty-h: 320px;
	--sc-donut-h: 220px;
	--sc-hero-pad: var(--space-xl);
	--surface-page: #f6f7f9;
	--surface-raised: var(--color-main-background, #fff);
	--surface-quiet: color-mix(in srgb, var(--color-main-text, #1a1d22) 3%, transparent);
	--rule: var(--color-border, #e7e9ec);
	--rule-soft: color-mix(in srgb, var(--color-border, #e7e9ec) 60%, transparent);
	--ease-out-quart: cubic-bezier(0.25, 1, 0.5, 1);
	--ease-out-quint: cubic-bezier(0.22, 1, 0.36, 1);
	--ease-out-expo: cubic-bezier(0.16, 1, 0.3, 1);
	width: 100%;
	min-height: 100%;
	background: var(--surface-page);
}

.sc-dashboard {
	width: 100%;
	max-width: var(--sc-page-max, none);
	margin-inline: auto;
	padding: var(--space-xl) clamp(var(--space-lg), 4vw, var(--space-2xl)) var(--space-3xl);
	color: var(--color-main-text);
	transition: max-width 220ms var(--ease-out-quart);
}

@media (max-width: 720px) {
	.sc-dashboard { padding: var(--space-lg) var(--space-md) var(--space-2xl); }
}

@media (max-width: 480px) {
	.sc-dashboard { padding: var(--space-md) var(--space-sm) var(--space-xl); }
	.sc-footer {
		font-size: 11px;
		gap: var(--space-sm);
	}
}

/* Sections + footer */
.sc-sections { display: flex; flex-direction: column; gap: var(--space-xl); }

.sc-sections > :deep(.sc-section + .sc-section) {
	padding-top: var(--space-xl);
	border-top: 1px solid var(--rule-soft);
	margin-top: 0;
}

.sc-footer {
	margin-top: var(--space-2xl);
	padding-top: var(--space-md);
	border-top: 1px solid var(--rule);
	display: flex;
	align-items: center;
	gap: var(--space-md);
	flex-wrap: wrap;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.sc-footer-spacer { flex: 1 1 auto; }
.sc-footer-muted { font-variant-numeric: tabular-nums; }

/* Dark theme + density */
@media (prefers-color-scheme: dark) {
	.sc-dashboard-scroll {
		--surface-page: #0e1116;
		--surface-quiet: rgba(255, 255, 255, 0.04);
	}
}

body[data-theme-dark] .sc-dashboard-scroll,
body[data-themes*="dark"] .sc-dashboard-scroll {
	--surface-page: #0e1116;
	--surface-quiet: rgba(255, 255, 255, 0.04);
}

.sc-dashboard-scroll.sc-density-comfortable {
	--sc-density: 1;
	--sc-page-max: none;
}

.sc-dashboard-scroll.sc-density-compact {
	--sc-density: 0.78;
	--sc-page-max: 1280px;
	--sc-kpi-min: 160px;
	--sc-kpi-value-size: 22px;
	--sc-kpi-feature-size: 32px;
	--sc-kpi-label-size: 10px;
	--sc-hero-title-size: clamp(22px, 2.1vw, 28px);
	--sc-hero-stat-size: 23px;
	--sc-hero-stat-primary-size: 30px;
	--sc-section-headline-size: clamp(18px, 2vw, 22px);
	--sc-section-primary-size: 20px;
	--sc-section-title-size: 13px;
	--sc-spark-h: 40px;
	--sc-spark-feature-h: 56px;
	--sc-spotlight-h: 320px;
	--sc-spotlight-empty-h: 260px;
	--sc-donut-h: 200px;
	--sc-hero-pad: var(--space-lg);
}

.sc-dashboard-scroll.sc-density-dense {
	--sc-density: 0.55;
	--sc-page-max: 1040px;
	--sc-kpi-min: 140px;
	--sc-kpi-value-size: 20px;
	--sc-kpi-feature-size: 28px;
	--sc-kpi-label-size: 10px;
	--sc-hero-title-size: clamp(20px, 1.8vw, 24px);
	--sc-hero-stat-size: 20px;
	--sc-hero-stat-primary-size: 26px;
	--sc-section-headline-size: clamp(16px, 1.7vw, 20px);
	--sc-section-primary-size: 18px;
	--sc-section-title-size: 12px;
	--sc-spark-h: 32px;
	--sc-spark-feature-h: 44px;
	--sc-spotlight-h: 260px;
	--sc-spotlight-empty-h: 220px;
	--sc-donut-h: 180px;
	--sc-hero-pad: var(--space-md);
}

/* Print / Save as PDF. The user's PDF export depends on this block. */
@media print {
	:deep(.sc-spotlight),
	:deep(.sc-kpi-trend),
	:deep(.sc-state-fade) {
		opacity: 1 !important;
		transform: none !important;
		animation: none !important;
		transition: none !important;
	}
	html, body {
		background: white !important;
		height: auto !important;
		overflow: visible !important;
		margin: 0 !important;
		padding: 0 !important;
	}
	body * { visibility: hidden !important; }
	#stats-collector-personal,
	#stats-collector-personal * { visibility: visible !important; }
	#stats-collector-personal {
		position: absolute !important;
		top: 0 !important;
		left: 0 !important;
		right: 0 !important;
		bottom: auto !important;
		width: 100% !important;
		height: auto !important;
		overflow: visible !important;
		background: white !important;
		z-index: auto !important;
	}
	.sc-dashboard-scroll {
		overflow: visible !important;
		height: auto !important;
		max-height: none !important;
	}
	.sc-dashboard {
		max-width: none !important;
		margin: 0 !important;
		padding: 0 !important;
		background: white !important;
	}
	:deep(.sc-no-export) { display: none !important; }
	:deep(.sc-section),
	:deep(.sc-spotlight),
	:deep(.sc-kpi) {
		break-inside: avoid;
		page-break-inside: avoid;
		box-shadow: none !important;
		border-color: #ddd !important;
	}
	:deep(.sc-hero) {
		break-after: avoid;
		page-break-after: avoid;
		background: white !important;
	}
	:deep(.apexcharts-canvas), :deep(.apexcharts-canvas *) { visibility: visible !important; }
	@page { size: A4; margin: 16mm; }
}
</style>
