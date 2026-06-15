<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Collector;

use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class SystemCollector implements ICollector {
	public function __construct(
		private IConfig $config,
		private IDBConnection $db,
		private IAppManager $appManager,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return 'system';
	}

	public function getName(): string {
		return 'System';
	}

	public function getDescription(): string {
		return 'Nextcloud system information';
	}

	public function getAppId(): string {
		return 'core';
	}

	public function getIcon(): string {
		return 'icon-settings';
	}

	public function getAvailableMetrics(): array {
		return [
			[
				'id' => 'nc_version',
				'name' => 'Nextcloud version',
				'description' => 'Installed Nextcloud version',
				'type' => 'gauge',
				'method' => 'api',
			],
			[
				'id' => 'php_version',
				'name' => 'PHP version',
				'description' => 'Running PHP version',
				'type' => 'gauge',
				'method' => 'api',
			],
			[
				'id' => 'db_type',
				'name' => 'Database type',
				'description' => 'Database engine (MySQL, PostgreSQL, SQLite)',
				'type' => 'gauge',
				'method' => 'api',
			],
			[
				'id' => 'db_size_bytes',
				'name' => 'Database size',
				'description' => 'Total database size in bytes',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'installed_apps',
				'name' => 'Installed apps',
				'description' => 'List of installed and enabled apps with versions',
				'type' => 'gauge',
				'method' => 'api',
			],
			[
				'id' => 'free_disk_space',
				'name' => 'Free disk space',
				'description' => 'Available disk space in bytes',
				'type' => 'gauge',
				'method' => 'api',
			],
		];
	}

	public function collect(array $enabledMetricIds): array {
		$result = [];

		foreach ($enabledMetricIds as $metricId) {
			try {
				$result[$metricId] = match ($metricId) {
					'nc_version' => $this->config->getSystemValueString('version', ''),
					'php_version' => PHP_VERSION,
					'db_type' => $this->config->getSystemValueString('dbtype', 'unknown'),
					'db_size_bytes' => $this->getDatabaseSize(),
					'installed_apps' => $this->getInstalledApps(),
					'free_disk_space' => $this->getFreeDiskSpace(),
					default => null,
				};
			} catch (\Exception $e) {
				$this->logger->warning('SystemCollector: Failed to collect metric ' . $metricId, [
					'exception' => $e,
				]);
				$result[$metricId] = null;
			}
		}

		return $result;
	}

	private function getDatabaseSize(): ?int {
		$dbType = $this->config->getSystemValueString('dbtype', '');
		$dbName = $this->config->getSystemValueString('dbname', '');

		try {
			if ($dbType === 'mysql') {
				$qb = $this->db->getQueryBuilder();
				$result = $this->db->executeQuery(
					'SELECT SUM(data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = ?',
					[$dbName]
				);
				$row = $result->fetch();
				return (int)($row['size'] ?? 0);
			} elseif ($dbType === 'pgsql') {
				$result = $this->db->executeQuery(
					'SELECT pg_database_size(?) AS size',
					[$dbName]
				);
				$row = $result->fetch();
				return (int)($row['size'] ?? 0);
			}
		} catch (\Exception $e) {
			$this->logger->warning('SystemCollector: Could not determine database size', [
				'exception' => $e,
			]);
		}

		return null;
	}

	private function getInstalledApps(): array {
		$apps = [];
		foreach ($this->appManager->getInstalledApps() as $appId) {
			if ($this->appManager->isEnabledForUser($appId)) {
				$apps[$appId] = $this->appManager->getAppVersion($appId);
			}
		}
		return $apps;
	}

	private function getFreeDiskSpace(): ?int {
		$dataDir = $this->config->getSystemValueString('datadirectory', '');
		if (empty($dataDir)) {
			return null;
		}
		$space = disk_free_space($dataDir);
		return $space !== false ? (int)$space : null;
	}
}
