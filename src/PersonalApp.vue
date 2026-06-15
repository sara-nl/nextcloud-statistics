<template>
	<NcContent app-name="stats_collector">
		<NcAppContent>
			<AccessDeniedState v-if="!allowed" />
			<PersonalDashboard v-else />
		</NcAppContent>
	</NcContent>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { NcContent, NcAppContent } from '@nextcloud/vue'
import PersonalDashboard from './components/PersonalDashboard.vue'
import AccessDeniedState from './components/dashboard/AccessDeniedState.vue'

export default {
	name: 'PersonalApp',
	components: {
		NcContent,
		NcAppContent,
		PersonalDashboard,
		AccessDeniedState,
	},
	data() {
		return {
			allowed: loadState('stats_collector', 'allowed', false),
		}
	},
}
</script>
