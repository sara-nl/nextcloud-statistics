<?php

declare(strict_types=1);

namespace OCA\StatsCollector\BackgroundJob;

use OCA\StatsCollector\AppInfo\Application;
use OCA\StatsCollector\Service\CollectionService;
use OCA\StatsCollector\Service\SnapshotService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Periodically collects statistics from all enabled collectors and stores them
 * locally as snapshots. External systems pull snapshots via the public API.
 *
 * Class name kept as CollectAndForwardJob for backwards compatibility with
 * existing background_jobs registrations.
 */
class CollectAndForwardJob extends TimedJob {
	private const INTERVALS = [
		'5min' => 300,
		'15min' => 900,
		'hourly' => 3600,
		'daily' => 86400,
		'weekly' => 604800,
	];

	public function __construct(
		ITimeFactory $time,
		private CollectionService $collectionService,
		private SnapshotService $snapshotService,
		private IConfig $config,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		$interval = $this->config->getAppValue(Application::APP_ID, 'cron_interval', 'hourly');
		$this->setInterval(self::INTERVALS[$interval] ?? 3600);
	}

	protected function run($argument): void {
		// Cleanup first so failed collections don't delay it
		$this->cleanupSnapshots();

		try {
			$payload = $this->collectionService->collectAll();
			$this->snapshotService->storeSnapshot($payload);
		} catch (\Exception $e) {
			$this->logger->error('CollectAndForwardJob: Collection failed', [
				'exception' => $e,
			]);
		}
	}

	private function cleanupSnapshots(): void {
		try {
			$retention = (int)$this->config->getAppValue(Application::APP_ID, 'snapshot_retention_days', '90');
			if ($retention > 0) {
				$deleted = $this->snapshotService->cleanupOlderThan($retention);
				if ($deleted > 0) {
					$this->logger->info("CollectAndForwardJob: Cleaned up $deleted old snapshot(s)");
				}
			}
		} catch (\Exception $e) {
			// Non-critical
		}
	}
}
