import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { reactive } from 'vue'
import { normalizePreferences, DEFAULT_PREFERENCES } from '../utils/dashboardConstants.js'

export { normalizePreferences }

const SAVE_DEBOUNCE_MS = 400
const SAVED_INDICATOR_MS = 1800
const ERROR_INDICATOR_MS = 3000

async function persistPreferences(preferences) {
	const { data } = await axios.put(
		generateUrl('/apps/stats_collector/api/personal/preferences'),
		preferences,
	)
	if (data && typeof data === 'object') return normalizePreferences(data)
	return preferences
}

// Factory used from Options-API components: stash the returned object in
// `data()` and access `.state.preferences`, `.state.saveStatus`, etc. via
// computed wrappers. The factory owns the debounce + indicator timers.
export function createPreferencesController(initial) {
	const state = reactive({
		preferences: normalizePreferences(initial),
		saveStatus: '',
	})
	let saveTimer = null
	let indicatorTimer = null

	function clearTimers() {
		if (saveTimer) { clearTimeout(saveTimer); saveTimer = null }
		if (indicatorTimer) { clearTimeout(indicatorTimer); indicatorTimer = null }
	}

	function flashSaved() {
		state.saveStatus = 'saved'
		indicatorTimer = setTimeout(() => {
			if (state.saveStatus === 'saved') state.saveStatus = ''
		}, SAVED_INDICATOR_MS)
	}

	function flashError() {
		state.saveStatus = 'error'
		indicatorTimer = setTimeout(() => {
			if (state.saveStatus === 'error') state.saveStatus = ''
		}, ERROR_INDICATOR_MS)
	}

	async function save() {
		try {
			state.preferences = await persistPreferences(state.preferences)
			flashSaved()
		} catch (e) {
			flashError()
		}
	}

	function update(next) {
		state.preferences = next
		state.saveStatus = 'saving'
		clearTimers()
		saveTimer = setTimeout(save, SAVE_DEBOUNCE_MS)
	}

	async function reset() {
		state.preferences = { ...DEFAULT_PREFERENCES, hidden_sections: [], section_order: [], hero_pinned: [] }
		try {
			await persistPreferences(state.preferences)
			flashSaved()
		} catch (e) {
			state.saveStatus = 'error'
		}
	}

	function dispose() {
		clearTimers()
	}

	return { state, update, save, reset, dispose }
}
