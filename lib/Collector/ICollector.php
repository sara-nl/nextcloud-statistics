<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Collector;

interface ICollector {
	/**
	 * Unique identifier, e.g. "users", "files", "talk"
	 */
	public function getId(): string;

	/**
	 * Human-readable name, e.g. "Nextcloud Talk"
	 */
	public function getName(): string;

	/**
	 * Description of what this collector gathers
	 */
	public function getDescription(): string;

	/**
	 * The Nextcloud app ID this collector requires.
	 * Return "core" for collectors that need no optional app.
	 */
	public function getAppId(): string;

	/**
	 * Icon identifier for the admin UI
	 */
	public function getIcon(): string;

	/**
	 * Return available metric definitions.
	 *
	 * @return array<array{id: string, name: string, description: string, type: string, method: string}>
	 */
	public function getAvailableMetrics(): array;

	/**
	 * Collect current values for the given metric IDs.
	 *
	 * @param string[] $enabledMetricIds Subset of metric IDs from getAvailableMetrics()
	 * @return array<string, mixed> metric_id => value
	 */
	public function collect(array $enabledMetricIds): array;
}
