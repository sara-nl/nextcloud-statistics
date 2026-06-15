<template>
	<div class="sc-export-wrap" :class="{ open }">
		<button
			type="button"
			class="sc-export-btn"
			aria-label="Export"
			@click.stop="toggle">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
				<polyline points="7 10 12 15 17 10" />
				<line x1="12" y1="15" x2="12" y2="3" />
			</svg>
			<span class="sc-export-btn-label">Export</span>
			<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<polyline points="6 9 12 15 18 9" />
			</svg>
		</button>
		<transition name="sc-export-menu">
			<div v-if="open" class="sc-export-menu" @click.stop>
				<button type="button" class="sc-export-item" @click="pick('pdf')">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M6 2h9l5 5v15a0 0 0 0 1 0 0H6a0 0 0 0 1 0 0V2z" />
						<polyline points="15 2 15 7 20 7" />
					</svg>
					<span>Print / Save as PDF</span>
				</button>
				<button type="button" class="sc-export-item" @click="pick('csv')">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<rect x="3" y="3" width="18" height="18" rx="2" />
						<line x1="3" y1="9" x2="21" y2="9" />
						<line x1="3" y1="15" x2="21" y2="15" />
						<line x1="9" y1="3" x2="9" y2="21" />
					</svg>
					<span>Download CSV</span>
				</button>
				<button type="button" class="sc-export-item" @click="pick('json')">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<polyline points="16 18 22 12 16 6" />
						<polyline points="8 6 2 12 8 18" />
					</svg>
					<span>Download JSON</span>
				</button>
			</div>
		</transition>
	</div>
</template>

<script>
export default {
	name: 'ExportMenu',
	props: {
		open: { type: Boolean, default: false },
	},
	emits: ['update:open', 'export'],
	methods: {
		toggle() {
			this.$emit('update:open', !this.open)
		},
		pick(format) {
			this.$emit('update:open', false)
			this.$emit('export', format)
		},
	},
}
</script>

<style scoped>
.sc-export-wrap { position: relative; }

.sc-export-btn {
	display: inline-flex;
	align-items: center;
	gap: var(--space-xs);
	padding: 8px 14px;
	font-size: 13px;
	font-weight: 600;
	color: var(--color-main-text);
	background: var(--surface-raised);
	border: 1px solid var(--rule);
	border-radius: var(--radius-sm);
	cursor: pointer;
	transition:
		border-color 180ms var(--ease-out-quart),
		box-shadow 180ms var(--ease-out-quart),
		background 180ms var(--ease-out-quart);
	font-family: inherit;
}

.sc-export-btn:hover:not(:disabled) {
	border-color: color-mix(in srgb, var(--color-primary, #0082c9) 50%, var(--rule));
	background: color-mix(in srgb, var(--color-primary, #0082c9) 4%, var(--surface-raised));
}

.sc-export-btn:focus-visible {
	outline: none;
	border-color: var(--color-primary, #0082c9);
	box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary, #0082c9) 18%, transparent);
}

.sc-export-btn:disabled { opacity: 0.7; cursor: progress; }

.sc-export-wrap.open .sc-export-btn {
	border-color: var(--color-primary, #0082c9);
	box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary, #0082c9) 18%, transparent);
}

.sc-export-menu {
	position: absolute;
	top: calc(100% + 6px);
	right: 0;
	min-width: 200px;
	max-width: calc(100vw - 32px);
	padding: 6px;
	background: var(--surface-raised);
	border: 1px solid var(--rule);
	border-radius: var(--radius-sm);
	box-shadow: 0 8px 28px rgba(15, 22, 36, 0.12), 0 1px 3px rgba(15, 22, 36, 0.06);
	z-index: 20;
	display: flex;
	flex-direction: column;
	gap: 1px;
	transform-origin: top right;
}

@media (max-width: 600px) {
	.sc-export-btn {
		min-height: 40px;
		padding: 8px 10px;
	}
	.sc-export-btn-label {
		display: none;
	}
	.sc-export-item {
		min-height: 44px;
		padding: 10px;
	}
}

@media (prefers-reduced-motion: no-preference) {
	.sc-export-menu-enter-active {
		transition:
			opacity 200ms var(--ease-out-expo),
			transform 200ms var(--ease-out-expo);
	}
	.sc-export-menu-leave-active {
		transition:
			opacity 150ms var(--ease-out-quart),
			transform 150ms var(--ease-out-quart);
	}
	.sc-export-menu-enter-from,
	.sc-export-menu-leave-to {
		opacity: 0;
		transform: translate3d(0, -6px, 0);
	}
}

.sc-export-item {
	display: flex;
	align-items: center;
	gap: var(--space-sm);
	padding: 8px 10px;
	border: none;
	background: transparent;
	font-size: 13px;
	font-weight: 500;
	color: var(--color-main-text);
	border-radius: 6px;
	cursor: pointer;
	text-align: left;
	font-family: inherit;
	transition:
		background 150ms var(--ease-out-quart),
		color 150ms var(--ease-out-quart);
}

.sc-export-item:hover {
	background: color-mix(in srgb, var(--color-primary, #0082c9) 8%, transparent);
	color: var(--color-primary, #0082c9);
}

.sc-export-item svg { color: var(--color-text-maxcontrast); flex-shrink: 0; }
.sc-export-item:hover svg { color: var(--color-primary, #0082c9); }
</style>
