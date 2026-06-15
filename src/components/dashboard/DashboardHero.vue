<template>
	<header class="sc-hero">
		<div class="sc-hero-top">
			<div class="sc-hero-left">
				<div class="sc-hero-eyebrow">
					<span class="sc-hero-eyebrow-dot" />
					Statistics dashboard
				</div>
				<h1 class="sc-hero-title">{{ instanceLabel || 'Nextcloud instance' }}</h1>
				<div v-if="!loading && !error && stats" class="sc-hero-meta">
					<span v-if="instanceUrl" class="sc-meta-pill" :title="instanceUrl">
						<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<circle cx="12" cy="12" r="10" />
							<path d="M2 12h20" />
							<path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
						</svg>
						<span class="sc-meta-pill-text">{{ shortUrl }}</span>
					</span>
					<span v-if="ncVersion" class="sc-meta-pill">
						<span class="sc-dot" />
						Nextcloud {{ ncVersion }}
					</span>
					<span v-if="timestamp" class="sc-meta-pill sc-meta-pill-muted">
						<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<circle cx="12" cy="12" r="9" />
							<path d="M12 7v5l3 2" />
						</svg>
						Updated {{ timeAgoLabel }}
					</span>
				</div>
			</div>

			<div v-if="!loading && !error && stats" class="sc-hero-actions sc-hero-actions-top sc-no-export">
				<button
					type="button"
					class="sc-customize-btn"
					:class="{ saving: saveStatus === 'saving', saved: saveStatus === 'saved', error: saveStatus === 'error' }"
					:title="saveStatusLabel"
					:aria-label="saveStatusLabel"
					@click="$emit('open-customize')">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<line x1="4" y1="21" x2="4" y2="14" />
						<line x1="4" y1="10" x2="4" y2="3" />
						<line x1="12" y1="21" x2="12" y2="12" />
						<line x1="12" y1="8" x2="12" y2="3" />
						<line x1="20" y1="21" x2="20" y2="16" />
						<line x1="20" y1="12" x2="20" y2="3" />
						<line x1="1" y1="14" x2="7" y2="14" />
						<line x1="9" y1="8" x2="15" y2="8" />
						<line x1="17" y1="16" x2="23" y2="16" />
					</svg>
					<span class="sc-customize-btn-label">Customize</span>
					<span v-if="saveStatus" :class="['sc-save-indicator', saveStatus]">
						<span v-if="saveStatus === 'saving'" class="sc-save-spinner" />
						<span v-else-if="saveStatus === 'saved'">Saved</span>
						<span v-else-if="saveStatus === 'error'">Couldn't save</span>
					</span>
				</button>

				<ExportMenu :open="exportMenuOpen" @update:open="$emit('update:export-menu-open', $event)" @export="$emit('export', $event)" />
			</div>
		</div>

		<div v-if="!loading && !error && stats" class="sc-hero-right">
			<div class="sc-hero-stats">
				<div v-for="(card, idx) in heroCards" :key="card.label"
					:class="['sc-hero-stat', { 'sc-hero-stat-primary': idx === 0 }]">
					<div class="sc-hero-stat-value">{{ card.value }}</div>
					<div class="sc-hero-stat-label">{{ card.label }}</div>
				</div>
			</div>
		</div>
	</header>
</template>

<script>
import { timeAgo } from '../../services/utils.js'
import ExportMenu from './ExportMenu.vue'

export default {
	name: 'DashboardHero',
	components: { ExportMenu },
	props: {
		instanceLabel: { type: String, default: '' },
		instanceUrl: { type: String, default: '' },
		shortUrl: { type: String, default: '' },
		ncVersion: { type: String, default: '' },
		timestamp: { type: String, default: '' },
		heroCards: { type: Array, default: () => [] },
		loading: { type: Boolean, default: false },
		error: { type: [String, null], default: null },
		stats: { type: [Object, null], default: null },
		saveStatus: { type: String, default: '' },
		saveStatusLabel: { type: String, default: '' },
		exportMenuOpen: { type: Boolean, default: false },
	},
	emits: ['open-customize', 'update:export-menu-open', 'export'],
	computed: {
		timeAgoLabel() {
			return timeAgo(this.timestamp)
		},
	},
}
</script>

<style scoped>
.sc-hero {
	padding: var(--sc-hero-pad, var(--space-xl));
	margin-bottom: var(--space-xl);
	background: var(--surface-raised);
	border: 1px solid var(--rule);
	border-radius: var(--radius-lg);
	display: flex;
	flex-direction: column;
	gap: var(--space-lg);
}

.sc-hero-top {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: var(--space-md);
	min-width: 0;
}

.sc-hero-left {
	min-width: 0;
	flex: 1 1 auto;
	display: flex;
	flex-direction: column;
	gap: var(--space-sm);
}

.sc-hero-actions-top {
	flex-shrink: 0;
	display: inline-flex;
	align-items: center;
	gap: var(--space-xs);
	margin-top: 0;
}

.sc-hero-eyebrow {
	display: inline-flex;
	align-items: center;
	gap: var(--space-xs);
	font-size: 11px;
	font-weight: 600;
	letter-spacing: 0.12em;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
}

.sc-hero-eyebrow-dot {
	width: 6px;
	height: 6px;
	border-radius: 50%;
	background: var(--color-primary, #0082c9);
}

.sc-hero-title {
	font-size: var(--sc-hero-title-size, clamp(24px, 2.4vw, 32px));
	line-height: 1.15;
	font-weight: 700;
	letter-spacing: -0.02em;
	margin: 0;
	color: var(--color-main-text);
	word-break: break-word;
}

.sc-hero-meta {
	display: flex;
	flex-wrap: wrap;
	gap: var(--space-xs);
}

.sc-meta-pill {
	display: inline-flex;
	align-items: center;
	gap: var(--space-2xs);
	padding: 5px 11px;
	border: 1px solid var(--rule);
	border-radius: 999px;
	background: var(--surface-raised);
	font-size: 12px;
	font-weight: 500;
	color: var(--color-main-text);
	white-space: nowrap;
	max-width: 100%;
	min-width: 0;
}

.sc-meta-pill-text {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	max-width: 60vw;
}

.sc-meta-pill svg { color: var(--color-text-maxcontrast); flex-shrink: 0; }

.sc-meta-pill-muted {
	background: transparent;
	border-color: transparent;
	color: var(--color-text-maxcontrast);
}

.sc-dot {
	display: inline-block;
	width: 6px;
	height: 6px;
	border-radius: 50%;
	background: #2ecc71;
}

.sc-hero-right {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: flex-start;
	gap: var(--space-lg);
	padding-top: var(--space-md);
	border-top: 1px solid var(--rule);
	min-width: 0;
}

.sc-hero-stats {
	display: flex;
	flex-wrap: wrap;
	gap: var(--space-lg);
	align-items: stretch;
}

.sc-hero-stat {
	min-width: 96px;
	padding-left: var(--space-lg);
	border-left: 1px solid var(--rule);
}

.sc-hero-stat:first-child {
	padding-left: 0;
	border-left: none;
}

.sc-hero-stat-value {
	font-size: var(--sc-hero-stat-size, 26px);
	font-weight: 700;
	letter-spacing: -0.015em;
	color: var(--color-main-text);
	font-variant-numeric: tabular-nums;
	line-height: 1.05;
}

.sc-hero-stat-primary .sc-hero-stat-value {
	font-size: var(--sc-hero-stat-primary-size, 34px);
	color: var(--color-primary, #0082c9);
}

.sc-hero-stat-label {
	margin-top: var(--space-2xs);
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.06em;
	font-weight: 600;
}

.sc-hero-actions {
	margin-top: var(--space-xs);
}

.sc-customize-btn {
	display: inline-flex;
	align-items: center;
	gap: var(--space-xs);
	padding: 8px 14px;
	margin-right: var(--space-xs);
	font-size: 13px;
	font-weight: 600;
	color: var(--color-main-text);
	background: var(--surface-raised);
	border: 1px solid var(--rule);
	border-radius: var(--radius-sm);
	cursor: pointer;
	font-family: inherit;
	transition:
		border-color 180ms var(--ease-out-quart),
		box-shadow 180ms var(--ease-out-quart),
		background 180ms var(--ease-out-quart);
}

.sc-customize-btn:hover {
	border-color: color-mix(in srgb, var(--color-primary, #0082c9) 50%, var(--rule));
	background: color-mix(in srgb, var(--color-primary, #0082c9) 4%, var(--surface-raised));
}

.sc-customize-btn:focus-visible {
	outline: none;
	border-color: var(--color-primary, #0082c9);
	box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary, #0082c9) 18%, transparent);
}

.sc-save-indicator {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	font-size: 11px;
	font-weight: 500;
	letter-spacing: 0.02em;
	padding-left: 6px;
	margin-left: 2px;
	border-left: 1px solid var(--rule);
}

.sc-save-indicator.saved { color: #1f8a3a; }
.sc-save-indicator.error { color: #c0211d; }
.sc-save-indicator.saving { color: var(--color-text-maxcontrast); }

.sc-save-spinner {
	display: inline-block;
	width: 10px;
	height: 10px;
	border: 1.5px solid color-mix(in srgb, var(--color-primary, #0082c9) 30%, transparent);
	border-top-color: var(--color-primary, #0082c9);
	border-radius: 50%;
	animation: sc-spin 0.7s linear infinite;
}

@keyframes sc-spin {
	from { transform: rotate(0deg); }
	to { transform: rotate(360deg); }
}

@media (max-width: 720px) {
	.sc-hero-stats {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: var(--space-md);
		width: 100%;
	}

	.sc-hero-stat {
		min-width: 0;
		padding-left: 0;
		border-left: none;
		padding-top: var(--space-sm);
		border-top: 1px solid var(--rule-soft);
	}

	.sc-hero-stat:first-child,
	.sc-hero-stat:nth-child(2) {
		padding-top: 0;
		border-top: none;
	}

	.sc-hero-right {
		padding-top: var(--space-sm);
	}
}

@media (max-width: 600px) {
	.sc-hero-title { font-size: 26px; }
	.sc-hero-stat-value { font-size: 22px; }
	.sc-hero-stat-primary .sc-hero-stat-value { font-size: 28px; }

	.sc-hero {
		padding: var(--space-lg) var(--space-md);
	}

	.sc-customize-btn {
		padding: 8px 10px;
		gap: var(--space-2xs);
		min-height: 40px;
	}

	.sc-customize-btn-label {
		display: none;
	}

	.sc-save-indicator {
		display: none;
	}

	.sc-meta-pill-text {
		max-width: 50vw;
	}
}

@media (max-width: 480px) {
	.sc-hero-stats {
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: var(--space-sm);
	}
}

@media (max-width: 360px) {
	.sc-hero-stats {
		grid-template-columns: 1fr;
	}

	.sc-hero-stat {
		padding-top: var(--space-sm);
		border-top: 1px solid var(--rule-soft);
	}

	.sc-hero-stat:first-child {
		padding-top: 0;
		border-top: none;
	}

	.sc-hero-stat:nth-child(2) {
		padding-top: var(--space-sm);
		border-top: 1px solid var(--rule-soft);
	}
}
</style>
