<template>
	<div :class="['collector-card', { unavailable: !collector.available }]">
		<div class="collector-header" @click="toggleExpanded">
			<div class="collector-info">
				<span class="collector-icon">{{ iconEmoji }}</span>
				<div>
					<strong>{{ collector.name }}</strong>
					<p class="collector-description">{{ collector.description }}</p>
				</div>
			</div>
			<div class="collector-meta">
				<span class="badge">{{ collector.app_id }}</span>
				<span v-if="!collector.available" class="badge unavailable-badge">
					Not installed
				</span>
				<span v-else class="badge metric-count">
					{{ enabledCount }}/{{ collector.metrics.length }}
				</span>
				<span class="expand-icon">{{ expanded ? '▾' : '▸' }}</span>
			</div>
		</div>

		<div v-if="expanded && collector.available" class="metrics-list">
			<div v-for="metric in collector.metrics" :key="metric.id" class="metric-item-wrapper">
				<div class="metric-item">
					<label class="metric-checkbox">
						<input type="checkbox"
							:checked="isEnabled(metric.id)"
							@change="toggleMetric(metric.id, $event.target.checked)">
						<span>{{ metric.name }}</span>
					</label>
					<span class="badge method-badge">{{ metric.method }}</span>
				</div>
				<p class="metric-description">{{ metric.description }}</p>
			</div>
		</div>

		<div v-if="expanded && !collector.available" class="metrics-list">
			<p class="unavailable-message">
				Install the <strong>{{ collector.app_id }}</strong> app to enable this collector.
			</p>
		</div>
	</div>
</template>

<script>
const ICON_MAP = {
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
}

export default {
	name: 'CollectorCard',
	props: {
		collector: {
			type: Object,
			required: true,
		},
	},
	emits: ['update-metrics'],
	data() {
		return {
			expanded: false,
		}
	},
	computed: {
		iconEmoji() {
			return ICON_MAP[this.collector.id] || '📦'
		},
		enabledCount() {
			return (this.collector.enabled_metrics || []).length
		},
	},
	methods: {
		toggleExpanded() {
			this.expanded = !this.expanded
		},

		isEnabled(metricId) {
			return (this.collector.enabled_metrics || []).includes(metricId)
		},

		toggleMetric(metricId, enabled) {
			const current = [...(this.collector.enabled_metrics || [])]
			let updated

			if (enabled) {
				updated = [...current, metricId]
			} else {
				updated = current.filter(id => id !== metricId)
			}

			this.$emit('update-metrics', this.collector.id, updated)
		},
	},
}
</script>

<style scoped>
.collector-description {
	margin: 2px 0 0;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.collector-icon {
	font-size: 24px;
}

.expand-icon {
	font-size: 14px;
	color: var(--color-text-maxcontrast);
	margin-left: 4px;
}

.unavailable-badge {
	background: var(--color-warning) !important;
	color: white !important;
}

.metric-count {
	font-variant-numeric: tabular-nums;
}

.method-badge {
	font-size: 10px;
	text-transform: uppercase;
}

.metric-checkbox {
	display: flex;
	align-items: center;
	gap: 8px;
	cursor: pointer;
}

.metric-checkbox input[type="checkbox"] {
	width: 18px;
	height: 18px;
	cursor: pointer;
	accent-color: var(--color-primary);
}

.metric-item-wrapper {
	margin-bottom: 4px;
}

.unavailable-message {
	color: var(--color-text-maxcontrast);
	font-style: italic;
}
</style>
