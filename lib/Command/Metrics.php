<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Command;

use OCA\StatsCollector\AppInfo\Application;
use OCA\StatsCollector\Collector\CollectorRegistry;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Metrics extends Command {
	public function __construct(
		private IConfig $config,
		private CollectorRegistry $registry,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('stats_collector:metrics')
			->setDescription('Enable or disable metrics for a collector')
			->addArgument('collector', InputArgument::OPTIONAL, 'Collector ID (e.g. users, files, system)')
			->addOption('enable-all', null, InputOption::VALUE_NONE, 'Enable all metrics for the given collector')
			->addOption('disable-all', null, InputOption::VALUE_NONE, 'Disable all metrics for the given collector')
			->addOption('enable-all-collectors', null, InputOption::VALUE_NONE, 'Enable all metrics for all available collectors')
			->addOption('enable', null, InputOption::VALUE_REQUIRED, 'Comma-separated metric ids to enable for the given collector')
			->addOption('disable', null, InputOption::VALUE_REQUIRED, 'Comma-separated metric ids to disable for the given collector')
			->addOption('list', 'l', InputOption::VALUE_NONE, 'List available metrics for the given collector');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$appId = Application::APP_ID;
		$enabledJson = $this->config->getAppValue($appId, 'enabled_metrics', '{}');
		$enabled = json_decode($enabledJson, true) ?: [];

		// Enable all collectors at once
		if ($input->getOption('enable-all-collectors')) {
			foreach ($this->registry->getAvailable() as $collector) {
				$allMetricIds = array_map(fn ($m) => $m['id'], $collector->getAvailableMetrics());
				$enabled[$collector->getId()] = $allMetricIds;
			}
			$this->config->setAppValue($appId, 'enabled_metrics', json_encode($enabled));
			$output->writeln('<info>All metrics for all available collectors enabled.</info>');
			return 0;
		}

		$collectorId = $input->getArgument('collector');
		if (!$collectorId) {
			// List all collectors
			$output->writeln('<info>Available collectors:</info>');
			foreach ($this->registry->getAvailable() as $collector) {
				$id = $collector->getId();
				$count = count($collector->getAvailableMetrics());
				$activeCount = count($enabled[$id] ?? []);
				$output->writeln("  $id — {$collector->getName()} ($activeCount/$count enabled)");
			}
			$output->writeln('');
			$output->writeln('Use: occ stats_collector:metrics <collector> --list');
			return 0;
		}

		$collector = $this->registry->get($collectorId);
		if (!$collector) {
			$output->writeln("<error>Collector '$collectorId' not found or its required app is not installed.</error>");
			return 1;
		}

		$metrics = $collector->getAvailableMetrics();
		$allMetricIds = array_map(fn ($m) => $m['id'], $metrics);

		if ($input->getOption('list')) {
			$activeMetrics = $enabled[$collectorId] ?? [];
			$output->writeln("<info>Metrics for {$collector->getName()} ($collectorId):</info>");
			foreach ($metrics as $metric) {
				$isEnabled = in_array($metric['id'], $activeMetrics, true);
				$marker = $isEnabled ? '<info>[x]</info>' : '[ ]';
				$output->writeln("  $marker {$metric['id']} — {$metric['name']}");
			}
			return 0;
		}

		if ($input->getOption('enable-all')) {
			$enabled[$collectorId] = $allMetricIds;
			$this->config->setAppValue($appId, 'enabled_metrics', json_encode($enabled));
			$output->writeln("<info>Enabled all " . count($allMetricIds) . " metrics for $collectorId.</info>");
			return 0;
		}

		if ($input->getOption('disable-all')) {
			unset($enabled[$collectorId]);
			$this->config->setAppValue($appId, 'enabled_metrics', json_encode($enabled));
			$output->writeln("<info>Disabled all metrics for $collectorId.</info>");
			return 0;
		}

		$enableList = $this->parseIds((string)$input->getOption('enable'));
		$disableList = $this->parseIds((string)$input->getOption('disable'));

		if ($enableList !== [] || $disableList !== []) {
			$unknown = array_diff([...$enableList, ...$disableList], $allMetricIds);
			if ($unknown !== []) {
				$output->writeln("<error>Unknown metric id(s) for $collectorId: " . implode(', ', $unknown) . "</error>");
				$output->writeln('Run: <comment>occ stats_collector:metrics ' . $collectorId . ' --list</comment>');
				return 1;
			}

			$current = $enabled[$collectorId] ?? [];
			$next = array_values(array_unique(array_diff([...$current, ...$enableList], $disableList)));
			$enabled[$collectorId] = $next;
			$this->config->setAppValue($appId, 'enabled_metrics', json_encode($enabled));

			$summary = [];
			if ($enableList !== []) {
				$summary[] = 'enabled ' . count($enableList) . ' (' . implode(', ', $enableList) . ')';
			}
			if ($disableList !== []) {
				$summary[] = 'disabled ' . count($disableList) . ' (' . implode(', ', $disableList) . ')';
			}
			$output->writeln("<info>$collectorId: " . implode('; ', $summary) . ". Now " . count($next) . "/" . count($allMetricIds) . " enabled.</info>");
			return 0;
		}

		$output->writeln('<comment>Specify --enable-all, --disable-all, --enable=<ids>, --disable=<ids>, or --list.</comment>');
		return 0;
	}

	private function parseIds(string $raw): array {
		if ($raw === '') {
			return [];
		}
		return array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($s) => $s !== ''));
	}
}
