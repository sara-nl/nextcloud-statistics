<template>
	<section class="sc-section">
		<header class="sc-section-head">
			<div class="sc-section-title">
				<span class="sc-section-icon" v-html="getIconSvg(collectorId)" />
				<h2>{{ formatCollectorName(collectorId) }}</h2>
			</div>
			<div class="sc-section-summary">
				<span v-if="primaryStat" class="sc-section-primary">
					<span class="sc-section-primary-value">{{ primaryStat.value }}</span>
					<span class="sc-section-primary-label">{{ primaryStat.label }}</span>
				</span>
				<span class="sc-section-count">
					{{ metricCount }}
					{{ metricCount === 1 ? 'metric' : 'metrics' }}
				</span>
			</div>
		</header>

		<div v-if="scalarEntries.length" class="sc-kpi-grid">
			<DashboardKpiCard
				v-for="([metricId, value], idx) in scalarEntries"
				:key="metricId"
				:collector-id="collectorId"
				:metric-id="metricId"
				:value="value"
				:featured="idx === 0 && hasSeriesData(collectorId, metricId)"
				:is-clickable="hasSeriesData(collectorId, metricId)"
				:series-data="seriesFor(collectorId, metricId)"
				:trend="trendFor(collectorId, metricId)"
				:trends-animated="trendsAnimated"
				:index="idx"
				@click="$emit('select-metric', collectorId + '.' + metricId)" />
		</div>

		<div v-if="mapEntries.length" class="sc-maps">
			<DashboardMap
				v-for="[metricId, value] in mapEntries"
				:key="metricId"
				:metric-id="metricId"
				:value="value" />
		</div>
	</section>
</template>

<script>
import { isObject, formatCollectorName, formatMetricName, formatValue } from '../../utils/dashboardFormat.js'
import { getIconSvg } from '../../utils/dashboardIcons.js'
import { SECTION_PRIMARY } from '../../utils/dashboardConstants.js'
import DashboardKpiCard from './DashboardKpiCard.vue'
import DashboardMap from './DashboardMap.vue'

export default {
	name: 'DashboardSection',
	components: { DashboardKpiCard, DashboardMap },
	props: {
		collectorId: { type: String, required: true },
		data: { type: Object, required: true },
		seriesFor: { type: Function, required: true },
		hasSeriesData: { type: Function, required: true },
		trendFor: { type: Function, required: true },
		trendsAnimated: { type: Boolean, default: false },
	},
	emits: ['select-metric'],
	computed: {
		metricCount() {
			return Object.keys(this.data).length
		},
		primaryStat() {
			const primaryId = SECTION_PRIMARY[this.collectorId]
			if (!primaryId) return null
			const v = this.data[primaryId]
			if (v === undefined || v === null || isObject(v) || Array.isArray(v)) return null
			return {
				metricId: primaryId,
				label: formatMetricName(primaryId),
				value: formatValue(v, primaryId),
			}
		},
		scalarEntries() {
			const primaryId = SECTION_PRIMARY[this.collectorId]
			return Object.entries(this.data)
				.filter(([, v]) => !isObject(v))
				.filter(([metricId]) => metricId !== primaryId)
		},
		mapEntries() {
			return Object.entries(this.data).filter(([, v]) => isObject(v))
		},
	},
	methods: {
		getIconSvg,
		formatCollectorName,
	},
}
</script>

<style scoped>
.sc-section {
	display: flex;
	flex-direction: column;
	gap: var(--space-md);
}

.sc-section-head {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	gap: var(--space-md);
	flex-wrap: wrap;
}

.sc-section-title {
	display: flex;
	align-items: center;
	gap: var(--space-sm);
	min-width: 0;
}

.sc-section-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	border-radius: 6px;
	background: var(--surface-quiet);
	color: var(--color-text-maxcontrast);
	flex-shrink: 0;
}

.sc-section-icon :deep(svg) { width: 16px; height: 16px; }

.sc-section-title h2 {
	margin: 0;
	font-size: var(--sc-section-title-size, 14px);
	font-weight: 600;
	letter-spacing: 0.04em;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
}

.sc-section-summary {
	display: inline-flex;
	align-items: baseline;
	gap: var(--space-md);
	flex-wrap: wrap;
}

.sc-section-primary {
	display: inline-flex;
	align-items: baseline;
	gap: var(--space-xs);
}

.sc-section-primary-value {
	font-size: var(--sc-section-primary-size, 22px);
	font-weight: 700;
	letter-spacing: -0.015em;
	color: var(--color-main-text);
	font-variant-numeric: tabular-nums;
}

.sc-section-primary-label {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	font-weight: 500;
}

.sc-section-count {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	font-weight: 500;
	font-variant-numeric: tabular-nums;
}

.sc-kpi-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(var(--sc-kpi-min, 180px), 1fr));
	gap: var(--space-sm);
	grid-auto-flow: row dense;
}

.sc-maps {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
	gap: var(--space-sm);
	margin-top: var(--space-sm);
}

@media (max-width: 600px) {
	.sc-section-head {
		flex-direction: column;
		align-items: flex-start;
		gap: var(--space-xs);
	}
	.sc-section-summary {
		width: 100%;
		justify-content: flex-start;
		gap: var(--space-sm);
	}
}

@media (max-width: 480px) {
	.sc-maps { grid-template-columns: 1fr; }
	.sc-kpi-grid {
		gap: var(--space-xs);
	}
}
</style>
