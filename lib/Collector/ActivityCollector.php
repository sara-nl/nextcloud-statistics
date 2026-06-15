<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Collector;

use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class ActivityCollector implements ICollector {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return 'activity';
	}

	public function getName(): string {
		return 'Activity';
	}

	public function getDescription(): string {
		return 'User activity log statistics';
	}

	public function getAppId(): string {
		return 'activity';
	}

	public function getIcon(): string {
		return 'icon-activity';
	}

	public function getAvailableMetrics(): array {
		return [
			[
				'id' => 'activities_24h',
				'name' => 'Activities (24h)',
				'description' => 'Number of activity entries in the last 24 hours',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'activities_7d',
				'name' => 'Activities (7d)',
				'description' => 'Number of activity entries in the last 7 days',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'activities_by_type',
				'name' => 'Activities by type (30d)',
				'description' => 'Distribution of activities by type in the last 30 days',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'most_active_users',
				'name' => 'Most active users',
				'description' => 'Top 10 users by activity count (7d)',
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
					'activities_24h' => $this->getActivitiesSince(24),
					'activities_7d' => $this->getActivitiesSince(24 * 7),
					'activities_by_type' => $this->getActivitiesByType(),
					'most_active_users' => $this->getMostActiveUsers(),
					default => null,
				};
			} catch (\Exception $e) {
				$this->logger->warning('ActivityCollector: Failed to collect metric ' . $metricId, [
					'exception' => $e,
				]);
				$result[$metricId] = null;
			}
		}

		return $result;
	}

	private function getActivitiesSince(int $hours): int {
		$cutoff = time() - ($hours * 3600);
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('activity')
			->where($qb->expr()->gte('timestamp', $qb->createNamedParameter($cutoff)));
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getActivitiesByType(): array {
		$cutoff = time() - (30 * 24 * 3600);
		$qb = $this->db->getQueryBuilder();
		$qb->select('type', $qb->func()->count('*', 'count'))
			->from('activity')
			->where($qb->expr()->gte('timestamp', $qb->createNamedParameter($cutoff)))
			->groupBy('type')
			->orderBy('count', 'DESC')
			->setMaxResults(20);
		$rows = $qb->executeQuery()->fetchAll();

		$types = [];
		foreach ($rows as $row) {
			$types[$row['type']] = (int)$row['count'];
		}
		return $types;
	}

	private function getMostActiveUsers(): array {
		$cutoff = time() - (7 * 24 * 3600);
		$qb = $this->db->getQueryBuilder();
		$qb->select('user', $qb->func()->count('*', 'count'))
			->from('activity')
			->where($qb->expr()->gte('timestamp', $qb->createNamedParameter($cutoff)))
			->groupBy('user')
			->orderBy('count', 'DESC')
			->setMaxResults(10);
		$rows = $qb->executeQuery()->fetchAll();

		$users = [];
		foreach ($rows as $row) {
			$users[$row['user']] = (int)$row['count'];
		}
		return $users;
	}
}
