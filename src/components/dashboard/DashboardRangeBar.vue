<template>
	<div class="sc-range-bar sc-no-export">
		<div class="sc-range-presets" role="tablist" aria-label="Time range" :data-active="range">
			<button v-for="preset in presets"
				:key="preset.key"
				role="tab"
				:aria-selected="range === preset.key"
				:class="['sc-preset-btn', { active: range === preset.key }]"
				@click="$emit('update:range', preset.key)">
				{{ preset.label }}
			</button>
		</div>
		<div class="sc-range-info">
			<span v-if="historyLoading" class="sc-range-loading">
				<span class="sc-range-spinner" />
				Loading history
			</span>
			<span v-else>
				<strong>{{ historyCount }}</strong>
				snapshot{{ historyCount === 1 ? '' : 's' }} in range
			</span>
		</div>
	</div>
</template>

<script>
export default {
	name: 'DashboardRangeBar',
	props: {
		range: { type: String, required: true },
		presets: { type: Array, required: true },
		historyLoading: { type: Boolean, default: false },
		historyCount: { type: Number, default: 0 },
	},
	emits: ['update:range'],
}
</script>

<style scoped>
.sc-range-bar {
	position: sticky;
	top: 0;
	z-index: 5;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: var(--space-md);
	padding: var(--space-sm) 0;
	margin-bottom: var(--space-xl);
	flex-wrap: wrap;
	background:
		linear-gradient(to bottom, var(--surface-page) 70%, transparent);
	backdrop-filter: blur(6px);
}

@media (max-width: 600px) {
	.sc-range-bar {
		gap: var(--space-xs);
	}
	.sc-range-presets {
		max-width: 100%;
		overflow-x: auto;
		scroll-snap-type: x mandatory;
		-webkit-overflow-scrolling: touch;
		scrollbar-width: none;
	}
	.sc-range-presets::-webkit-scrollbar { display: none; }
	.sc-preset-btn {
		scroll-snap-align: start;
		flex-shrink: 0;
		min-height: 36px;
		padding: 8px 12px;
	}
	.sc-range-info {
		flex-basis: 100%;
		font-size: 11px;
	}
}

.sc-range-bar::after {
	content: "";
	position: absolute;
	left: 0;
	right: 0;
	bottom: 0;
	height: 1px;
	background: var(--rule-soft);
}

.sc-range-presets {
	position: relative;
	display: inline-flex;
	border: 1px solid var(--rule);
	border-radius: var(--radius-sm);
	background: var(--surface-raised);
	padding: 3px;
	gap: 2px;
	isolation: isolate;
}

.sc-range-presets::before {
	content: "";
	position: absolute;
	z-index: 0;
	top: 3px;
	bottom: 3px;
	left: 3px;
	width: 0;
	background: var(--color-primary, #0082c9);
	border-radius: 6px;
	pointer-events: none;
	transform: translate3d(0, 0, 0);
	opacity: 0;
}

@media (prefers-reduced-motion: no-preference) {
	.sc-range-presets::before {
		transition:
			transform 250ms var(--ease-out-quint),
			width 250ms var(--ease-out-quint),
			opacity 200ms var(--ease-out-quart);
	}
}

.sc-range-presets:has(.sc-preset-btn.active)::before {
	opacity: 1;
}

.sc-range-presets[data-active="24h"]::before { transform: translate3d(0%, 0, 0); }
.sc-range-presets[data-active="7d"]::before  { transform: translate3d(100%, 0, 0); }
.sc-range-presets[data-active="30d"]::before { transform: translate3d(200%, 0, 0); }
.sc-range-presets[data-active="90d"]::before { transform: translate3d(300%, 0, 0); }
.sc-range-presets[data-active="all"]::before { transform: translate3d(400%, 0, 0); }

.sc-range-presets:has(.sc-preset-btn.active)::before {
	width: calc((100% - 6px) / 5);
}

.sc-preset-btn {
	position: relative;
	z-index: 1;
	padding: 6px 14px;
	border: none;
	background: transparent;
	cursor: pointer;
	font-size: 13px;
	font-weight: 500;
	color: var(--color-text-maxcontrast);
	transition:
		color 200ms var(--ease-out-quart),
		background 150ms var(--ease-out-quart);
	border-radius: 6px;
	font-family: inherit;
	font-variant-numeric: tabular-nums;
}

.sc-preset-btn:hover:not(.active) {
	background: color-mix(in srgb, var(--color-main-text, #000) 5%, transparent);
	color: var(--color-main-text);
}

.sc-preset-btn.active {
	color: #fff;
}

@supports not selector(:has(*)) {
	.sc-preset-btn.active {
		background: var(--color-primary, #0082c9);
	}
}

.sc-preset-btn:focus-visible {
	outline: none;
	box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary, #0082c9) 35%, transparent);
}

.sc-range-info {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	display: inline-flex;
	align-items: center;
	gap: var(--space-xs);
}

.sc-range-info strong {
	color: var(--color-main-text);
	font-weight: 600;
	font-variant-numeric: tabular-nums;
}

.sc-range-loading {
	display: inline-flex;
	align-items: center;
	gap: var(--space-xs);
}

.sc-range-spinner {
	display: inline-block;
	width: 12px;
	height: 12px;
	border: 2px solid color-mix(in srgb, var(--color-primary, #0082c9) 30%, transparent);
	border-top-color: var(--color-primary, #0082c9);
	border-radius: 50%;
	animation: sc-spin 0.7s linear infinite;
}

@keyframes sc-spin {
	from { transform: rotate(0deg); }
	to { transform: rotate(360deg); }
}
</style>
