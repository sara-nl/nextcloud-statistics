<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Collector;

use OCP\IDBConnection;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

class UsersCollector implements ICollector {
	public function __construct(
		private IDBConnection $db,
		private IGroupManager $groupManager,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return 'users';
	}

	public function getName(): string {
		return 'Users';
	}

	public function getDescription(): string {
		return 'User account statistics';
	}

	public function getAppId(): string {
		return 'core';
	}

	public function getIcon(): string {
		return 'icon-user';
	}

	public function getAvailableMetrics(): array {
		return [
			[
				'id' => 'total_users',
				'name' => 'Total users',
				'description' => 'Total number of user accounts',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'active_users_24h',
				'name' => 'Active users (24h)',
				'description' => 'Users who logged in within the last 24 hours',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'active_users_7d',
				'name' => 'Active users (7d)',
				'description' => 'Users who logged in within the last 7 days',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'active_users_30d',
				'name' => 'Active users (30d)',
				'description' => 'Users who logged in within the last 30 days',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'disabled_users',
				'name' => 'Disabled users',
				'description' => 'Number of disabled user accounts',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'users_per_group',
				'name' => 'Users per group',
				'description' => 'User count per group',
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
					'total_users' => $this->getTotalUsers(),
					'active_users_24h' => $this->getActiveUsers(24),
					'active_users_7d' => $this->getActiveUsers(24 * 7),
					'active_users_30d' => $this->getActiveUsers(24 * 30),
					'disabled_users' => $this->getDisabledUsers(),
					'users_per_group' => $this->getUsersPerGroup(),
					default => null,
				};
			} catch (\Exception $e) {
				$this->logger->warning('UsersCollector: Failed to collect metric ' . $metricId, [
					'exception' => $e,
				]);
				$result[$metricId] = null;
			}
		}

		return $result;
	}

	private function getTotalUsers(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('users');
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getActiveUsers(int $hours): int {
		$cutoff = time() - ($hours * 3600);
		// configvalue is VARCHAR but stores UNIX timestamps as numeric strings.
		// Use explicit CAST to ensure numeric comparison across all DB backends.
		$result = $this->db->executeQuery(
			'SELECT COUNT(*) AS `count` FROM `*PREFIX*preferences`'
			. ' WHERE `appid` = ? AND `configkey` = ? AND CAST(`configvalue` AS UNSIGNED) >= ?',
			['login', 'lastLogin', $cutoff]
		);
		$row = $result->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getDisabledUsers(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('core')))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter('enabled')))
			->andWhere($qb->expr()->eq('configvalue', $qb->createNamedParameter('false')));
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getUsersPerGroup(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('gid', $qb->func()->count('uid', 'count'))
			->from('group_user')
			->groupBy('gid')
			->orderBy('count', 'DESC');
		$rows = $qb->executeQuery()->fetchAll();

		$groups = [];
		foreach ($rows as $row) {
			$groups[$row['gid']] = (int)$row['count'];
		}
		return $groups;
	}

}
