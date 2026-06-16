<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Command;

use OCA\StatsCollector\AppInfo\Application;
use OCP\IConfig;
use OCP\IGroupManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Manage which Nextcloud groups can access the personal dashboard.
 * Mirrors the Admin > Access tab so ops automation can pin the
 * allowlist without a UI.
 */
class Groups extends Command {
	public function __construct(
		private IConfig $config,
		private IGroupManager $groupManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('stats_collector:groups')
			->setDescription('Manage groups allowed to see the personal dashboard')
			->addArgument('action', InputArgument::REQUIRED, 'One of: list, add, remove, set, clear')
			->addArgument('groups', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Group id(s). For "set", the full list replaces the current one. For "add"/"remove", supply one or more ids. Accepts space- or comma-separated.')
			->addOption('skip-validation', null, InputOption::VALUE_NONE, 'Do not check that each group exists (use when seeding before groups are created)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$action = (string)$input->getArgument('action');
		$rawGroups = $this->flatten((array)$input->getArgument('groups'));
		$skipValidation = (bool)$input->getOption('skip-validation');

		return match ($action) {
			'list' => $this->list($output),
			'add' => $this->add($output, $rawGroups, $skipValidation),
			'remove' => $this->remove($output, $rawGroups),
			'set' => $this->set($output, $rawGroups, $skipValidation),
			'clear' => $this->set($output, [], true),
			default => $this->fail($output, "Unknown action: $action. Expected list | add | remove | set | clear."),
		};
	}

	private function list(OutputInterface $output): int {
		$current = $this->getAllowed();
		if ($current === []) {
			$output->writeln('No groups allowed. Only admins can see the personal dashboard.');
			return 0;
		}

		$table = new Table($output);
		$table->setHeaders(['Group id', 'Exists', 'Members']);
		foreach ($current as $gid) {
			$group = $this->groupManager->get($gid);
			$exists = $group !== null;
			$count = $exists ? count($group->getUsers()) : 0;
			$table->addRow([$gid, $exists ? 'yes' : '<comment>missing</comment>', $exists ? (string)$count : '-']);
		}
		$table->render();
		return 0;
	}

	private function add(OutputInterface $output, array $groups, bool $skipValidation): int {
		if ($groups === []) {
			return $this->fail($output, 'Pass at least one group id.');
		}
		if (!$skipValidation && ($invalid = $this->findMissing($groups)) !== []) {
			return $this->fail($output, 'Unknown group(s): ' . implode(', ', $invalid) . '. Pass --skip-validation to add anyway.');
		}
		$next = array_values(array_unique([...$this->getAllowed(), ...$groups]));
		$this->save($next);
		$output->writeln('<info>Added ' . count($groups) . '. Now ' . count($next) . ' allowed group(s).</info>');
		return 0;
	}

	private function remove(OutputInterface $output, array $groups): int {
		if ($groups === []) {
			return $this->fail($output, 'Pass at least one group id.');
		}
		$next = array_values(array_diff($this->getAllowed(), $groups));
		$this->save($next);
		$output->writeln('<info>Removed ' . count($groups) . '. Now ' . count($next) . ' allowed group(s).</info>');
		return 0;
	}

	private function set(OutputInterface $output, array $groups, bool $skipValidation): int {
		$groups = array_values(array_unique($groups));
		if (!$skipValidation && ($invalid = $this->findMissing($groups)) !== []) {
			return $this->fail($output, 'Unknown group(s): ' . implode(', ', $invalid) . '. Pass --skip-validation to set anyway.');
		}
		$this->save($groups);
		$output->writeln('<info>Allowed groups set to: ' . ($groups === [] ? '(none)' : implode(', ', $groups)) . '</info>');
		return 0;
	}

	private function flatten(array $args): array {
		$flat = [];
		foreach ($args as $a) {
			foreach (explode(',', (string)$a) as $piece) {
				$piece = trim($piece);
				if ($piece !== '') {
					$flat[] = $piece;
				}
			}
		}
		return $flat;
	}

	private function findMissing(array $groups): array {
		return array_values(array_filter($groups, fn ($gid) => !$this->groupManager->groupExists($gid)));
	}

	private function getAllowed(): array {
		$json = $this->config->getAppValue(Application::APP_ID, 'allowed_groups', '[]');
		$decoded = json_decode($json, true);
		return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
	}

	private function save(array $groups): void {
		$this->config->setAppValue(Application::APP_ID, 'allowed_groups', json_encode(array_values($groups)));
	}

	private function fail(OutputInterface $output, string $msg): int {
		$output->writeln("<error>$msg</error>");
		return 1;
	}
}
