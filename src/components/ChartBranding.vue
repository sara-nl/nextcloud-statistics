<template>
	<div class="settings-section">
		<div class="section-header">
			<h2>Chart Branding</h2>
		</div>

		<p class="description">
			Customize the look of all charts across the Instances viewer.
		</p>

		<!-- Logo -->
		<div class="form-group">
			<label>Logo URL</label>
			<input v-model="settings.chart_logo_url"
				type="url"
				placeholder="https://example.com/logo.png">
			<span class="hint">URL to your company logo. Shown as watermark on all charts.</span>
		</div>

		<div v-if="settings.chart_logo_url" class="logo-preview">
			<img :src="settings.chart_logo_url" alt="Logo preview" @error="logoError = true" @load="logoError = false">
			<span v-if="logoError" class="logo-error">Could not load image</span>
		</div>

		<!-- Font -->
		<div class="form-group">
			<label>Chart Font</label>
			<select v-model="settings.chart_font">
				<option value="">Default (inherit)</option>
				<option value="Inter, sans-serif">Inter</option>
				<option value="Roboto, sans-serif">Roboto</option>
				<option value="Open Sans, sans-serif">Open Sans</option>
				<option value="Lato, sans-serif">Lato</option>
				<option value="Nunito, sans-serif">Nunito</option>
				<option value="Source Sans Pro, sans-serif">Source Sans Pro</option>
				<option value="Poppins, sans-serif">Poppins</option>
				<option value="Montserrat, sans-serif">Montserrat</option>
				<option value="monospace">Monospace</option>
			</select>
			<span class="hint">Font used in all chart labels and legends. The font must be available on the viewer's system or loaded via CSS.</span>
		</div>

		<!-- Colors -->
		<div class="form-group">
			<label>Chart Color Palette</label>
			<div class="colors-list">
				<div v-for="(color, index) in colors" :key="index" class="color-item">
					<input type="color" :value="color" @input="updateColor(index, $event.target.value)">
					<input type="text" :value="color" class="hex-input"
						placeholder="#000000"
						@change="updateColor(index, $event.target.value)">
					<button class="btn-icon-sm" @click="removeColor(index)" title="Remove">&times;</button>
				</div>
				<button class="btn btn-tertiary btn-sm" @click="addColor">+ Add color</button>
			</div>
			<span class="hint">Colors used for chart series. Leave empty to use the default palette.</span>
		</div>

		<!-- Preview -->
		<div v-if="colors.length > 0" class="palette-preview">
			<span v-for="(color, i) in colors" :key="i"
				class="palette-swatch"
				:style="{ background: color }">
			</span>
		</div>

		<!-- Save -->
		<div class="button-row">
			<button class="btn btn-primary" :disabled="saving" @click="saveSettings">
				{{ saving ? 'Saving...' : 'Save Branding' }}
			</button>
			<button class="btn" @click="resetColors">Reset to defaults</button>
		</div>

		<div v-if="saveMessage" :class="['save-message', saveError ? 'error' : 'success']">
			{{ saveMessage }}
		</div>
	</div>
</template>

<script>
import api from '../services/api.js'
import { COLORS } from '../services/utils.js'

export default {
	name: 'ChartBranding',
	data() {
		return {
			settings: {
				chart_logo_url: '',
				chart_font: '',
			},
			colors: [],
			saving: false,
			saveMessage: '',
			saveError: false,
			logoError: false,
		}
	},
	async mounted() {
		await this.loadSettings()
	},
	methods: {
		async loadSettings() {
			try {
				const { data } = await api.getSettings()
				this.settings.chart_logo_url = data.chart_logo_url || ''
				this.settings.chart_font = data.chart_font || ''
				this.colors = (data.chart_colors && data.chart_colors.length > 0)
					? [...data.chart_colors]
					: []
			} catch (e) {
				console.error('Failed to load settings', e)
			}
		},

		updateColor(index, value) {
			this.colors[index] = value
		},

		addColor() {
			// Pick a default color that's not already used
			const defaults = COLORS
			const next = defaults.find(c => !this.colors.includes(c)) || '#666666'
			this.colors.push(next)
		},

		removeColor(index) {
			this.colors.splice(index, 1)
		},

		resetColors() {
			this.colors = []
		},

		async saveSettings() {
			this.saving = true
			this.saveMessage = ''
			this.saveError = false
			try {
				await api.updateSettings({
					chart_logo_url: this.settings.chart_logo_url,
					chart_font: this.settings.chart_font,
					chart_colors: this.colors.length > 0 ? this.colors : [],
				})
				this.saveMessage = 'Branding saved'
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
	font-size: 14px;
	margin-bottom: 16px;
}

.hint {
	display: block;
	margin-top: 4px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.logo-preview {
	margin: 8px 0 16px;
	padding: 16px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius-large);
	text-align: center;
}

.logo-preview img {
	max-width: 200px;
	max-height: 80px;
	object-fit: contain;
}

.logo-error {
	color: var(--color-error);
	font-size: 13px;
}

.colors-list {
	display: flex;
	flex-direction: column;
	gap: 6px;
	margin-top: 4px;
}

.color-item {
	display: flex;
	align-items: center;
	gap: 8px;
}

.color-item input[type="color"] {
	width: 36px;
	height: 36px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	cursor: pointer;
	padding: 2px;
}

.hex-input {
	width: 90px;
	padding: 4px 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	font-size: 13px;
	font-family: monospace;
	color: var(--color-main-text);
}

.btn-icon-sm {
	border: none;
	background: none;
	cursor: pointer;
	font-size: 18px;
	color: var(--color-text-maxcontrast);
	padding: 0 4px;
	line-height: 1;
}

.btn-icon-sm:hover { color: var(--color-error); }

.btn-sm { font-size: 13px !important; padding: 4px 12px !important; }

.palette-preview {
	display: flex;
	gap: 4px;
	margin: 12px 0;
}

.palette-swatch {
	width: 32px;
	height: 20px;
	border-radius: 4px;
	border: 1px solid rgba(0,0,0,0.1);
}

.button-row {
	display: flex;
	gap: 8px;
	margin-top: 16px;
}

.save-message {
	margin-top: 8px;
	font-size: 13px;
}

.save-message.success { color: var(--color-success); }
.save-message.error { color: var(--color-error); }
</style>
