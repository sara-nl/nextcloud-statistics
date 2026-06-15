<template>
	<div class="stats-collector-settings">
		<div class="tabs">
			<button v-for="tab in tabs"
				:key="tab.id"
				:class="['tab-button', { active: activeTab === tab.id }]"
				@click="activeTab = tab.id">
				{{ tab.label }}
			</button>
		</div>

		<Dashboard v-if="activeTab === 'dashboard'" />
		<CollectorList v-if="activeTab === 'collectors'" />
		<ApiKeys v-if="activeTab === 'keys'" />
		<AccessSettings v-if="activeTab === 'access'" />
		<GeneralSettings v-if="activeTab === 'settings'" />
		<ChartBranding v-if="activeTab === 'branding'" />
	</div>
</template>

<script>
import Dashboard from './components/Dashboard.vue'
import CollectorList from './components/CollectorList.vue'
import ApiKeys from './components/ApiKeys.vue'
import AccessSettings from './components/AccessSettings.vue'
import GeneralSettings from './components/GeneralSettings.vue'
import ChartBranding from './components/ChartBranding.vue'

export default {
	name: 'App',
	components: {
		Dashboard,
		CollectorList,
		ApiKeys,
		AccessSettings,
		GeneralSettings,
		ChartBranding,
	},
	data() {
		return {
			activeTab: 'dashboard',
			tabs: [
				{ id: 'dashboard', label: 'Dashboard' },
				{ id: 'collectors', label: 'Collectors' },
				{ id: 'keys', label: 'API Keys' },
				{ id: 'access', label: 'Access' },
				{ id: 'settings', label: 'Settings' },
				{ id: 'branding', label: 'Branding' },
			],
		}
	},
}
</script>

<style scoped>
.tabs {
	display: flex;
	gap: 0;
	margin-bottom: 24px;
	border-bottom: 2px solid var(--color-border);
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
	scrollbar-width: none;
}

.tabs::-webkit-scrollbar { display: none; }

.tab-button {
	padding: 10px 14px;
	border: none;
	background: none;
	cursor: pointer;
	font-size: 13px;
	font-weight: 500;
	color: var(--color-text-maxcontrast);
	border-bottom: 2px solid transparent;
	margin-bottom: -2px;
	transition: color 0.2s, border-color 0.2s;
	white-space: nowrap;
	flex-shrink: 0;
}

.tab-button:hover {
	color: var(--color-main-text);
}

.tab-button.active {
	color: var(--color-primary);
	border-bottom-color: var(--color-primary);
}
</style>
