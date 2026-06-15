<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Collector;

use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class FormsCollector implements ICollector {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return 'forms';
	}

	public function getName(): string {
		return 'Forms';
	}

	public function getDescription(): string {
		return 'Form and submission statistics';
	}

	public function getAppId(): string {
		return 'forms';
	}

	public function getIcon(): string {
		return 'icon-forms';
	}

	public function getAvailableMetrics(): array {
		return [
			[
				'id' => 'total_forms',
				'name' => 'Total forms',
				'description' => 'Total number of forms',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'total_submissions',
				'name' => 'Total submissions',
				'description' => 'Total number of form submissions',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'submissions_7d',
				'name' => 'Submissions (7d)',
				'description' => 'Form submissions in the last 7 days',
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
					'total_forms' => $this->countTable('forms_v2_forms'),
					'total_submissions' => $this->countTable('forms_v2_submissions'),
					'submissions_7d' => $this->getRecentSubmissions(24 * 7),
					default => null,
				};
			} catch (\Exception $e) {
				$this->logger->warning('FormsCollector: Failed to collect metric ' . $metricId, [
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

	private function getRecentSubmissions(int $hours): int {
		$cutoff = time() - ($hours * 3600);
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('forms_v2_submissions')
			->where($qb->expr()->gte('timestamp', $qb->createNamedParameter($cutoff)));
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}
}
