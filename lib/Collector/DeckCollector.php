<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Collector;

use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class DeckCollector implements ICollector {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return 'deck';
	}

	public function getName(): string {
		return 'Deck';
	}

	public function getDescription(): string {
		return 'Kanban board and card statistics';
	}

	public function getAppId(): string {
		return 'deck';
	}

	public function getIcon(): string {
		return 'icon-deck';
	}

	public function getAvailableMetrics(): array {
		return [
			[
				'id' => 'total_boards',
				'name' => 'Total boards',
				'description' => 'Total number of Deck boards',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'total_cards',
				'name' => 'Total cards',
				'description' => 'Total number of cards',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'cards_overdue',
				'name' => 'Overdue cards',
				'description' => 'Cards past their due date',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'cards_created_7d',
				'name' => 'New cards (7d)',
				'description' => 'Cards created in the last 7 days',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'cards_by_label',
				'name' => 'Cards by label',
				'description' => 'Distribution of cards across labels',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'active_boards_7d',
				'name' => 'Active boards (7d)',
				'description' => 'Boards modified in the last 7 days',
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
					'total_boards' => $this->countTable('deck_boards'),
					'total_cards' => $this->countTable('deck_cards'),
					'cards_overdue' => $this->getOverdueCards(),
					'cards_created_7d' => $this->getCardsCreatedSince(24 * 7),
					'cards_by_label' => $this->getCardsByLabel(),
					'active_boards_7d' => $this->getActiveBoardsSince(24 * 7),
					default => null,
				};
			} catch (\Exception $e) {
				$this->logger->warning('DeckCollector: Failed to collect metric ' . $metricId, [
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
			->from($table)
			->where($qb->expr()->eq('deleted_at', $qb->createNamedParameter(0)));
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getOverdueCards(): int {
		$now = new \DateTimeImmutable();
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('deck_cards')
			->where($qb->expr()->lt('duedate', $qb->createNamedParameter(
				$now,
				'datetime_immutable'
			)))
			->andWhere($qb->expr()->isNotNull('duedate'))
			->andWhere($qb->expr()->eq('deleted_at', $qb->createNamedParameter(0)));
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getCardsCreatedSince(int $hours): int {
		$cutoff = time() - ($hours * 3600);
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('deck_cards')
			->where($qb->expr()->gte('created_at', $qb->createNamedParameter($cutoff)))
			->andWhere($qb->expr()->eq('deleted_at', $qb->createNamedParameter(0)));
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getCardsByLabel(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('l.title', $qb->func()->count('cl.card_id', 'count'))
			->from('deck_assigned_labels', 'cl')
			->innerJoin('cl', 'deck_labels', 'l', $qb->expr()->eq('cl.label_id', 'l.id'))
			->groupBy('l.title')
			->orderBy('count', 'DESC');
		$rows = $qb->executeQuery()->fetchAll();

		$labels = [];
		foreach ($rows as $row) {
			$labels[$row['title']] = (int)$row['count'];
		}
		return $labels;
	}

	private function getActiveBoardsSince(int $hours): int {
		$cutoff = time() - ($hours * 3600);
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('deck_boards')
			->where($qb->expr()->gte('last_modified', $qb->createNamedParameter($cutoff)))
			->andWhere($qb->expr()->eq('deleted_at', $qb->createNamedParameter(0)));
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}
}
