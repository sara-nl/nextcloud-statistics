<template>
	<div class="settings-section">
		<div class="section-header">
			<h2>General Settings</h2>
		</div>

		<p class="description">
			Configure how often statistics are collected and how long they are kept.
		</p>

		<div class="form-group">
			<label for="instance-label">Instance Label</label>
			<input id="instance-label"
				v-model="settings.instance_label"
				type="text"
				placeholder="Production Cloud">
			<span class="hint">Friendly name shown on dashboards. Defaults to the Nextcloud instance ID.</span>
		</div>

		<div class="form-group">
			<label for="cron-interval">Collection Interval</label>
			<select id="cron-interval" v-model="settings.cron_interval">
				<option value="5min">Every 5 minutes</option>
				<option value="15min">Every 15 minutes</option>
				<option value="hourly">Every hour</option>
				<option value="daily">Daily</option>
				<option value="weekly">Weekly</option>
			</select>
			<span class="hint">How often the background job collects statistics. The dashboard reads the cached snapshot from the latest run.</span>
		</div>

		<div class="form-group">
			<label for="retention">Snapshot Retention</label>
			<select id="retention" v-model="settings.snapshot_retention_days">
				<option value="30">30 days</option>
				<option value="60">60 days</option>
				<option value="90">90 days</option>
				<option value="180">180 days</option>
				<option value="365">1 year</option>
				<option value="0">Keep forever</option>
			</select>
			<span class="hint">How long historical snapshots are kept. Old snapshots are pruned automatically.</span>
		</div>

		<div class="button-row">
			<button class="btn btn-primary" :disabled="saving" @click="save">
				{{ saving ? 'Saving...' : 'Save Settings' }}
			</button>
		</div>

		<div v-if="message" :class="['save-message', error ? 'error' : 'success']">
			{{ message }}
		</div>
	</div>
</template>

<script>
import api from '../services/api.js'

export default {
	name: 'GeneralSettings',
	data() {
		return {
			settings: {
				instance_label: '',
				cron_interval: 'hourly',
				snapshot_retention_days: '90',
			},
			saving: false,
			message: '',
			error: false,
		}
	},
	async mounted() {
		await this.load()
	},
	methods: {
		async load() {
			try {
				const { data } = await api.getSettings()
				this.settings.instance_label = data.instance_label || ''
				this.settings.cron_interval = data.cron_interval || 'hourly'
				this.settings.snapshot_retention_days = String(data.snapshot_retention_days || '90')
			} catch (e) {
				// ignore
			}
		},
		async save() {
			this.saving = true
			this.message = ''
			this.error = false
			try {
				await api.updateSettings({ ...this.settings })
				this.message = 'Settings saved'
				setTimeout(() => { this.message = '' }, 3000)
			} catch (e) {
				this.message = 'Failed to save settings'
				this.error = true
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
}

.form-group {
	margin-bottom: 20px;
}

.form-group label {
	display: block;
	font-weight: 600;
	font-size: 14px;
	margin-bottom: 6px;
}

.form-group input,
.form-group select {
	width: 100%;
	max-width: 400px;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	font-size: 14px;
}

.hint {
	display: block;
	margin-top: 4px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.button-row {
	margin-top: 8px;
}

.save-message {
	margin-top: 10px;
	font-size: 13px;
}

.save-message.success { color: var(--color-success, #46ba61); }
.save-message.error { color: var(--color-error, #e9322d); }
</style>
