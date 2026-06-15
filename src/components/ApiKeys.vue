<template>
	<div class="settings-section">
		<div class="section-header">
			<h2>API Keys</h2>
		</div>

		<p class="description">
			Generate API keys for remote instances. Each instance uses its key to send stats to this instance.
		</p>

		<div class="key-create">
			<input v-model="newKeyLabel"
				type="text"
				placeholder="Instance label (e.g. Production Cloud)"
				@keydown.enter="createKey">
			<button class="btn btn-primary" :disabled="!newKeyLabel.trim()" @click="createKey">
				Generate Key
			</button>
		</div>

		<!-- Newly created key (show full key once) -->
		<div v-if="newlyCreatedKey" class="new-key-banner">
			<div class="new-key-banner-header">
				<strong>API key created for "{{ newlyCreatedKey.label }}"</strong>
				<button class="btn-dismiss" @click="newlyCreatedKey = null">&times;</button>
			</div>
			<p class="new-key-warning">Copy this key now. It will not be shown again.</p>
			<div class="key-display">
				<code>{{ newlyCreatedKey.key }}</code>
				<button class="btn btn-sm" @click="copyKey(newlyCreatedKey.key)">{{ copied ? 'Copied!' : 'Copy' }}</button>
			</div>
			<p class="key-config-hint">
				On the remote instance: Endpoint tab &rarr; Stats Collector API &rarr; paste this key.<br>
				Or via CLI: <code class="key-config-code">occ stats_collector:configure --endpoint-type=stats_collector --sc-api-key=&lt;key&gt;</code>
			</p>
		</div>

		<div v-if="error" class="error-message">{{ error }}</div>

		<div v-if="apiKeys.length > 0" class="keys-list">
			<div v-for="key in apiKeys" :key="key.id" class="key-item">
				<div class="key-info">
					<strong class="key-label">{{ key.label }}</strong>
					<code class="key-preview">{{ key.key_preview }}</code>
				</div>
				<span class="key-date">{{ formatDate(key.created_at) }}</span>
				<button class="btn-icon" @click="revokeKey(key.id, key.label)" title="Revoke">&times;</button>
			</div>
		</div>
		<div v-else class="keys-empty">
			No API keys yet. Generate one to connect a remote instance.
		</div>
	</div>
</template>

<script>
import api from '../services/api.js'
import { formatDate, confirm } from '../services/utils.js'

export default {
	name: 'ApiKeys',
	data() {
		return {
			apiKeys: [],
			error: null,
			newKeyLabel: '',
			newlyCreatedKey: null,
			copied: false,
		}
	},
	async mounted() {
		await this.loadKeys()
	},
	methods: {
		async loadKeys() {
			try {
				const { data } = await api.getApiKeys()
				this.apiKeys = data
			} catch (e) {
				this.error = 'Failed to load API keys'
			}
		},

		async createKey() {
			if (!this.newKeyLabel.trim()) return
			try {
				const { data } = await api.createApiKey(this.newKeyLabel.trim())
				this.newlyCreatedKey = data
				this.newKeyLabel = ''
				await this.loadKeys()
			} catch (e) {
				this.error = 'Failed to create API key'
			}
		},

		async revokeKey(id, label) {
			if (!confirm(`Revoke API key for "${label}"? The remote instance will no longer be able to send stats.`)) return
			try {
				await api.revokeApiKey(id)
				this.apiKeys = this.apiKeys.filter(k => k.id !== id)
			} catch (e) {
				this.error = 'Failed to revoke API key'
			}
		},

		copyKey(key) {
			navigator.clipboard.writeText(key).then(() => {
				this.copied = true
				setTimeout(() => { this.copied = false }, 2000)
			}).catch(() => {
				const el = document.createElement('textarea')
				el.value = key
				document.body.appendChild(el)
				el.select()
				document.execCommand('copy')
				document.body.removeChild(el)
				this.copied = true
				setTimeout(() => { this.copied = false }, 2000)
			})
		},

		formatDate,
	},
}
</script>

<style scoped>
.description {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	margin-bottom: 16px;
}

.key-create {
	display: flex;
	gap: 8px;
	margin-bottom: 16px;
}

.key-create input {
	flex: 1;
	padding: 6px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	font-size: 14px;
}

.new-key-banner {
	padding: 16px 18px;
	background: #1a7431;
	color: white;
	border-radius: var(--border-radius-large);
	margin-bottom: 16px;
}

.new-key-banner-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 6px;
	font-size: 14px;
}

.btn-dismiss {
	border: none;
	background: none;
	color: rgba(255,255,255,0.7);
	font-size: 20px;
	cursor: pointer;
	padding: 0 4px;
	line-height: 1;
}

.btn-dismiss:hover { color: white; }

.new-key-warning {
	margin: 0 0 10px;
	font-size: 13px;
	color: rgba(255,255,255,0.85);
}

.key-display {
	display: flex;
	gap: 8px;
	align-items: center;
	margin-bottom: 10px;
}

.key-display code {
	flex: 1;
	padding: 10px 14px;
	background: rgba(0,0,0,0.25);
	border-radius: var(--border-radius);
	font-size: 13px;
	word-break: break-all;
	font-family: monospace;
	color: white;
	letter-spacing: 0.02em;
}

.key-display .btn-sm {
	padding: 6px 16px;
	background: white;
	color: #1a7431;
	border: none;
	border-radius: var(--border-radius);
	font-weight: 600;
	font-size: 13px;
	cursor: pointer;
}

.key-display .btn-sm:hover { background: #f0f0f0; }

.key-config-hint {
	font-size: 12px;
	color: rgba(255,255,255,0.75);
	margin: 0;
}

.key-config-code {
	background: rgba(0,0,0,0.2);
	padding: 2px 6px;
	border-radius: 4px;
	font-size: 11px;
}

.keys-list {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.key-item {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}

.key-info {
	display: flex;
	align-items: center;
	gap: 10px;
	flex: 1;
	overflow: hidden;
}

.key-label { font-size: 14px; }
.key-preview { font-size: 12px; color: var(--color-text-maxcontrast); font-family: monospace; background: var(--color-background-dark); padding: 2px 6px; border-radius: 4px; }
.key-date { font-size: 12px; color: var(--color-text-maxcontrast); white-space: nowrap; }

.keys-empty {
	text-align: center;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	padding: 12px;
}

.btn-icon {
	border: none;
	background: none;
	cursor: pointer;
	font-size: 20px;
	color: var(--color-text-maxcontrast);
	padding: 2px 6px;
	line-height: 1;
}

.btn-icon:hover { color: var(--color-error); }

.error-message {
	padding: 12px 16px;
	background: var(--color-error);
	color: white;
	border-radius: var(--border-radius-large);
	margin-bottom: 16px;
	font-size: 14px;
}
</style>
