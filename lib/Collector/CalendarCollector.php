<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Collector;

use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class CalendarCollector implements ICollector {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return 'calendar';
	}

	public function getName(): string {
		return 'Calendar';
	}

	public function getDescription(): string {
		return 'Calendar and event statistics';
	}

	public function getAppId(): string {
		return 'dav';
	}

	public function getIcon(): string {
		return 'icon-calendar';
	}

	public function getAvailableMetrics(): array {
		return [
			[
				'id' => 'total_calendars',
				'name' => 'Total calendars',
				'description' => 'Total number of calendars',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'total_events',
				'name' => 'Total events',
				'description' => 'Total number of calendar events',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'events_upcoming_7d',
				'name' => 'Upcoming events (7d)',
				'description' => 'Events in the next 7 days',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'calendars_shared',
				'name' => 'Shared calendars',
				'description' => 'Number of calendars that are shared',
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
					'total_calendars' => $this->countTable('calendars'),
					'total_events' => $this->countTable('calendarobjects'),
					'events_upcoming_7d' => $this->getUpcomingEvents(7),
					'calendars_shared' => $this->getSharedCalendars(),
					default => null,
				};
			} catch (\Exception $e) {
				$this->logger->warning('CalendarCollector: Failed to collect metric ' . $metricId, [
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

	private function getUpcomingEvents(int $days): int {
		$now = time();
		$future = $now + ($days * 86400);

		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('calendarobjects')
			->where($qb->expr()->isNotNull('firstoccurence'))
			->andWhere($qb->expr()->gte('firstoccurence', $qb->createNamedParameter($now)))
			->andWhere($qb->expr()->lte('firstoccurence', $qb->createNamedParameter($future)));
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getSharedCalendars(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('dav_shares')
			->where($qb->expr()->eq('type', $qb->createNamedParameter('calendar')));
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}
}
