<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Command;

use OCA\StatsCollector\AppInfo\Application;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Reset extends Command {
	private const ALL_KEYS = [
		'cron_interval',
		'instance_label',
		'snapshot_retention_days',
		'enabled_metrics',
		'last_snapshot',
		'api_keys',
		'chart_logo_url',
		'chart_font',
		'chart_colors',
		'allowed_groups',
	];

	public function __construct(
		private IConfig $config,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('stats_collector:reset')
			->setDescription('Remove all Stats Collector configuration and data')
			->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$appId = Application::APP_ID;

		if (!$input->getOption('yes')) {
			/** @var QuestionHelper $helper */
			$helper = $this->getHelper('question');
			$q = new ConfirmationQuestion(
				'<comment>This will remove ALL Stats Collector settings, history, API keys, and views. Continue? [y/N] </comment>',
				false
			);
			if (!$helper->ask($input, $output, $q)) {
				$output->writeln('Aborted.');
				return 0;
			}
		}

		$removed = 0;
		foreach (self::ALL_KEYS as $key) {
			$current = $this->config->getAppValue($appId, $key, '');
			if ($current !== '') {
				$this->config->deleteAppValue($appId, $key);
				$removed++;
			}
		}

		$output->writeln("<info>Removed $removed setting(s).</info>");
		$output->writeln('');
		$output->writeln('The app is still installed. To fully remove:');
		$output->writeln('  <comment>occ app:disable stats_collector</comment>');
		$output->writeln('  <comment>occ app:remove stats_collector</comment>');

		return 0;
	}
}
