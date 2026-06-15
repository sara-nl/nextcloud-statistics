<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Collector;

use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class SharesCollector implements ICollector {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return 'shares';
	}

	public function getName(): string {
		return 'Shares';
	}

	public function getDescription(): string {
		return 'File and folder sharing statistics';
	}

	public function getAppId(): string {
		return 'core';
	}

	public function getIcon(): string {
		return 'icon-share';
	}

	public function getAvailableMetrics(): array {
		return [
			[
				'id' => 'shares_total',
				'name' => 'Total shares',
				'description' => 'Total number of shares',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'shares_user',
				'name' => 'User shares',
				'description' => 'Shares with specific users (share_type = 0)',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'shares_group',
				'name' => 'Group shares',
				'description' => 'Shares with groups (share_type = 1)',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'shares_link',
				'name' => 'Link shares',
				'description' => 'Public link shares (share_type = 3)',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'shares_federated',
				'name' => 'Federated shares',
				'description' => 'Federated (remote) shares (share_type = 6)',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'shares_email',
				'name' => 'Email shares',
				'description' => 'Shares via email (share_type = 4)',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'shares_circle',
				'name' => 'Circle/Team shares',
				'description' => 'Shares with circles/teams (share_type = 7)',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'shares_room',
				'name' => 'Room shares',
				'description' => 'Shares via Talk rooms (share_type = 10)',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'shares_deck',
				'name' => 'Deck shares',
				'description' => 'Shares via Deck cards (share_type = 12)',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'shares_created_24h',
				'name' => 'New shares (24h)',
				'description' => 'Shares created in the last 24 hours',
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
					'shares_total' => $this->getShareCount(),
					'shares_user' => $this->getShareCountByType(0),
					'shares_group' => $this->getShareCountByType(1),
					'shares_link' => $this->getShareCountByType(3),
					'shares_federated' => $this->getShareCountByType(6),
					'shares_email' => $this->getShareCountByType(4),
					'shares_circle' => $this->getShareCountByType(7),
					'shares_room' => $this->getShareCountByType(10),
					'shares_deck' => $this->getShareCountByType(12),
					'shares_created_24h' => $this->getSharesCreatedSince(24),
					default => null,
				};
			} catch (\Exception $e) {
				$this->logger->warning('SharesCollector: Failed to collect metric ' . $metricId, [
					'exception' => $e,
				]);
				$result[$metricId] = null;
			}
		}

		return $result;
	}

	private function getShareCount(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('share');
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getShareCountByType(int $shareType): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter($shareType)));
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getSharesCreatedSince(int $hours): int {
		$cutoff = new \DateTimeImmutable('-' . $hours . ' hours');
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('share')
			->where($qb->expr()->gte('stime', $qb->createNamedParameter($cutoff->getTimestamp())));
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}
}
