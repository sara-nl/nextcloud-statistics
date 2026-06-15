<template>
	<Teleport to="body">
		<transition name="sc-drawer-backdrop">
			<div v-if="open" class="sc-drawer-backdrop sc-no-export" @click="close" />
		</transition>
		<transition name="sc-drawer">
			<aside v-if="open"
				class="sc-drawer sc-no-export"
				role="dialog"
				aria-modal="true"
				aria-labelledby="sc-drawer-title"
				@click.stop>
				<header class="sc-drawer-head">
					<h2 id="sc-drawer-title">Customize dashboard</h2>
					<button type="button" class="sc-drawer-close" aria-label="Close" @click="close">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<line x1="18" y1="6" x2="6" y2="18" />
							<line x1="6" y1="6" x2="18" y2="18" />
						</svg>
					</button>
				</header>

				<div class="sc-drawer-body">
					<section class="sc-drawer-section">
						<h3 class="sc-drawer-section-title">Density</h3>
						<p class="sc-drawer-section-hint">Scales paddings and gaps across the page.</p>
						<div class="sc-segmented" role="radiogroup" aria-label="Density">
							<button
								v-for="d in densityOptions"
								:key="d.key"
								type="button"
								role="radio"
								:aria-checked="preferences.density === d.key"
								:class="['sc-segmented-btn', { active: preferences.density === d.key }]"
								@click="setDensity(d.key)">
								{{ d.label }}
							</button>
						</div>
					</section>

					<section class="sc-drawer-section">
						<h3 class="sc-drawer-section-title">Hero stats</h3>
						<p class="sc-drawer-section-hint">
							Pick up to 4 metrics to pin in the header. Empty falls back to the auto pick.
						</p>
						<div v-if="preferences.hero_pinned.length === 0" class="sc-drawer-empty">
							Auto-picked
						</div>
						<div v-else class="sc-chip-list">
							<span v-for="key in preferences.hero_pinned" :key="key" class="sc-chip">
								<span class="sc-chip-label">{{ metricLabel(key) }}</span>
								<button type="button" class="sc-chip-remove" :aria-label="'Remove ' + metricLabel(key)" @click="removeHeroPin(key)">
									<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<line x1="18" y1="6" x2="6" y2="18" />
										<line x1="6" y1="6" x2="18" y2="18" />
									</svg>
								</button>
							</span>
						</div>
						<div v-if="preferences.hero_pinned.length < 4" class="sc-drawer-add-row">
							<select class="sc-drawer-select" :value="''" @change="onAddHeroPin($event)">
								<option value="" disabled>Add metric</option>
								<optgroup v-for="group in numericMetricOptions" :key="'hp-' + group.collectorId" :label="formatCollectorName(group.collectorId)">
									<option
										v-for="opt in group.metrics"
										:key="'hp-' + opt.value"
										:value="opt.value"
										:disabled="preferences.hero_pinned.includes(opt.value)">
										{{ opt.label }}
									</option>
								</optgroup>
							</select>
						</div>
					</section>

					<section class="sc-drawer-section">
						<h3 class="sc-drawer-section-title">Default spotlight metric</h3>
						<p class="sc-drawer-section-hint">
							Selected on every page load. Falls back to auto if removed.
						</p>
						<select class="sc-drawer-select" :value="preferences.default_spotlight" @change="onChangeDefaultSpotlight($event)">
							<option value="">Auto</option>
							<optgroup v-for="group in numericMetricOptions" :key="'sp-' + group.collectorId" :label="formatCollectorName(group.collectorId)">
								<option v-for="opt in group.metrics" :key="'sp-' + opt.value" :value="opt.value">
									{{ opt.label }}
								</option>
							</optgroup>
						</select>
					</section>

					<section class="sc-drawer-section">
						<h3 class="sc-drawer-section-title">Sections</h3>
						<p class="sc-drawer-section-hint">
							Drag to reorder. Toggle the checkbox to hide.
						</p>
						<ul class="sc-section-list">
							<li
								v-for="(id, idx) in orderedSectionIds"
								:key="id"
								:class="['sc-section-row', { dragging: draggingId === id, 'drop-target': dragOverId === id }]"
								draggable="true"
								@dragstart="onDragStart($event, id)"
								@dragover.prevent="onDragOver($event, id)"
								@dragleave="onDragLeave($event, id)"
								@drop.prevent="onDrop($event, id)"
								@dragend="onDragEnd">
								<span class="sc-drag-handle" aria-hidden="true">
									<svg width="12" height="14" viewBox="0 0 12 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
										<circle cx="3" cy="3" r="0.8" fill="currentColor" stroke="none" />
										<circle cx="9" cy="3" r="0.8" fill="currentColor" stroke="none" />
										<circle cx="3" cy="7" r="0.8" fill="currentColor" stroke="none" />
										<circle cx="9" cy="7" r="0.8" fill="currentColor" stroke="none" />
										<circle cx="3" cy="11" r="0.8" fill="currentColor" stroke="none" />
										<circle cx="9" cy="11" r="0.8" fill="currentColor" stroke="none" />
									</svg>
								</span>
								<label class="sc-section-row-label">
									<input
										type="checkbox"
										:checked="!preferences.hidden_sections.includes(id)"
										@change="toggleSectionVisibility(id, $event)">
									<span class="sc-section-row-icon" v-html="getIconSvg(id)" />
									<span class="sc-section-row-name">{{ formatCollectorName(id) }}</span>
								</label>
								<span class="sc-section-row-pos">{{ idx + 1 }}</span>
								<span class="sc-section-row-moves">
									<button
										type="button"
										class="sc-section-move"
										:disabled="idx === 0"
										:aria-label="'Move ' + formatCollectorName(id) + ' up'"
										@click="moveSection(id, -1)">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
											<polyline points="6 15 12 9 18 15" />
										</svg>
									</button>
									<button
										type="button"
										class="sc-section-move"
										:disabled="idx === orderedSectionIds.length - 1"
										:aria-label="'Move ' + formatCollectorName(id) + ' down'"
										@click="moveSection(id, 1)">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
											<polyline points="6 9 12 15 18 9" />
										</svg>
									</button>
								</span>
							</li>
						</ul>
					</section>
				</div>

				<footer class="sc-drawer-foot">
					<button type="button" class="sc-drawer-btn sc-drawer-btn-quiet" @click="$emit('reset')">
						Reset to defaults
					</button>
					<button type="button" class="sc-drawer-btn sc-drawer-btn-primary" @click="close">
						Done
					</button>
				</footer>
			</aside>
		</transition>
	</Teleport>
</template>

<script>
import { formatCollectorName, metricLabel } from '../../utils/dashboardFormat.js'
import { getIconSvg } from '../../utils/dashboardIcons.js'
import { DENSITY_OPTIONS } from '../../utils/dashboardConstants.js'

export default {
	name: 'CustomizeDrawer',
	props: {
		open: { type: Boolean, default: false },
		preferences: { type: Object, required: true },
		numericMetricOptions: { type: Array, default: () => [] },
		orderedSectionIds: { type: Array, default: () => [] },
	},
	emits: ['update:open', 'update:preferences', 'reset'],
	data() {
		return {
			draggingId: null,
			dragOverId: null,
			densityOptions: DENSITY_OPTIONS,
		}
	},
	methods: {
		formatCollectorName,
		metricLabel,
		getIconSvg,
		close() {
			this.$emit('update:open', false)
		},
		emitPrefs(next) {
			this.$emit('update:preferences', next)
		},
		setDensity(d) {
			this.emitPrefs({ ...this.preferences, density: d })
		},
		toggleSectionVisibility(id, e) {
			const visible = !!e.target.checked
			const set = new Set(this.preferences.hidden_sections || [])
			if (visible) set.delete(id)
			else set.add(id)
			this.emitPrefs({ ...this.preferences, hidden_sections: Array.from(set) })
		},
		removeHeroPin(key) {
			this.emitPrefs({
				...this.preferences,
				hero_pinned: this.preferences.hero_pinned.filter(k => k !== key),
			})
		},
		onAddHeroPin(e) {
			const value = e.target.value
			if (!value) return
			const current = this.preferences.hero_pinned || []
			if (current.includes(value) || current.length >= 4) {
				e.target.value = ''
				return
			}
			this.emitPrefs({
				...this.preferences,
				hero_pinned: [...current, value],
			})
			e.target.value = ''
		},
		onChangeDefaultSpotlight(e) {
			this.emitPrefs({
				...this.preferences,
				default_spotlight: e.target.value || '',
			})
		},
		onDragStart(e, id) {
			this.draggingId = id
			try {
				e.dataTransfer.effectAllowed = 'move'
				e.dataTransfer.setData('text/plain', id)
			} catch (err) { /* some browsers throw on synthetic events */ }
		},
		onDragOver(e, id) {
			if (!this.draggingId || this.draggingId === id) return
			e.dataTransfer.dropEffect = 'move'
			this.dragOverId = id
		},
		onDragLeave(e, id) {
			if (this.dragOverId === id) this.dragOverId = null
		},
		onDrop(e, targetId) {
			const sourceId = this.draggingId
			this.draggingId = null
			this.dragOverId = null
			if (!sourceId || sourceId === targetId) return
			const order = [...this.orderedSectionIds]
			const from = order.indexOf(sourceId)
			const to = order.indexOf(targetId)
			if (from < 0 || to < 0) return
			order.splice(from, 1)
			order.splice(to, 0, sourceId)
			this.emitPrefs({ ...this.preferences, section_order: order })
		},
		onDragEnd() {
			this.draggingId = null
			this.dragOverId = null
		},
		moveSection(id, delta) {
			const order = [...this.orderedSectionIds]
			const from = order.indexOf(id)
			if (from < 0) return
			const to = from + delta
			if (to < 0 || to >= order.length) return
			order.splice(from, 1)
			order.splice(to, 0, id)
			this.emitPrefs({ ...this.preferences, section_order: order })
		},
	},
}
</script>

<style scoped>
.sc-drawer-backdrop {
	position: fixed;
	top: var(--header-height, 50px);
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(15, 22, 36, 0.32);
	z-index: 2000;
	cursor: pointer;
}

.sc-drawer {
	position: fixed;
	top: var(--header-height, 50px);
	right: 0;
	bottom: 0;
	width: 360px;
	max-width: calc(100vw - 64px);
	background: var(--color-main-background, #fff);
	border-left: 1px solid var(--color-border, #e7e9ec);
	box-shadow: -8px 0 32px -16px rgba(15, 22, 36, 0.18);
	z-index: 2001;
	display: flex;
	flex-direction: column;
	overflow: hidden;
	color: var(--color-main-text);
	padding-right: env(safe-area-inset-right, 0);
}

@media (max-width: 600px) {
	.sc-drawer {
		width: 100vw;
		max-width: 100vw;
		padding-right: env(safe-area-inset-right, 0);
		padding-left: env(safe-area-inset-left, 0);
	}
}

@media (prefers-reduced-motion: no-preference) {
	.sc-drawer-backdrop-enter-active,
	.sc-drawer-backdrop-leave-active { transition: opacity 220ms var(--ease-out-quart); }
	.sc-drawer-backdrop-enter-from,
	.sc-drawer-backdrop-leave-to { opacity: 0; }
	.sc-drawer-enter-active { transition: transform 320ms var(--ease-out-expo); }
	.sc-drawer-leave-active { transition: transform 240ms var(--ease-out-quart); }
	.sc-drawer-enter-from,
	.sc-drawer-leave-to { transform: translateX(100%); }
}

.sc-drawer-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: var(--space-sm);
	padding: 18px 20px;
	border-bottom: 1px solid var(--rule);
	flex-shrink: 0;
}

.sc-drawer-head h2 { margin: 0; font-size: 15px; font-weight: 600; letter-spacing: -0.005em; color: var(--color-main-text); }

.sc-drawer-close {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	border: none;
	background: transparent;
	color: var(--color-text-maxcontrast);
	border-radius: 6px;
	cursor: pointer;
	transition: background 150ms var(--ease-out-quart), color 150ms var(--ease-out-quart);
}

.sc-drawer-close:hover {
	background: color-mix(in srgb, var(--color-main-text, #000) 6%, transparent);
	color: var(--color-main-text);
}

.sc-drawer-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 8px 20px 24px; -webkit-overflow-scrolling: touch; }

@media (max-width: 600px) {
	.sc-drawer-body { padding: 8px 16px 24px; }
}
.sc-drawer-section { padding: 16px 0; border-bottom: 1px solid var(--rule-soft); }
.sc-drawer-section:last-child { border-bottom: none; }

.sc-drawer-section-title {
	margin: 0 0 4px;
	font-size: 12px;
	font-weight: 600;
	letter-spacing: 0.06em;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
}

.sc-drawer-section-hint { margin: 0 0 12px; font-size: 12px; line-height: 1.45; color: var(--color-text-maxcontrast); }

.sc-segmented {
	display: inline-flex;
	width: 100%;
	border: 1px solid var(--rule);
	border-radius: 8px;
	background: var(--surface-raised);
	padding: 3px;
	gap: 2px;
}

.sc-segmented-btn {
	flex: 1 1 0;
	padding: 6px 10px;
	border: none;
	background: transparent;
	font-size: 12px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	border-radius: 6px;
	font-family: inherit;
	transition: background 160ms var(--ease-out-quart), color 160ms var(--ease-out-quart);
}

.sc-segmented-btn:hover:not(.active) {
	background: color-mix(in srgb, var(--color-main-text, #000) 5%, transparent);
	color: var(--color-main-text);
}

.sc-segmented-btn.active { background: var(--color-primary, #0082c9); color: #fff; }
.sc-drawer-empty { font-size: 12px; color: var(--color-text-maxcontrast); font-style: italic; padding: 4px 0 12px; }
.sc-chip-list { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }

.sc-chip {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 4px 4px 4px 10px;
	border: 1px solid var(--rule);
	border-radius: 999px;
	background: color-mix(in srgb, var(--color-primary, #0082c9) 6%, var(--surface-raised));
	font-size: 12px;
	font-weight: 500;
	color: var(--color-main-text);
	max-width: 100%;
}

.sc-chip-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; }

.sc-chip-remove {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 18px;
	height: 18px;
	border: none;
	background: transparent;
	color: var(--color-text-maxcontrast);
	border-radius: 50%;
	cursor: pointer;
	transition: background 140ms var(--ease-out-quart), color 140ms var(--ease-out-quart);
}

.sc-chip-remove:hover { background: color-mix(in srgb, #c0211d 18%, transparent); color: #c0211d; }
.sc-drawer-add-row { margin-top: 4px; }

.sc-drawer-select {
	width: 100%;
	padding: 7px 30px 7px 12px;
	border: 1px solid var(--rule);
	border-radius: 8px;
	font-size: 13px;
	background: var(--surface-raised)
		url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>")
		no-repeat right 10px center;
	color: var(--color-main-text);
	cursor: pointer;
	-webkit-appearance: none;
	appearance: none;
	font-family: inherit;
}

.sc-drawer-select:focus {
	outline: none;
	border-color: var(--color-primary, #0082c9);
	box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary, #0082c9) 18%, transparent);
}

.sc-section-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }

.sc-section-row {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 6px 8px;
	border: 1px solid var(--rule);
	border-radius: 8px;
	background: var(--surface-raised);
	font-size: 13px;
	transition: background 150ms var(--ease-out-quart), border-color 150ms var(--ease-out-quart), opacity 150ms var(--ease-out-quart);
}

.sc-section-row.dragging { opacity: 0.4; }

.sc-section-row.drop-target {
	border-color: var(--color-primary, #0082c9);
	background: color-mix(in srgb, var(--color-primary, #0082c9) 8%, var(--surface-raised));
}

.sc-drag-handle {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 16px;
	height: 22px;
	color: var(--color-text-maxcontrast);
	cursor: grab;
	flex-shrink: 0;
}

.sc-drag-handle:active { cursor: grabbing; }

.sc-section-row-label {
	display: flex;
	align-items: center;
	gap: 8px;
	flex: 1 1 auto;
	min-width: 0;
	cursor: pointer;
	user-select: none;
}

.sc-section-row-label input[type="checkbox"] { flex-shrink: 0; margin: 0; cursor: pointer; }

.sc-section-row-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 22px;
	height: 22px;
	color: var(--color-text-maxcontrast);
	flex-shrink: 0;
}

.sc-section-row-icon :deep(svg) { width: 14px; height: 14px; }
.sc-section-row-name { font-weight: 500; color: var(--color-main-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sc-section-row-pos { font-size: 11px; font-variant-numeric: tabular-nums; color: var(--color-text-maxcontrast); min-width: 16px; text-align: right; flex-shrink: 0; }

.sc-section-row-moves {
	display: inline-flex;
	align-items: center;
	gap: 2px;
	flex-shrink: 0;
	margin-left: 2px;
}

.sc-section-move {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	border: 1px solid transparent;
	background: transparent;
	color: var(--color-text-maxcontrast);
	border-radius: 6px;
	cursor: pointer;
	font-family: inherit;
	transition:
		background 140ms var(--ease-out-quart),
		color 140ms var(--ease-out-quart),
		border-color 140ms var(--ease-out-quart);
}

.sc-section-move:hover:not(:disabled) {
	background: color-mix(in srgb, var(--color-primary, #0082c9) 8%, transparent);
	color: var(--color-primary, #0082c9);
}

.sc-section-move:focus-visible {
	outline: none;
	border-color: var(--color-primary, #0082c9);
	box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary, #0082c9) 25%, transparent);
}

.sc-section-move:disabled {
	opacity: 0.35;
	cursor: not-allowed;
}

@media (max-width: 600px) {
	.sc-section-move {
		width: 36px;
		height: 36px;
	}
	.sc-drag-handle {
		display: none;
	}
}

.sc-drawer-foot {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
	padding: 14px 20px calc(14px + env(safe-area-inset-bottom, 0));
	border-top: 1px solid var(--rule);
	flex-shrink: 0;
	background: var(--surface-raised);
}

@media (max-width: 600px) {
	.sc-drawer-foot {
		padding-left: 16px;
		padding-right: 16px;
	}
	.sc-drawer-btn {
		flex: 1 1 0;
		min-height: 44px;
		padding: 10px 14px;
	}
}

@media (max-width: 360px) {
	.sc-drawer-foot {
		flex-direction: column-reverse;
		align-items: stretch;
	}
	.sc-drawer-btn {
		width: 100%;
	}
}

.sc-drawer-btn {
	padding: 8px 14px;
	font-size: 13px;
	font-weight: 600;
	border-radius: var(--radius-sm);
	cursor: pointer;
	font-family: inherit;
	transition: background 160ms var(--ease-out-quart), border-color 160ms var(--ease-out-quart), color 160ms var(--ease-out-quart);
}

.sc-drawer-btn-quiet { background: transparent; border: 1px solid var(--rule); color: var(--color-text-maxcontrast); }
.sc-drawer-btn-quiet:hover { border-color: color-mix(in srgb, #e9322d 40%, var(--rule)); color: #c0211d; }
.sc-drawer-btn-primary { background: var(--color-primary, #0082c9); border: 1px solid var(--color-primary, #0082c9); color: #fff; }
.sc-drawer-btn-primary:hover { background: color-mix(in srgb, var(--color-primary, #0082c9) 88%, #000); }
.sc-drawer-btn:focus-visible { outline: none; box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary, #0082c9) 25%, transparent); }

@media (prefers-color-scheme: dark) {
	.sc-drawer-backdrop { background: rgba(0, 0, 0, 0.5); }
}
</style>
