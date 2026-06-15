<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Collector;

use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class FilesCollector implements ICollector {
	private ?int $dirMimeId = null;

	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return 'files';
	}

	public function getName(): string {
		return 'Files';
	}

	public function getDescription(): string {
		return 'File storage statistics';
	}

	public function getAppId(): string {
		return 'core';
	}

	public function getIcon(): string {
		return 'icon-folder';
	}

	public function getAvailableMetrics(): array {
		return [
			[
				'id' => 'total_files',
				'name' => 'Total files',
				'description' => 'Total number of user files',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'total_storage_bytes',
				'name' => 'Total storage',
				'description' => 'Total storage used by users in bytes',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'storage_per_user_avg',
				'name' => 'Average storage per user',
				'description' => 'Average total storage per user in bytes',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'files_created_24h',
				'name' => 'New files (24h)',
				'description' => 'Files created in the last 24 hours',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'mimetypes_distribution',
				'name' => 'File type distribution',
				'description' => 'Top 10 file types with counts',
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
					'total_files' => $this->getTotalFiles(),
					'total_storage_bytes' => $this->getTotalStorage(),
					'storage_per_user_avg' => $this->getAverageStoragePerUser(),
					'files_created_24h' => $this->getFilesCreatedSince(24),
					'mimetypes_distribution' => $this->getMimetypeDistribution(),
					default => null,
				};
			} catch (\Exception $e) {
				$this->logger->warning('FilesCollector: Failed to collect metric ' . $metricId, [
					'exception' => $e,
				]);
				$result[$metricId] = null;
			}
		}

		return $result;
	}

	/**
	 * Count user files only (home:: storages, exclude directories).
	 */
	private function getTotalFiles(): int {
		$result = $this->db->executeQuery(
			'SELECT COUNT(*) AS `count` FROM `*PREFIX*filecache` `fc`'
			. ' INNER JOIN `*PREFIX*storages` `s` ON `fc`.`storage` = `s`.`numeric_id`'
			. ' WHERE `s`.`id` LIKE ? AND `fc`.`mimetype` != ?',
			['home::%', $this->getDirMimeId()]
		);
		$row = $result->fetch();
		return (int)($row['count'] ?? 0);
	}

	/**
	 * Sum storage used by user files only (home:: storages).
	 */
	private function getTotalStorage(): int {
		$result = $this->db->executeQuery(
			'SELECT SUM(`fc`.`size`) AS `total` FROM `*PREFIX*filecache` `fc`'
			. ' INNER JOIN `*PREFIX*storages` `s` ON `fc`.`storage` = `s`.`numeric_id`'
			. ' WHERE `s`.`id` LIKE ? AND `fc`.`mimetype` != ? AND `fc`.`size` > 0',
			['home::%', $this->getDirMimeId()]
		);
		$row = $result->fetch();
		return (int)($row['total'] ?? 0);
	}

	/**
	 * Average total storage per user.
	 * Sums file sizes per home storage, then averages across users.
	 */
	private function getAverageStoragePerUser(): int {
		$result = $this->db->executeQuery(
			'SELECT AVG(`user_total`) AS `avg_size` FROM ('
			. '  SELECT SUM(`fc`.`size`) AS `user_total`'
			. '  FROM `*PREFIX*filecache` `fc`'
			. '  INNER JOIN `*PREFIX*storages` `s` ON `fc`.`storage` = `s`.`numeric_id`'
			. '  WHERE `s`.`id` LIKE ? AND `fc`.`mimetype` != ? AND `fc`.`size` > 0'
			. '  GROUP BY `s`.`numeric_id`'
			. ') `per_user`',
			['home::%', $this->getDirMimeId()]
		);
		$row = $result->fetch();
		return (int)($row['avg_size'] ?? 0);
	}

	/**
	 * Files created in the last N hours (home:: storages only).
	 */
	private function getFilesCreatedSince(int $hours): int {
		$cutoff = time() - ($hours * 3600);
		$result = $this->db->executeQuery(
			'SELECT COUNT(*) AS `count` FROM `*PREFIX*filecache` `fc`'
			. ' INNER JOIN `*PREFIX*storages` `s` ON `fc`.`storage` = `s`.`numeric_id`'
			. ' WHERE `s`.`id` LIKE ? AND `fc`.`mimetype` != ? AND `fc`.`storage_mtime` >= ?',
			['home::%', $this->getDirMimeId(), $cutoff]
		);
		$row = $result->fetch();
		return (int)($row['count'] ?? 0);
	}

	/**
	 * Top 10 file types by count (home:: storages only).
	 */
	private function getMimetypeDistribution(): array {
		$result = $this->db->executeQuery(
			'SELECT `m`.`mimetype`, COUNT(`fc`.`fileid`) AS `count`'
			. ' FROM `*PREFIX*filecache` `fc`'
			. ' INNER JOIN `*PREFIX*mimetypes` `m` ON `fc`.`mimetype` = `m`.`id`'
			. ' INNER JOIN `*PREFIX*storages` `s` ON `fc`.`storage` = `s`.`numeric_id`'
			. ' WHERE `s`.`id` LIKE ? AND `m`.`mimetype` != ?'
			. ' GROUP BY `m`.`mimetype`'
			. ' ORDER BY `count` DESC'
			. ' LIMIT 10',
			['home::%', 'httpd/unix-directory']
		);
		$rows = $result->fetchAll();

		$distribution = [];
		foreach ($rows as $row) {
			$distribution[$row['mimetype']] = (int)$row['count'];
		}
		return $distribution;
	}

	/**
	 * Get the mimetype ID for directories (cached).
	 */
	private function getDirMimeId(): int {
		if ($this->dirMimeId === null) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id')
				->from('mimetypes')
				->where($qb->expr()->eq('mimetype', $qb->createNamedParameter('httpd/unix-directory')));
			$result = $qb->executeQuery()->fetchOne();
			$this->dirMimeId = (int)($result ?? 0);
		}
		return $this->dirMimeId;
	}
}
