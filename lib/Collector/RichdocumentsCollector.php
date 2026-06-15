<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Collector;

use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class RichdocumentsCollector implements ICollector {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return 'richdocuments';
	}

	public function getName(): string {
		return 'Collabora Online';
	}

	public function getDescription(): string {
		return 'Collabora Online / Richdocuments usage statistics';
	}

	public function getAppId(): string {
		return 'richdocuments';
	}

	public function getIcon(): string {
		return 'icon-office';
	}

	public function getAvailableMetrics(): array {
		return [
			[
				'id' => 'active_sessions',
				'name' => 'Active sessions',
				'description' => 'Currently active WOPI editing sessions (tokens not yet expired)',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'sessions_24h',
				'name' => 'Sessions (24h)',
				'description' => 'Editing sessions opened in the last 24 hours',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'sessions_7d',
				'name' => 'Sessions (7d)',
				'description' => 'Editing sessions opened in the last 7 days',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'unique_users_7d',
				'name' => 'Unique users (7d)',
				'description' => 'Distinct users who opened documents in the last 7 days',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'unique_documents_7d',
				'name' => 'Unique documents (7d)',
				'description' => 'Distinct documents opened in the last 7 days',
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
					'active_sessions' => $this->getActiveSessions(),
					'sessions_24h' => $this->getSessionsSince(1),
					'sessions_7d' => $this->getSessionsSince(7),
					'unique_users_7d' => $this->getUniqueUsers(7),
					'unique_documents_7d' => $this->getUniqueDocuments(7),
					default => null,
				};
			} catch (\Exception $e) {
				$this->logger->warning('RichdocumentsCollector: Failed to collect metric ' . $metricId, [
					'exception' => $e,
				]);
				$result[$metricId] = null;
			}
		}

		return $result;
	}

	/**
	 * Filter for real document sessions only (token_type=0, fileid>0).
	 * Excludes internal Collabora tokens (font loading, discovery, etc.)
	 */
	private function addDocumentSessionFilter($qb): void {
		$qb->andWhere($qb->expr()->eq('token_type', $qb->createNamedParameter(0)))
			->andWhere($qb->expr()->gt('fileid', $qb->createNamedParameter(0)));
	}

	private function getActiveSessions(): int {
		$now = time();
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('richdocuments_wopi')
			->where($qb->expr()->gt('expiry', $qb->createNamedParameter($now)));
		$this->addDocumentSessionFilter($qb);
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getSessionsSince(int $days): int {
		$since = time() - ($days * 86400);
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('richdocuments_wopi')
			->where($qb->expr()->gt('expiry', $qb->createNamedParameter($since)));
		$this->addDocumentSessionFilter($qb);
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getUniqueUsers(int $days): int {
		$since = time() - ($days * 86400);
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->count($qb->createFunction('DISTINCT ' . $qb->getColumnName('owner_uid'))), 'count')
			->from('richdocuments_wopi')
			->where($qb->expr()->gt('expiry', $qb->createNamedParameter($since)))
			->andWhere($qb->expr()->isNotNull('owner_uid'))
			->andWhere($qb->expr()->neq('owner_uid', $qb->createNamedParameter('')));
		$this->addDocumentSessionFilter($qb);
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getUniqueDocuments(int $days): int {
		$since = time() - ($days * 86400);
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->count($qb->createFunction('DISTINCT ' . $qb->getColumnName('fileid'))), 'count')
			->from('richdocuments_wopi')
			->where($qb->expr()->gt('expiry', $qb->createNamedParameter($since)));
		$this->addDocumentSessionFilter($qb);
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}
}
