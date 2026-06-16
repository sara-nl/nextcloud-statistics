<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Command;

use OCA\StatsCollector\Service\SnapshotService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Manage API keys non-interactively. Mirrors the Admin > API Keys tab so ops
 * automation (Ansible, k8s Jobs, etc.) can seed and revoke keys without a UI.
 */
class ApiKey extends Command {
	public function __construct(
		private SnapshotService $snapshotService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('stats_collector:api-key')
			->setDescription('Create, list or revoke API keys for the pull endpoint')
			->addArgument('action', InputArgument::REQUIRED, 'One of: create, list, revoke')
			->addOption('label', null, InputOption::VALUE_REQUIRED, 'Human-readable label (required for "create" and "revoke")')
			->addOption('id', null, InputOption::VALUE_REQUIRED, 'Key id (alternative to --label for "revoke")')
			->addOption('key', null, InputOption::VALUE_REQUIRED, 'On "create", register this exact plaintext key (32+ chars). Omit to let the server generate one.')
			->addOption('quiet-key', null, InputOption::VALUE_NONE, 'On "create", print only the plaintext key (for scripting)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$action = (string)$input->getArgument('action');

		return match ($action) {
			'create' => $this->create($input, $output),
			'list' => $this->list($output),
			'revoke' => $this->revoke($input, $output),
			default => $this->fail($output, "Unknown action: $action. Expected create | list | revoke."),
		};
	}

	private function create(InputInterface $input, OutputInterface $output): int {
		$label = (string)$input->getOption('label');
		if ($label === '') {
			return $this->fail($output, '--label is required for create.');
		}

		foreach ($this->snapshotService->getApiKeys() as $existing) {
			if (($existing['label'] ?? '') === $label) {
				return $this->fail($output, "An API key with label '$label' already exists. Revoke it first or choose another label.");
			}
		}

		$providedKey = (string)$input->getOption('key');

		try {
			$record = $this->snapshotService->createApiKey($label, $providedKey);
		} catch (\InvalidArgumentException $e) {
			return $this->fail($output, $e->getMessage());
		}
		$plain = (string)($record['key'] ?? '');

		if ($input->getOption('quiet-key')) {
			$output->write($plain);
			return 0;
		}

		$output->writeln("<info>API key created.</info>");
		$output->writeln("Label : " . $record['label']);
		$output->writeln("Id    : " . $record['id']);
		$output->writeln("Key   : <comment>$plain</comment>");
		$output->writeln('');
		if ($providedKey === '') {
			$output->writeln('<comment>Copy this key now. It is hashed at rest and cannot be recovered.</comment>');
		} else {
			$output->writeln('<comment>Stored. The plaintext is only stored encrypted; this output is the key you provided.</comment>');
		}
		return 0;
	}

	private function list(OutputInterface $output): int {
		$keys = $this->snapshotService->getApiKeys();
		if ($keys === []) {
			$output->writeln('No API keys.');
			return 0;
		}

		$table = new Table($output);
		$table->setHeaders(['Id', 'Label', 'Preview', 'Created']);
		foreach ($keys as $k) {
			$table->addRow([
				$k['id'] ?? '',
				$k['label'] ?? '',
				($k['key_prefix'] ?? '????') . '...',
				$k['created_at'] ?? '',
			]);
		}
		$table->render();
		return 0;
	}

	private function revoke(InputInterface $input, OutputInterface $output): int {
		$id = (string)$input->getOption('id');
		$label = (string)$input->getOption('label');

		if ($id === '' && $label === '') {
			return $this->fail($output, 'Pass either --id or --label to identify the key to revoke.');
		}

		if ($id === '') {
			foreach ($this->snapshotService->getApiKeys() as $k) {
				if (($k['label'] ?? '') === $label) {
					$id = (string)($k['id'] ?? '');
					break;
				}
			}
			if ($id === '') {
				return $this->fail($output, "No API key found with label '$label'.");
			}
		}

		$this->snapshotService->revokeApiKey($id);
		$output->writeln("<info>Revoked $id.</info>");
		return 0;
	}

	private function fail(OutputInterface $output, string $msg): int {
		$output->writeln("<error>$msg</error>");
		return 1;
	}
}
