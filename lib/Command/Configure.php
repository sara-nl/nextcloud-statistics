<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Command;

use OCA\StatsCollector\AppInfo\Application;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Configure extends Command {
	public function __construct(
		private IConfig $config,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('stats_collector:configure')
			->setDescription('Configure Stats Collector settings')
			->addOption('cron-interval', null, InputOption::VALUE_REQUIRED, 'Cron interval: 5min, 15min, hourly, daily, weekly')
			->addOption('instance-label', null, InputOption::VALUE_REQUIRED, 'Custom instance label')
			->addOption('retention-days', null, InputOption::VALUE_REQUIRED, 'Snapshot retention in days (0 = keep forever)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$appId = Application::APP_ID;
		$changed = 0;

		$map = [
			'cron-interval' => 'cron_interval',
			'instance-label' => 'instance_label',
			'retention-days' => 'snapshot_retention_days',
		];

		foreach ($map as $option => $configKey) {
			$value = $input->getOption($option);
			if ($value !== null) {
				$this->config->setAppValue($appId, $configKey, $value);
				$output->writeln("<info>Set $configKey</info>");
				$changed++;
			}
		}

		if ($changed === 0) {
			$output->writeln('<comment>No options provided. Use --help to see available options.</comment>');
			return 0;
		}

		$output->writeln("<info>$changed setting(s) updated.</info>");
		return 0;
	}
}
