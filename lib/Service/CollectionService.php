<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Service;

use OCA\StatsCollector\AppInfo\Application;
use OCA\StatsCollector\Collector\CollectorRegistry;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class CollectionService {
	public function __construct(
		private CollectorRegistry $registry,
		private IConfig $config,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Collect all enabled metrics and build the full payload.
	 */
	public function collectAll(): array {
		$enabledMetrics = $this->getEnabledMetrics();
		$collectors = [];

		foreach ($this->registry->getAvailable() as $collector) {
			$id = $collector->getId();
			if (!isset($enabledMetrics[$id]) || empty($enabledMetrics[$id])) {
				continue;
			}

			try {
				$data = $collector->collect($enabledMetrics[$id]);
				if (!empty($data)) {
					$collectors[$id] = $data;
				}
			} catch (\Exception $e) {
				$this->logger->error('CollectionService: Collector "' . $id . '" failed', [
					'exception' => $e,
				]);
			}
		}

		$payload = [
			'instance_id' => $this->config->getSystemValueString('instanceid', ''),
			'instance_label' => $this->config->getAppValue(Application::APP_ID, 'instance_label', ''),
			'instance_url' => $this->config->getSystemValueString('overwrite.cli.url', ''),
			'nextcloud_version' => $this->config->getSystemValueString('version', ''),
			'timestamp' => (new \DateTimeImmutable())->format('c'),
			'collectors' => $collectors,
		];

		// Cache the latest snapshot for the dashboard
		$this->config->setAppValue(
			Application::APP_ID,
			'last_snapshot',
			json_encode($payload, JSON_UNESCAPED_SLASHES)
		);

		return $payload;
	}

	/**
	 * Get the cached snapshot (from the last collection run).
	 */
	public function getLastSnapshot(): ?array {
		$json = $this->config->getAppValue(Application::APP_ID, 'last_snapshot', '');
		if (empty($json)) {
			return null;
		}
		$data = json_decode($json, true);
		return is_array($data) ? $data : null;
	}

	/**
	 * Get the enabled metrics configuration.
	 *
	 * @return array<string, string[]> collector_id => [metric_ids]
	 */
	public function getEnabledMetrics(): array {
		$json = $this->config->getAppValue(Application::APP_ID, 'enabled_metrics', '{}');
		$decoded = json_decode($json, true);
		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * Save the enabled metrics configuration.
	 *
	 * @param array<string, string[]> $metrics collector_id => [metric_ids]
	 */
	public function setEnabledMetrics(array $metrics): void {
		$this->config->setAppValue(
			Application::APP_ID,
			'enabled_metrics',
			json_encode($metrics)
		);
	}
}
