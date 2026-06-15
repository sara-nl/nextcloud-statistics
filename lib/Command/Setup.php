<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Command;

use OCA\StatsCollector\AppInfo\Application;
use OCA\StatsCollector\Collector\CollectorRegistry;
use OCA\StatsCollector\Service\CollectionService;
use OCA\StatsCollector\Service\SnapshotService;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class Setup extends Command {
	public function __construct(
		private IConfig $config,
		private CollectorRegistry $registry,
		private CollectionService $collectionService,
		private SnapshotService $snapshotService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('stats_collector:setup')
			->setDescription('Interactive setup wizard for Stats Collector');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$appId = Application::APP_ID;
		/** @var QuestionHelper $helper */
		$helper = $this->getHelper('question');

		$output->writeln('');
		$output->writeln('<info>╔══════════════════════════════════════════╗</info>');
		$output->writeln('<info>║     Stats Collector — Setup Wizard      ║</info>');
		$output->writeln('<info>╚══════════════════════════════════════════╝</info>');
		$output->writeln('');

		// Step 1: Instance label
		$output->writeln('<info>Step 1/5: Instance Identity</info>');
		$currentLabel = $this->config->getAppValue($appId, 'instance_label', '');
		$q = new Question('Instance label' . ($currentLabel ? " [$currentLabel]" : '') . ': ', $currentLabel);
		$label = $helper->ask($input, $output, $q);
		if ($label) {
			$this->config->setAppValue($appId, 'instance_label', $label);
		}
		$output->writeln('');

		// Step 2: Cron interval
		$output->writeln('<info>Step 2/5: Collection Interval</info>');
		$currentInterval = $this->config->getAppValue($appId, 'cron_interval', 'hourly');
		$q = new ChoiceQuestion(
			"How often should stats be collected? [$currentInterval]",
			['5min' => 'Every 5 minutes', '15min' => 'Every 15 minutes', 'hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly'],
			$currentInterval,
		);
		$interval = $helper->ask($input, $output, $q);
		$this->config->setAppValue($appId, 'cron_interval', $interval);
		$output->writeln('');

		// Step 3: Retention
		$output->writeln('<info>Step 3/5: Snapshot Retention</info>');
		$currentRetention = $this->config->getAppValue($appId, 'snapshot_retention_days', '90');
		$q = new ChoiceQuestion(
			"How long should snapshots be kept? [$currentRetention days]",
			['30' => '30 days', '60' => '60 days', '90' => '90 days', '180' => '180 days', '365' => '1 year', '0' => 'Keep forever'],
			$currentRetention,
		);
		$retention = $helper->ask($input, $output, $q);
		$this->config->setAppValue($appId, 'snapshot_retention_days', $retention);
		$output->writeln('');

		// Step 4: Metrics
		$output->writeln('<info>Step 4/5: Metrics</info>');
		$available = $this->registry->getAvailable();
		$output->writeln(count($available) . ' collector(s) available: ' . implode(', ', array_map(fn ($c) => $c->getName(), $available)));

		$q = new ConfirmationQuestion('Enable all metrics for all collectors? [Y/n] ', true);
		if ($helper->ask($input, $output, $q)) {
			$enabled = [];
			foreach ($available as $collector) {
				$allMetricIds = array_map(fn ($m) => $m['id'], $collector->getAvailableMetrics());
				$enabled[$collector->getId()] = $allMetricIds;
			}
			$this->config->setAppValue($appId, 'enabled_metrics', json_encode($enabled));
			$output->writeln('<info>All metrics enabled.</info>');
		} else {
			$output->writeln('Skipped. Use <comment>occ stats_collector:metrics</comment> to configure individually.');
		}
		$output->writeln('');

		// Step 5: First collection
		$output->writeln('<info>Step 5/5: First Collection</info>');
		$q = new ConfirmationQuestion('Run a collection now to populate the dashboard? [Y/n] ', true);
		if ($helper->ask($input, $output, $q)) {
			try {
				$payload = $this->collectionService->collectAll();
				$this->snapshotService->storeSnapshot($payload);
				$count = count($payload['collectors'] ?? []);
				$output->writeln("<info>Collected and stored snapshot from $count collector(s).</info>");
			} catch (\Exception $e) {
				$output->writeln('<error>Collection failed: ' . $e->getMessage() . '</error>');
			}
		}

		$output->writeln('');
		$output->writeln('<info>Setup complete!</info>');
		$output->writeln('');
		$output->writeln('Next steps:');
		$output->writeln('  1. Generate API keys for external systems via the admin UI: API Keys tab');
		$output->writeln('  2. Or use OCC: <comment>occ stats_collector:status</comment>');
		$output->writeln('  3. External systems pull snapshots via <comment>GET /ocs/v2.php/apps/stats_collector/api/v1/snapshots</comment>');
		$output->writeln('');

		return 0;
	}
}
