<template>
	<div class="settings-section">
		<div class="section-header">
			<h2>Collectors</h2>
		</div>

		<div v-if="loading" class="loading">
			Loading collectors...
		</div>

		<div v-else class="collector-grid">
			<CollectorCard v-for="collector in collectors"
				:key="collector.id"
				:collector="collector"
				@update-metrics="onUpdateMetrics" />
		</div>
	</div>
</template>

<script>
import CollectorCard from './CollectorCard.vue'
import api from '../services/api.js'

export default {
	name: 'CollectorList',
	components: {
		CollectorCard,
	},
	data() {
		return {
			collectors: [],
			loading: true,
		}
	},
	async mounted() {
		await this.loadCollectors()
	},
	methods: {
		async loadCollectors() {
			this.loading = true
			try {
				const { data } = await api.getCollectors()
				this.collectors = data
			} catch (e) {
				console.error('Failed to load collectors', e)
			} finally {
				this.loading = false
			}
		},

		async onUpdateMetrics(collectorId, metrics) {
			try {
				await api.updateMetrics(collectorId, metrics)
				// Update local state
				const collector = this.collectors.find(c => c.id === collectorId)
				if (collector) {
					collector.enabled_metrics = metrics
				}
			} catch (e) {
				console.error('Failed to update metrics', e)
			}
		},
	},
}
</script>

<style scoped>
.loading {
	padding: 20px;
	text-align: center;
	color: var(--color-text-maxcontrast);
}
</style>
