<template>
	<section :class="['sc-spotlight', { 'sc-spotlight-revealed': revealed }]">
		<div class="sc-spotlight-head">
			<div class="sc-spotlight-titles">
				<div class="sc-spotlight-eyebrow">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<polyline points="3 17 9 11 13 15 21 7" />
						<polyline points="14 7 21 7 21 14" />
					</svg>
					Trend
				</div>
				<h2>{{ headline }}</h2>
				<div class="sc-spotlight-sub">
					{{ subtitle }}
				</div>
			</div>
			<div class="sc-spotlight-controls sc-no-export">
				<select :value="metricKey" class="sc-spotlight-select" @change="onChange">
					<optgroup v-for="group in metricOptions" :key="group.collectorId" :label="formatCollectorName(group.collectorId)">
						<option v-for="opt in group.metrics" :key="opt.value" :value="opt.value">
							{{ opt.label }}
						</option>
					</optgroup>
				</select>
			</div>
		</div>
		<div :class="['sc-spotlight-chart', { 'sc-spotlight-chart-loading': historyLoading }]">
			<div v-if="historyLoading && !hasData" class="sc-spotlight-empty">
				<span class="sc-range-spinner" />
				Loading data
			</div>
			<div v-else-if="!historyLoading && !hasData" class="sc-spotlight-empty">
				No historical data for this metric in the selected range.
			</div>
			<div v-else-if="!chartReady" class="sc-spotlight-empty" aria-hidden="true">
				<span class="sc-range-spinner" />
			</div>
			<apexchart v-else
				:key="metricKey"
				type="area"
				:height="chartHeight"
				:options="options"
				:series="series" />
		</div>
	</section>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts'
import { formatCollectorName } from '../../utils/dashboardFormat.js'

export default {
	name: 'DashboardSpotlight',
	components: { apexchart: VueApexCharts },
	props: {
		metricKey: { type: String, default: '' },
		metricOptions: { type: Array, default: () => [] },
		series: { type: Array, default: () => [{ name: '', data: [] }] },
		options: { type: Object, default: () => ({}) },
		historyLoading: { type: Boolean, default: false },
		revealed: { type: Boolean, default: false },
		headline: { type: String, default: '' },
		subtitle: { type: String, default: '' },
	},
	emits: ['update:metric-key'],
	data() {
		return {
			// Defer first ApexCharts mount until after the rest of the dashboard
			// paints. ApexCharts is the single heaviest component on the page;
			// pushing its mount past the first frame makes the hero + KPI grid
			// appear noticeably sooner.
			chartReady: false,
			chartHeight: 380,
		}
	},
	computed: {
		hasData() {
			return Array.isArray(this.series)
				&& this.series[0]
				&& Array.isArray(this.series[0].data)
				&& this.series[0].data.length > 0
		},
	},
	mounted() {
		this.measureHeight()
		const schedule = window.requestIdleCallback
			|| ((cb) => window.setTimeout(cb, 0))
		schedule(() => {
			this.chartReady = true
		})
	},
	methods: {
		formatCollectorName,
		onChange(e) {
			this.$emit('update:metric-key', e.target.value)
		},
		measureHeight() {
			// Read --sc-spotlight-h off the cascade so density tweaks the chart
			// height without remounting Apex. Apex height accepts a number (px)
			// or a string; we resolve to a number for consistency.
			try {
				const cs = getComputedStyle(this.$el)
				const raw = cs.getPropertyValue('--sc-spotlight-h').trim()
				const n = parseInt(raw, 10)
				if (Number.isFinite(n) && n > 0) {
					this.chartHeight = n
				}
			} catch (e) { /* fall back to default */ }
		},
	},
}
</script>

<style scoped>
.sc-spotlight {
	position: relative;
	background: var(--surface-raised);
	border: 1px solid var(--rule);
	border-radius: var(--radius-lg);
	padding: var(--space-xl) clamp(var(--space-lg), 3vw, var(--space-2xl)) var(--space-lg);
	margin-bottom: var(--space-3xl);
	box-shadow: 0 1px 2px rgba(15, 22, 36, 0.04), 0 24px 48px -24px rgba(15, 22, 36, 0.10);
}

@media (prefers-reduced-motion: no-preference) {
	.sc-spotlight {
		opacity: 0;
		transform: translate3d(0, 8px, 0);
		transition:
			opacity 400ms var(--ease-out-expo),
			transform 400ms var(--ease-out-expo);
	}
	.sc-spotlight-revealed {
		opacity: 1;
		transform: translate3d(0, 0, 0);
	}
}

.sc-spotlight-head {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: var(--space-md);
	margin-bottom: var(--space-lg);
	flex-wrap: wrap;
}

.sc-spotlight-titles { min-width: 0; flex: 1 1 auto; }

.sc-spotlight-eyebrow {
	display: inline-flex;
	align-items: center;
	gap: var(--space-2xs);
	font-size: 11px;
	font-weight: 600;
	letter-spacing: 0.12em;
	text-transform: uppercase;
	color: var(--color-primary, #0082c9);
	margin-bottom: var(--space-xs);
}

.sc-spotlight-head h2 {
	margin: 0;
	font-size: var(--sc-section-headline-size, clamp(20px, 2.2vw, 26px));
	font-weight: 700;
	letter-spacing: -0.02em;
	color: var(--color-main-text);
	line-height: 1.15;
}

.sc-spotlight-sub {
	margin-top: var(--space-2xs);
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.sc-spotlight-controls {
	display: flex;
	align-items: center;
	gap: var(--space-xs);
	flex-shrink: 0;
}

.sc-spotlight-select {
	padding: 8px 32px 8px 14px;
	border: 1px solid var(--rule);
	border-radius: var(--radius-sm);
	font-size: 13px;
	font-weight: 500;
	background: var(--surface-raised)
		url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>")
		no-repeat right 12px center;
	color: var(--color-main-text);
	min-width: 240px;
	cursor: pointer;
	-webkit-appearance: none;
	appearance: none;
	font-family: inherit;
	transition:
		border-color 180ms var(--ease-out-quart),
		box-shadow 180ms var(--ease-out-quart);
}

.sc-spotlight-select:hover { border-color: var(--color-primary, #0082c9); }
.sc-spotlight-select:focus {
	outline: none;
	border-color: var(--color-primary, #0082c9);
	box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary, #0082c9) 18%, transparent);
}

.sc-spotlight-chart {
	min-height: var(--sc-spotlight-h, 380px);
	transition: opacity 200ms var(--ease-out-quart);
}

.sc-spotlight-chart-loading { opacity: 0.6; }

.sc-spotlight-empty {
	height: var(--sc-spotlight-empty-h, 320px);
	display: flex;
	align-items: center;
	justify-content: center;
	gap: var(--space-xs);
	color: var(--color-text-maxcontrast);
	font-size: 14px;
}

.sc-range-spinner {
	display: inline-block;
	width: 12px;
	height: 12px;
	border: 2px solid color-mix(in srgb, var(--color-primary, #0082c9) 30%, transparent);
	border-top-color: var(--color-primary, #0082c9);
	border-radius: 50%;
	animation: sc-spin 0.7s linear infinite;
}

@keyframes sc-spin {
	from { transform: rotate(0deg); }
	to { transform: rotate(360deg); }
}

@media (max-width: 720px) {
	.sc-spotlight-head {
		flex-direction: column;
		align-items: stretch;
	}
	.sc-spotlight-controls {
		width: 100%;
	}
	.sc-spotlight-select {
		width: 100%;
		min-width: 0;
		min-height: 40px;
	}
}

@media (max-width: 600px) {
	.sc-spotlight {
		padding: var(--space-lg) var(--space-md) var(--space-md);
		margin-bottom: var(--space-2xl);
	}
	.sc-spotlight-head h2 {
		font-size: clamp(18px, 5.6vw, 22px);
	}
}

/* Mobile-specific chart sizing. The min-height var also drives the rendered
   ApexCharts canvas via measureHeight() on first mount. */
@media (max-width: 600px) {
	.sc-spotlight {
		--sc-spotlight-h: 260px;
		--sc-spotlight-empty-h: 220px;
	}
}

@media (max-width: 480px) {
	.sc-spotlight {
		--sc-spotlight-h: 240px;
		--sc-spotlight-empty-h: 200px;
	}
}
</style>
