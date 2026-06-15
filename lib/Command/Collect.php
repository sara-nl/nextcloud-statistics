<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Command;

use OCA\StatsCollector\Service\CollectionService;
use OCA\StatsCollector\Service\SnapshotService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Collect extends Command {
	public function __construct(
		private CollectionService $collectionService,
		private SnapshotService $snapshotService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('stats_collector:collect')
			->setDescription('Collect stats and store a local snapshot')
			->addOption('preview', null, InputOption::VALUE_NONE, 'Print collected payload without storing')
			->addOption('no-store', null, InputOption::VALUE_NONE, 'Collect only, do not persist as snapshot');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->writeln('<info>Collecting stats...</info>');

		try {
			$payload = $this->collectionService->collectAll();
		} catch (\Exception $e) {
			$output->writeln('<error>Collection failed: ' . $e->getMessage() . '</error>');
			return 1;
		}

		$collectorCount = count($payload['collectors'] ?? []);
		$output->writeln("Collected data from $collectorCount collector(s).");

		if ($input->getOption('preview')) {
			$output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
			return 0;
		}

		if ($input->getOption('no-store')) {
			$output->writeln('<comment>Skipping snapshot storage (--no-store).</comment>');
			return 0;
		}

		try {
			$this->snapshotService->storeSnapshot($payload);
			$output->writeln('<info>Snapshot stored.</info>');
		} catch (\Exception $e) {
			$output->writeln('<error>Snapshot storage failed: ' . $e->getMessage() . '</error>');
			return 1;
		}

		return 0;
	}
}
