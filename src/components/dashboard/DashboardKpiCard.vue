<template>
	<div
		:class="[
			'sc-kpi',
			{
				'sc-kpi-clickable': isClickable,
				'sc-kpi-has-spark': isClickable,
				'sc-kpi-feature': featured,
			},
		]"
		@click="onClick">
		<div class="sc-kpi-body">
			<div class="sc-kpi-head">
				<div class="sc-kpi-label">{{ formatMetricName(metricId) }}</div>
				<span v-if="trend"
					:class="['sc-kpi-trend', trend.dir, { 'sc-kpi-trend-in': trendsAnimated }]"
					:style="{ '--sc-trend-delay': trendDelay }">
					{{ trend.icon }}
					{{ trend.percent }}%
				</span>
			</div>
			<div class="sc-kpi-value">{{ formatValue(value, metricId) }}</div>
		</div>
		<div v-if="isClickable" class="sc-kpi-spark" :style="sparkStyle">
			<svg
				:viewBox="sparkViewBox"
				preserveAspectRatio="none"
				class="sc-kpi-spark-svg"
				aria-hidden="true">
				<defs>
					<linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
						<stop offset="0%" :stop-color="sparkColor" stop-opacity="0.45" />
						<stop offset="100%" :stop-color="sparkColor" stop-opacity="0" />
					</linearGradient>
				</defs>
				<path v-if="sparkAreaPath" :d="sparkAreaPath" :fill="'url(#' + gradientId + ')'" stroke="none" />
				<path v-if="sparkLinePath" :d="sparkLinePath" fill="none" :stroke="sparkColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
			</svg>
		</div>
	</div>
</template>

<script>
import { formatMetricName, formatValue } from '../../utils/dashboardFormat.js'

// Hand-rolled SVG sparkline. Replaces a per-card ApexCharts area chart that was
// the dominant cost on first paint when ~20 KPI cards mounted simultaneously.
// Uses a fixed virtual viewBox (100x40) with preserveAspectRatio=none so the
// stroke + fill stretch to the container; vector-effect=non-scaling-stroke
// keeps the line crisp at any rendered size.

const VBOX_W = 100
const VBOX_H = 40

let sparkUid = 0

export default {
	name: 'DashboardKpiCard',
	props: {
		collectorId: { type: String, required: true },
		metricId: { type: String, required: true },
		value: { default: null },
		featured: { type: Boolean, default: false },
		seriesData: { type: Array, default: () => [] },
		trend: { type: [Object, null], default: null },
		isClickable: { type: Boolean, default: false },
		trendsAnimated: { type: Boolean, default: false },
		index: { type: Number, default: 0 },
	},
	emits: ['click'],
	data() {
		return {
			gradientId: 'sc-spark-grad-' + (++sparkUid),
		}
	},
	computed: {
		trendDelay() {
			return (this.index * 50) + 'ms'
		},
		sparkColor() {
			if (this.trend?.dir === 'down') return '#e9322d'
			if (this.trend?.dir === 'up') return '#46ba61'
			return '#0082c9'
		},
		sparkStyle() {
			return {
				height: this.featured
					? 'var(--sc-spark-feature-h, 72px)'
					: 'var(--sc-spark-h, 56px)',
			}
		},
		sparkViewBox() {
			return '0 0 ' + VBOX_W + ' ' + VBOX_H
		},
		sparkPoints() {
			const data = Array.isArray(this.seriesData) ? this.seriesData : []
			if (data.length < 2) return []
			let min = Infinity
			let max = -Infinity
			for (const v of data) {
				if (typeof v !== 'number' || !isFinite(v)) continue
				if (v < min) min = v
				if (v > max) max = v
			}
			if (!isFinite(min) || !isFinite(max)) return []
			const range = max - min || 1
			const stepX = data.length > 1 ? VBOX_W / (data.length - 1) : 0
			// Inset by 1 unit top/bottom so the stroke isn't clipped.
			const padTop = 1
			const padBot = 1
			const usableH = VBOX_H - padTop - padBot
			return data.map((v, i) => {
				const x = i * stepX
				const norm = (v - min) / range
				const y = VBOX_H - padBot - (norm * usableH)
				return [x, y]
			})
		},
		sparkLinePath() {
			const pts = this.sparkPoints
			if (pts.length < 2) return ''
			let d = 'M' + this.fmt(pts[0][0]) + ' ' + this.fmt(pts[0][1])
			for (let i = 1; i < pts.length; i++) {
				d += ' L' + this.fmt(pts[i][0]) + ' ' + this.fmt(pts[i][1])
			}
			return d
		},
		sparkAreaPath() {
			const pts = this.sparkPoints
			if (pts.length < 2) return ''
			let d = 'M' + this.fmt(pts[0][0]) + ' ' + VBOX_H
			for (const [x, y] of pts) {
				d += ' L' + this.fmt(x) + ' ' + this.fmt(y)
			}
			d += ' L' + this.fmt(pts[pts.length - 1][0]) + ' ' + VBOX_H + ' Z'
			return d
		},
	},
	methods: {
		formatMetricName,
		formatValue,
		fmt(n) {
			// Two decimals is plenty for a 100-unit viewBox; trims path size.
			return Math.round(n * 100) / 100
		},
		onClick() {
			if (this.isClickable) this.$emit('click')
		},
	},
}
</script>

<style scoped>
.sc-kpi {
	position: relative;
	overflow: hidden;
	background: var(--surface-raised);
	border: 1px solid var(--rule);
	border-radius: var(--radius-md);
	min-width: 0;
	display: flex;
	flex-direction: column;
	transform: translate3d(0, 0, 0);
	transition:
		border-color 180ms var(--ease-out-quart),
		box-shadow 180ms var(--ease-out-quart);
}

.sc-kpi-body { padding: var(--space-md) var(--space-md) var(--space-sm); }

.sc-kpi:hover {
	border-color: color-mix(in srgb, var(--color-primary, #0082c9) 40%, var(--rule));
}

.sc-kpi-clickable { cursor: pointer; }

.sc-kpi-clickable:focus-visible {
	outline: none;
	border-color: var(--color-primary, #0082c9);
	box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary, #0082c9) 18%, transparent);
}

@media (prefers-reduced-motion: no-preference) {
	.sc-kpi-clickable {
		transition:
			border-color 180ms var(--ease-out-quart),
			box-shadow 180ms var(--ease-out-quart),
			transform 180ms var(--ease-out-quart);
	}
	.sc-kpi-clickable:hover {
		transform: translate3d(0, -2px, 0);
		box-shadow: 0 6px 18px -8px rgba(15, 22, 36, 0.18), 0 1px 2px rgba(15, 22, 36, 0.04);
	}
	.sc-kpi-clickable:active {
		transition:
			transform 120ms var(--ease-out-quart),
			border-color 120ms var(--ease-out-quart);
		transform: translate3d(0, 0, 0) scale(0.97);
	}
}

.sc-kpi-feature {
	grid-column: span 2;
	grid-row: span 2;
	background:
		linear-gradient(180deg,
			color-mix(in srgb, var(--color-primary, #0082c9) 5%, var(--surface-raised)),
			var(--surface-raised) 60%);
}

.sc-kpi-feature .sc-kpi-body {
	padding: var(--space-lg) var(--space-lg) var(--space-md);
}

.sc-kpi-feature .sc-kpi-value {
	font-size: var(--sc-kpi-feature-size, 36px);
}

@media (max-width: 480px) {
	.sc-kpi-feature {
		grid-column: span 1;
		grid-row: auto;
	}
	.sc-kpi-feature .sc-kpi-value {
		font-size: 26px;
	}
}

.sc-kpi-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: var(--space-xs);
	margin-bottom: var(--space-2xs);
}

.sc-kpi-label {
	font-size: var(--sc-kpi-label-size, 11px);
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.06em;
	/* Wrap onto at most two lines instead of truncating: metric names like
	   "Files created (24h)" or "Total messages (7d)" were cut to "Files
	   create..." / "Total messa...", which read as duplicates in the grid. */
	display: -webkit-box;
	-webkit-box-orient: vertical;
	-webkit-line-clamp: 2;
	line-clamp: 2;
	overflow: hidden;
	overflow-wrap: anywhere;
	flex: 1 1 auto;
	min-width: 0;
}

.sc-kpi-value {
	font-size: var(--sc-kpi-value-size, 24px);
	font-weight: 700;
	letter-spacing: -0.015em;
	color: var(--color-main-text);
	font-variant-numeric: tabular-nums;
	line-height: 1.2;
	overflow-wrap: anywhere;
}

.sc-kpi-trend {
	font-size: 11px;
	font-weight: 600;
	padding: 2px 8px;
	border-radius: 999px;
	white-space: nowrap;
	flex-shrink: 0;
	font-variant-numeric: tabular-nums;
}

.sc-kpi-trend.up { color: #1f8a3a; background: color-mix(in srgb, #46ba61 18%, transparent); }
.sc-kpi-trend.down { color: #c0211d; background: color-mix(in srgb, #e9322d 16%, transparent); }

@media (prefers-reduced-motion: no-preference) {
	.sc-kpi-trend {
		opacity: 0;
		transform: scale(0.85);
		transition:
			opacity 280ms var(--ease-out-quart),
			transform 280ms var(--ease-out-quart);
		transition-delay: var(--sc-trend-delay, 0ms);
	}
	.sc-kpi-trend.sc-kpi-trend-in {
		opacity: 1;
		transform: scale(1);
	}
}

.sc-kpi-spark {
	margin-top: auto;
	pointer-events: none;
	line-height: 0;
	opacity: 0.6;
	transition: opacity 180ms var(--ease-out-quart);
	width: 100%;
}

.sc-kpi-spark-svg {
	display: block;
	width: 100%;
	height: 100%;
}

.sc-kpi-has-spark .sc-kpi-body { padding-bottom: var(--space-2xs); }
.sc-kpi-clickable:hover .sc-kpi-spark { opacity: 1; }
</style>
