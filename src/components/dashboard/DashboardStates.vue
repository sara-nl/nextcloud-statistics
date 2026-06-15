<template>
	<div v-if="loading" class="sc-state">
		<div class="sc-skeleton-hero" />
		<div class="sc-skeleton-grid">
			<div v-for="i in 8" :key="i" class="sc-skeleton-card" />
		</div>
	</div>
	<div v-else-if="error" class="sc-state sc-state-fade">
		<div class="sc-state-card sc-state-error">
			<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<circle cx="12" cy="12" r="10" />
				<path d="M12 8v4" />
				<path d="M12 16h.01" />
			</svg>
			<h3>Couldn't load statistics</h3>
			<p>{{ error }}</p>
		</div>
	</div>
	<div v-else-if="empty" class="sc-state sc-state-fade">
		<div class="sc-state-card">
			<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M3 3v18h18" />
				<path d="M7 14l4-4 4 4 5-5" />
			</svg>
			<h3>No statistics yet</h3>
			<p>
				Either no metrics have been selected by the administrator,
				or no data has been collected yet. Data is collected in the background and refreshed automatically.
			</p>
		</div>
	</div>
</template>

<script>
export default {
	name: 'DashboardStates',
	props: {
		loading: { type: Boolean, default: false },
		error: { type: [String, null], default: null },
		empty: { type: Boolean, default: false },
	},
}
</script>

<style scoped>
.sc-state { margin-top: var(--space-md); }

@media (prefers-reduced-motion: no-preference) {
	.sc-state-fade { animation: sc-state-fade-in 200ms var(--ease-out-quart) both; }
	@keyframes sc-state-fade-in { from { opacity: 0; } to { opacity: 1; } }
}

.sc-state-card {
	background: var(--surface-raised);
	border: 1px solid var(--rule);
	border-radius: var(--radius-md);
	padding: var(--space-2xl) var(--space-xl);
	text-align: center;
	max-width: 520px;
	margin: var(--space-lg) 0;
	color: var(--color-text-maxcontrast);
}

.sc-state-card svg { color: var(--color-text-maxcontrast); opacity: 0.7; margin-bottom: var(--space-sm); }
.sc-state-card h3 { color: var(--color-main-text); font-size: 17px; font-weight: 600; margin: 0 0 var(--space-xs); }
.sc-state-card p { font-size: 14px; line-height: 1.55; margin: 0; }
.sc-state-error svg { color: #e74c3c; opacity: 1; }

.sc-skeleton-hero,
.sc-skeleton-card {
	border-radius: var(--radius-md);
	background: linear-gradient(
		90deg,
		var(--surface-quiet) 0%,
		color-mix(in srgb, var(--color-main-text, #000) 6%, transparent) 50%,
		var(--surface-quiet) 100%
	);
	background-size: 200% 100%;
	animation: sc-shimmer 1.6s linear infinite;
	border: 1px solid var(--rule);
}

.sc-skeleton-hero { height: 160px; margin-bottom: var(--space-xl); }
.sc-skeleton-card { height: 116px; }

.sc-skeleton-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
	gap: var(--space-sm);
}

@keyframes sc-shimmer {
	0% { background-position: 200% 0; }
	100% { background-position: -200% 0; }
}
</style>
