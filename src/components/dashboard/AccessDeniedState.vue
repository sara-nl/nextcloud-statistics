<template>
	<div class="sc-denied">
		<div class="sc-denied-card">
			<svg class="sc-denied-icon" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<rect x="3" y="11" width="18" height="11" rx="2" />
				<path d="M7 11V7a5 5 0 0 1 10 0v4" />
			</svg>
			<h2>Access denied</h2>
			<p>
				You don't have permission to view the Stats Collector dashboard.
				Ask an administrator to add you to an authorized group.
			</p>
		</div>
	</div>
</template>

<script>
export default {
	name: 'AccessDeniedState',
}
</script>

<style scoped>
.sc-denied {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 32px 20px;
}

.sc-denied-card {
	max-width: 420px;
	width: 100%;
	padding: 40px 32px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 16px;
	text-align: center;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04), 0 8px 24px rgba(0, 0, 0, 0.04);
}

.sc-denied-icon {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
	opacity: 0.7;
}

.sc-denied-card h2 {
	font-size: 20px;
	font-weight: 600;
	margin: 0 0 8px;
	color: var(--color-main-text);
}

.sc-denied-card p {
	font-size: 14px;
	line-height: 1.55;
	color: var(--color-text-maxcontrast);
	margin: 0;
}
</style>
