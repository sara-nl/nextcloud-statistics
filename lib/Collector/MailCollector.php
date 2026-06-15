<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Collector;

use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class MailCollector implements ICollector {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return 'mail';
	}

	public function getName(): string {
		return 'Mail';
	}

	public function getDescription(): string {
		return 'Email account and message statistics';
	}

	public function getAppId(): string {
		return 'mail';
	}

	public function getIcon(): string {
		return 'icon-mail';
	}

	public function getAvailableMetrics(): array {
		return [
			[
				'id' => 'total_accounts',
				'name' => 'Mail accounts',
				'description' => 'Total number of configured mail accounts',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'total_mailboxes',
				'name' => 'Mailboxes',
				'description' => 'Total number of mailboxes',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'messages_synced',
				'name' => 'Synced messages',
				'description' => 'Total number of synced email messages',
				'type' => 'gauge',
				'method' => 'db',
			],
		];
	}

	public function collect(array $enabledMetricIds): array {
		$result = [];

		foreach ($enabledMetricIds as $metricId) {
			try {
				$result[$metricId] = match ($metricId) {
					'total_accounts' => $this->countTable('mail_accounts'),
					'total_mailboxes' => $this->countTable('mail_mailboxes'),
					'messages_synced' => $this->countTable('mail_messages'),
					default => null,
				};
			} catch (\Exception $e) {
				$this->logger->warning('MailCollector: Failed to collect metric ' . $metricId, [
					'exception' => $e,
				]);
				$result[$metricId] = null;
			}
		}

		return $result;
	}

	private function countTable(string $table): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($table);
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}
}
