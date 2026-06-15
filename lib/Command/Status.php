<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Command;

use OCA\StatsCollector\AppInfo\Application;
use OCA\StatsCollector\Collector\CollectorRegistry;
use OCA\StatsCollector\Service\SnapshotService;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Status extends Command {
	public function __construct(
		private IConfig $config,
		private CollectorRegistry $registry,
		private SnapshotService $snapshotService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('stats_collector:status')
			->setDescription('Show current Stats Collector configuration and snapshot info');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$appId = Application::APP_ID;

		$output->writeln('<info>== Stats Collector Configuration ==</info>');
		$output->writeln('');

		$interval = $this->config->getAppValue($appId, 'cron_interval', 'hourly');
		$label = $this->config->getAppValue($appId, 'instance_label', '');
		$retention = $this->config->getAppValue($appId, 'snapshot_retention_days', '90');
		$output->writeln("Cron interval:    $interval");
		$output->writeln("Instance label:   " . ($label ?: '<auto>'));
		$output->writeln('Retention:        ' . ($retention === '0' ? 'keep forever' : "$retention days"));

		// Snapshots
		$snapshots = $this->snapshotService->listSnapshots(null, null, 1, false);
		$keys = $this->snapshotService->getApiKeys();
		$output->writeln('');
		$output->writeln('<info>== Storage ==</info>');
		$output->writeln('');
		$output->writeln('Latest snapshot:  ' . ($snapshots[0]['timestamp'] ?? '<none yet>'));
		$output->writeln('API keys:         ' . count($keys) . ' configured');

		// Enabled metrics
		$output->writeln('');
		$output->writeln('<info>== Collectors ==</info>');
		$output->writeln('');

		$enabledJson = $this->config->getAppValue($appId, 'enabled_metrics', '{}');
		$enabled = json_decode($enabledJson, true) ?: [];

		foreach ($this->registry->getAvailable() as $collector) {
			$id = $collector->getId();
			$metrics = $enabled[$id] ?? [];
			$available = count($collector->getAvailableMetrics());
			$active = count($metrics);
			$status = $active > 0 ? "<info>$active/$available metrics</info>" : '<comment>disabled</comment>';
			$output->writeln("  {$collector->getName()} ($id): $status");
		}

		return 0;
	}
}
