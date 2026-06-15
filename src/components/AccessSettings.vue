<template>
	<div class="settings-section">
		<div class="section-header">
			<h2>Personal Dashboard Access</h2>
		</div>

		<p class="description">
			Configure which user groups can see the personal Stats Collector dashboard at <code>/apps/stats_collector/</code>.
			Admins always have access. Group members see the same metrics that are enabled in the <strong>Collectors</strong> tab.
		</p>

		<div class="form-group">
			<label>Allowed Groups</label>
			<div class="group-search">
				<input v-model="searchQuery"
					type="text"
					placeholder="Search groups..."
					@input="onSearchInput">
				<div v-if="searchResults.length > 0 && searchQuery" class="search-results">
					<button v-for="g in searchResults"
						:key="g.id"
						class="search-result"
						:disabled="allowedGroups.includes(g.id)"
						@click="addGroup(g.id)">
						{{ g.displayName }}
						<span v-if="allowedGroups.includes(g.id)" class="hint-already">added</span>
					</button>
				</div>
			</div>

			<div v-if="allowedGroups.length > 0" class="selected-groups">
				<span v-for="gid in allowedGroups" :key="gid" class="group-chip">
					{{ gid }}
					<button class="chip-remove" @click="removeGroup(gid)">×</button>
				</span>
			</div>
			<div v-else class="hint">No groups selected — only admins can access the personal dashboard.</div>
		</div>

		<div class="button-row">
			<button class="btn btn-primary" :disabled="saving" @click="save">
				{{ saving ? 'Saving...' : 'Save Access Settings' }}
			</button>
		</div>

		<div v-if="saveMessage" :class="['save-message', saveError ? 'error' : 'success']">
			{{ saveMessage }}
		</div>
	</div>
</template>

<script>
import api from '../services/api.js'

export default {
	name: 'AccessSettings',
	data() {
		return {
			allowedGroups: [],
			searchQuery: '',
			searchResults: [],
			searchTimer: null,
			saving: false,
			saveMessage: '',
			saveError: false,
		}
	},
	async mounted() {
		await this.loadSettings()
	},
	methods: {
		async loadSettings() {
			try {
				const { data } = await api.getSettings()
				this.allowedGroups = Array.isArray(data.allowed_groups) ? data.allowed_groups : []
			} catch (e) {
				// ignore
			}
		},

		onSearchInput() {
			clearTimeout(this.searchTimer)
			this.searchTimer = setTimeout(() => this.searchGroups(), 250)
		},

		async searchGroups() {
			if (!this.searchQuery.trim()) {
				this.searchResults = []
				return
			}
			try {
				const { data } = await api.searchGroups(this.searchQuery)
				this.searchResults = data || []
			} catch (e) {
				this.searchResults = []
			}
		},

		addGroup(gid) {
			if (!this.allowedGroups.includes(gid)) {
				this.allowedGroups.push(gid)
			}
			this.searchQuery = ''
			this.searchResults = []
		},

		removeGroup(gid) {
			this.allowedGroups = this.allowedGroups.filter(g => g !== gid)
		},

		async save() {
			this.saving = true
			this.saveMessage = ''
			this.saveError = false
			try {
				await api.updateSettings({
					allowed_groups: this.allowedGroups,
				})
				this.saveMessage = 'Saved'
				setTimeout(() => { this.saveMessage = '' }, 3000)
			} catch (e) {
				this.saveMessage = 'Failed to save'
				this.saveError = true
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.description {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	margin-bottom: 20px;
	line-height: 1.5;
}

.description code {
	font-size: 12px;
	padding: 1px 6px;
	background: var(--color-background-dark);
	border-radius: 4px;
}

.description strong {
	color: var(--color-main-text);
	font-weight: 600;
}

.form-group {
	margin-bottom: 24px;
}

.form-group > label {
	display: block;
	font-weight: 600;
	font-size: 14px;
	margin-bottom: 6px;
}

.hint {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	margin-top: 8px;
}

.group-search {
	position: relative;
}

.group-search input {
	width: 100%;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	font-size: 14px;
}

.search-results {
	position: absolute;
	top: 100%;
	left: 0;
	right: 0;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	box-shadow: 0 4px 12px rgba(0,0,0,0.08);
	max-height: 240px;
	overflow-y: auto;
	z-index: 10;
}

.search-result {
	display: flex;
	justify-content: space-between;
	align-items: center;
	width: 100%;
	padding: 8px 12px;
	border: none;
	background: none;
	cursor: pointer;
	font-size: 13px;
	text-align: left;
}

.search-result:hover { background: var(--color-background-hover); }
.search-result:disabled { color: var(--color-text-maxcontrast); cursor: not-allowed; }

.hint-already { font-size: 11px; color: var(--color-text-maxcontrast); }

.selected-groups {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin-top: 12px;
}

.group-chip {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 4px 6px 4px 12px;
	background: var(--color-primary);
	color: var(--color-primary-text);
	border-radius: 999px;
	font-size: 13px;
}

.chip-remove {
	border: none;
	background: rgba(255,255,255,0.2);
	color: white;
	width: 18px;
	height: 18px;
	border-radius: 50%;
	cursor: pointer;
	font-size: 14px;
	line-height: 1;
	padding: 0;
}

.chip-remove:hover { background: rgba(255,255,255,0.4); }

.button-row {
	margin-top: 16px;
}

.save-message {
	margin-top: 10px;
	font-size: 13px;
}

.save-message.success { color: var(--color-success, #46ba61); }
.save-message.error { color: var(--color-error, #e9322d); }
</style>
