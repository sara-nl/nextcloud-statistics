<template>
	<div ref="root" class="sc-map" :class="{ 'is-collapsed': isCollapsible && !expanded }">
		<button
			v-if="isCollapsible"
			type="button"
			class="sc-map-head sc-map-head-button"
			:aria-expanded="expanded ? 'true' : 'false'"
			@click="expanded = !expanded">
			<span class="sc-map-title-wrap">
				<span class="sc-map-chevron" :class="{ 'is-open': expanded }" aria-hidden="true">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
						stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="9 18 15 12 9 6" />
					</svg>
				</span>
				<h3 class="sc-map-title">{{ formatMetricName(metricId) }}</h3>
			</span>
			<span class="sc-map-total">
				{{ entryCount }}
				{{ entryCount === 1 ? 'entry' : 'entries' }}
			</span>
		</button>
		<div v-else class="sc-map-head">
			<span class="sc-map-title-wrap">
				<h3 class="sc-map-title">{{ formatMetricName(metricId) }}</h3>
			</span>
			<span class="sc-map-total">
				{{ entryCount }}
				{{ entryCount === 1 ? 'entry' : 'entries' }}
			</span>
		</div>
		<div v-show="!isCollapsible || expanded" class="sc-map-body">
			<div v-if="entryCount === 0" class="sc-map-empty">No data available</div>
			<div v-else-if="renderDonut" class="sc-donut-wrap" :style="donutWrapStyle">
				<div v-if="!donutReady" class="sc-donut-placeholder" aria-hidden="true" />
				<apexchart v-else
					type="donut"
					:height="donutHeight"
					:options="donutOpts"
					:series="donutSeriesData" />
			</div>
			<ul v-else class="sc-bars">
				<li v-for="row in rows" :key="row.key" class="sc-bar-row">
					<div class="sc-bar-label" :title="row.key">{{ formatMapKey(metricId, row.key) }}</div>
					<div class="sc-bar-track">
						<div
							class="sc-bar-fill"
							:style="{ width: barWidth(row.numericValue, value) + '%' }" />
					</div>
					<div class="sc-bar-value">{{ formatSimpleValue(row.value, metricId) }}</div>
				</li>
			</ul>
		</div>
	</div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts'
import { formatMetricName, formatSimpleValue, formatMapKey } from '../../utils/dashboardFormat.js'
import {
	buildDonutOptions,
	donutSeries,
	sortedMapRows,
	useDonut,
	barWidth,
} from '../../utils/dashboardCharts.js'

const COLLAPSE_THRESHOLD = 8

export default {
	name: 'DashboardMap',
	components: { apexchart: VueApexCharts },
	props: {
		metricId: { type: String, required: true },
		value: { type: Object, required: true },
	},
	data() {
		return {
			// Donut charts (ApexCharts) only mount when the section enters the
			// viewport. On a tall dashboard this delays multiple full-blown
			// SVG chart instantiations off the critical path.
			donutReady: false,
			donutHeight: 220,
			observer: null,
			expanded: false,
		}
	},
	computed: {
		entryCount() {
			return Object.keys(this.value).length
		},
		renderDonut() {
			return useDonut(this.value)
		},
		isCollapsible() {
			// Donut mode stays open (it's compact); only collapse the long bar lists.
			return !this.renderDonut && this.entryCount > COLLAPSE_THRESHOLD
		},
		rows() {
			return sortedMapRows(this.value)
		},
		donutSeriesData() {
			return donutSeries(this.value)
		},
		donutOpts() {
			return buildDonutOptions(this.rows.filter(r => typeof r.numericValue === 'number' && r.numericValue > 0), this.metricId)
		},
		donutWrapStyle() {
			return { minHeight: 'var(--sc-donut-h, 220px)' }
		},
	},
	watch: {
		expanded(open) {
			if (open && this.renderDonut && !this.donutReady) {
				this.donutReady = true
			}
		},
	},
	mounted() {
		this.measureDonutHeight()
		if (!this.renderDonut) return
		if (typeof IntersectionObserver === 'undefined') {
			this.donutReady = true
			return
		}
		this.observer = new IntersectionObserver((entries) => {
			for (const entry of entries) {
				if (entry.isIntersecting) {
					this.donutReady = true
					this.disconnectObserver()
					break
				}
			}
		}, { rootMargin: '200px' })
		this.observer.observe(this.$refs.root)
	},
	beforeUnmount() {
		this.disconnectObserver()
	},
	methods: {
		formatMetricName,
		formatSimpleValue,
		formatMapKey,
		barWidth,
		disconnectObserver() {
			if (this.observer) {
				this.observer.disconnect()
				this.observer = null
			}
		},
		measureDonutHeight() {
			try {
				const cs = getComputedStyle(this.$refs.root)
				const raw = cs.getPropertyValue('--sc-donut-h').trim()
				const n = parseInt(raw, 10)
				if (Number.isFinite(n) && n > 0) {
					this.donutHeight = n
				}
			} catch (e) { /* fall back to default */ }
		},
	},
}
</script>

<style scoped>
.sc-map {
	background: var(--surface-raised);
	border: 1px solid var(--rule);
	border-radius: var(--radius-md);
	padding: var(--space-md) var(--space-lg);
}

.sc-map.is-collapsed {
	padding-bottom: var(--space-sm);
}

.sc-map-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: var(--space-xs);
	margin-bottom: var(--space-md);
	padding-bottom: var(--space-xs);
	border-bottom: 1px solid var(--rule-soft);
}

.sc-map.is-collapsed .sc-map-head {
	margin-bottom: 0;
	padding-bottom: 0;
	border-bottom: none;
}

.sc-map-head-button {
	width: 100%;
	background: transparent;
	border: none;
	padding: 0 0 var(--space-xs) 0;
	font: inherit;
	color: inherit;
	text-align: left;
	cursor: pointer;
	border-radius: 4px;
	transition: color 150ms ease;
}

.sc-map-head-button:hover .sc-map-title { color: var(--color-primary, #0082c9); }
.sc-map-head-button:focus-visible {
	outline: 2px solid var(--color-primary, #0082c9);
	outline-offset: 3px;
}

.sc-map.is-collapsed .sc-map-head-button { padding-bottom: 0; }

.sc-map-title-wrap {
	display: inline-flex;
	align-items: center;
	gap: var(--space-2xs, 4px);
	min-width: 0;
}

.sc-map-chevron {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	color: var(--color-text-maxcontrast);
	transition: transform 200ms var(--ease-out-quart, ease-out);
	flex-shrink: 0;
}

.sc-map-chevron.is-open { transform: rotate(90deg); }

.sc-map-title {
	margin: 0;
	font-size: 13px;
	font-weight: 600;
	color: var(--color-main-text);
	letter-spacing: -0.005em;
	transition: color 150ms ease;
}

.sc-map-total {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	font-variant-numeric: tabular-nums;
}

.sc-map-empty {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	font-style: italic;
	padding: var(--space-xs) 0;
}

.sc-donut-wrap { margin-top: var(--space-2xs); padding: var(--space-2xs) 0; }

.sc-donut-placeholder {
	width: 100%;
	height: var(--sc-donut-h, 220px);
}

.sc-bars {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: var(--space-xs);
}

.sc-bar-row {
	display: grid;
	grid-template-columns: minmax(80px, 28%) 1fr auto;
	align-items: center;
	gap: var(--space-sm);
	font-size: 13px;
}

.sc-bar-label {
	color: var(--color-main-text);
	font-weight: 500;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.sc-bar-track {
	height: 6px;
	background: var(--surface-quiet);
	border-radius: 999px;
	overflow: hidden;
	position: relative;
}

.sc-bar-fill {
	height: 100%;
	background: var(--color-primary, #0082c9);
	border-radius: 999px;
	transition: width 350ms var(--ease-out-quart);
}

.sc-bar-value {
	font-variant-numeric: tabular-nums;
	font-weight: 600;
	color: var(--color-main-text);
	min-width: 56px;
	text-align: right;
}

@media (max-width: 600px) {
	.sc-map {
		--sc-donut-h: 180px;
		padding: var(--space-sm) var(--space-md);
	}
	.sc-donut-placeholder { height: 180px; }
}

@media (max-width: 480px) {
	.sc-bar-row {
		grid-template-columns: 1fr auto;
		grid-template-rows: auto auto;
		row-gap: var(--space-2xs);
	}
	.sc-bar-track {
		grid-column: 1 / -1;
		grid-row: 2;
	}
	.sc-bar-label {
		max-width: 100%;
	}
}

@media (prefers-color-scheme: dark) {
	.sc-bar-track { background: rgba(255, 255, 255, 0.06); }
}
</style>
