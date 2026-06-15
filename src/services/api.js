import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = '/apps/stats_collector'

export default {
	// Settings
	getSettings() {
		return axios.get(generateUrl(`${base}/api/settings`))
	},

	updateSettings(data) {
		return axios.put(generateUrl(`${base}/api/settings`), data)
	},

	getLogoProxy() {
		return axios.get(generateUrl(`${base}/api/settings/logo`), { responseType: 'arraybuffer' })
	},

	searchGroups(search) {
		return axios.get(generateUrl(`${base}/api/settings/groups`), { params: { search } })
	},

	// Collectors
	getCollectors() {
		return axios.get(generateUrl(`${base}/api/collectors`))
	},

	updateMetrics(collectorId, metrics) {
		return axios.put(generateUrl(`${base}/api/collectors/${collectorId}/metrics`), { metrics })
	},

	// Admin dashboard
	getDashboard() {
		return axios.get(generateUrl(`${base}/api/stats/dashboard`))
	},

	collectNow() {
		return axios.post(generateUrl(`${base}/api/stats/collect`))
	},

	getSnapshots(params) {
		return axios.get(generateUrl(`${base}/api/stats/snapshots`), { params })
	},

	// API Keys
	getApiKeys() {
		return axios.get(generateUrl(`${base}/api/keys`))
	},

	createApiKey(label) {
		return axios.post(generateUrl(`${base}/api/keys`), { label })
	},

	revokeApiKey(id) {
		return axios.delete(generateUrl(`${base}/api/keys/${id}`))
	},

	// Personal dashboard preferences
	getPersonalPreferences() {
		return axios.get(generateUrl(`${base}/api/personal/preferences`))
	},

	updatePersonalPreferences(prefs) {
		return axios.put(generateUrl(`${base}/api/personal/preferences`), prefs)
	},
}
