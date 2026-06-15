<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Collector;

use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class TalkCollector implements ICollector {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return 'talk';
	}

	public function getName(): string {
		return 'Talk';
	}

	public function getDescription(): string {
		return 'Chat and video call statistics';
	}

	public function getAppId(): string {
		return 'spreed';
	}

	public function getIcon(): string {
		return 'icon-talk';
	}

	public function getAvailableMetrics(): array {
		return [
			[
				'id' => 'total_rooms',
				'name' => 'Total rooms',
				'description' => 'Total number of Talk conversations',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'active_rooms_24h',
				'name' => 'Active rooms (24h)',
				'description' => 'Rooms with activity in the last 24 hours',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'active_rooms_7d',
				'name' => 'Active rooms (7d)',
				'description' => 'Rooms with activity in the last 7 days',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'total_messages_24h',
				'name' => 'Messages (24h)',
				'description' => 'Messages sent in the last 24 hours',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'total_messages_7d',
				'name' => 'Messages (7d)',
				'description' => 'Messages sent in the last 7 days',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'rooms_by_type',
				'name' => 'Rooms by type',
				'description' => 'Distribution of room types (1-on-1, group, public)',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'total_participants',
				'name' => 'Total participants',
				'description' => 'Unique participants across all rooms',
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
					'total_rooms' => $this->countTable('talk_rooms'),
					'active_rooms_24h' => $this->getActiveRooms(24),
					'active_rooms_7d' => $this->getActiveRooms(24 * 7),
					'total_messages_24h' => $this->getMessageCount(24),
					'total_messages_7d' => $this->getMessageCount(24 * 7),
					'rooms_by_type' => $this->getRoomsByType(),
					'total_participants' => $this->getTotalParticipants(),
					default => null,
				};
			} catch (\Exception $e) {
				$this->logger->warning('TalkCollector: Failed to collect metric ' . $metricId, [
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

	private function getActiveRooms(int $hours): int {
		$cutoff = new \DateTimeImmutable('-' . $hours . ' hours');
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('talk_rooms')
			->where($qb->expr()->gte('last_activity', $qb->createNamedParameter(
				$cutoff,
				'datetime_immutable'
			)));
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getMessageCount(int $hours): int {
		$cutoff = new \DateTimeImmutable('-' . $hours . ' hours');
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('comments')
			->where($qb->expr()->eq('object_type', $qb->createNamedParameter('chat')))
			->andWhere($qb->expr()->eq('verb', $qb->createNamedParameter('comment')))
			->andWhere($qb->expr()->gte('creation_timestamp', $qb->createNamedParameter(
				$cutoff,
				'datetime_immutable'
			)));
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getRoomsByType(): array {
		// See: https://nextcloud-talk.readthedocs.io/en/latest/constants/#conversation-types
		$typeNames = [
			1 => 'one_to_one',
			2 => 'group',
			3 => 'public',
			4 => 'changelog',
			5 => 'former_one_to_one',
			6 => 'note_to_self',
		];

		$qb = $this->db->getQueryBuilder();
		$qb->select('type', $qb->func()->count('*', 'count'))
			->from('talk_rooms')
			->groupBy('type');
		$rows = $qb->executeQuery()->fetchAll();

		$result = [];
		foreach ($rows as $row) {
			$type = (int)$row['type'];
			$name = $typeNames[$type] ?? 'type_' . $type;
			$result[$name] = (int)$row['count'];
		}
		return $result;
	}

	private function getTotalParticipants(): int {
		$result = $this->db->executeQuery(
			'SELECT COUNT(DISTINCT `actor_id`) AS `count` FROM `*PREFIX*talk_attendees` WHERE `actor_type` = ?',
			['users']
		);
		$row = $result->fetch();
		$result->closeCursor();
		return (int)($row['count'] ?? 0);
	}
}
