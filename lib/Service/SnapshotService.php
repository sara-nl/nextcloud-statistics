<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Service;

use OCA\StatsCollector\AppInfo\Application;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

/**
 * Manages local snapshot storage, retrieval, and API key authentication
 * for external systems pulling statistics via the public API.
 */
class SnapshotService {
	private const SNAPSHOTS_FOLDER = 'snapshots';

	public function __construct(
		private IAppData $appData,
		private IConfig $config,
		private ICrypto $crypto,
		private LoggerInterface $logger,
	) {
	}

	// --- API Key Management ---

	/**
	 * @return array<array{id: string, key_prefix: string, label: string, created_at: string}>
	 */
	public function getApiKeys(): array {
		$json = $this->config->getAppValue(Application::APP_ID, 'api_keys', '[]');
		$keys = json_decode($json, true);
		return is_array($keys) ? $keys : [];
	}

	/**
	 * Create a new API key. Returns the plaintext key once — never retrievable again.
	 */
	public function createApiKey(string $label): array {
		$keys = $this->getApiKeys();
		$plainKey = bin2hex(random_bytes(32));

		$keyRecord = [
			'id' => 'key_' . bin2hex(random_bytes(8)),
			'key_encrypted' => $this->crypto->encrypt($plainKey),
			'key_prefix' => substr($plainKey, 0, 8),
			'label' => $label,
			'created_at' => (new \DateTimeImmutable())->format('c'),
		];

		$keys[] = $keyRecord;
		$this->saveApiKeys($keys);

		return array_merge($keyRecord, ['key' => $plainKey]);
	}

	public function revokeApiKey(string $keyId): void {
		$keys = $this->getApiKeys();
		$keys = array_values(array_filter($keys, fn ($k) => $k['id'] !== $keyId));
		$this->saveApiKeys($keys);
	}

	/**
	 * Validate an API key with timing-safe comparison.
	 * @return array|null The matching key record, or null if invalid.
	 */
	public function validateApiKey(string $key): ?array {
		foreach ($this->getApiKeys() as $apiKey) {
			if (isset($apiKey['key_encrypted'])) {
				try {
					$decrypted = $this->crypto->decrypt($apiKey['key_encrypted']);
					if (hash_equals($decrypted, $key)) {
						return $apiKey;
					}
				} catch (\Exception $e) {
					continue;
				}
			}
		}
		return null;
	}

	private function saveApiKeys(array $keys): void {
		$this->config->setAppValue(
			Application::APP_ID,
			'api_keys',
			json_encode($keys, JSON_UNESCAPED_SLASHES)
		);
	}

	// --- Snapshot Storage ---

	/**
	 * Persist a snapshot to local appdata. Filename derived from timestamp.
	 */
	public function storeSnapshot(array $payload): void {
		$timestamp = $payload['timestamp'] ?? (new \DateTimeImmutable())->format('c');
		try {
			$date = new \DateTimeImmutable($timestamp);
		} catch (\Exception $e) {
			$date = new \DateTimeImmutable();
		}

		$folder = $this->getOrCreateFolder(self::SNAPSHOTS_FOLDER);
		$filename = 'snapshot_' . $date->format('Y-m-d_H-i-s') . '.json';

		$folder->newFile($filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	/**
	 * List snapshots within a date range, newest first.
	 *
	 * @param string|null $from ISO 8601 timestamp (inclusive)
	 * @param string|null $to ISO 8601 timestamp (inclusive)
	 * @param int $limit Maximum number of snapshots to return (0 = no limit)
	 * @return array<array{timestamp: string, filename: string, payload?: array}>
	 */
	public function listSnapshots(?string $from = null, ?string $to = null, int $limit = 0, bool $includePayload = false): array {
		try {
			$folder = $this->appData->getFolder(self::SNAPSHOTS_FOLDER);
		} catch (NotFoundException $e) {
			return [];
		}

		// Phase 1: collect candidate filenames + filename-derived timestamps without
		// reading any file content. This keeps the light listing path O(directory size)
		// instead of O(N file reads + json_decode).
		$candidates = [];
		foreach ($folder->getDirectoryListing() as $file) {
			$name = $file->getName();
			if (!str_ends_with($name, '.json') || !str_starts_with($name, 'snapshot_')) {
				continue;
			}

			if (!preg_match('/^snapshot_(\d{4})-(\d{2})-(\d{2})_(\d{2})-(\d{2})-(\d{2})\.json$/', $name, $m)) {
				continue;
			}

			$timestamp = sprintf('%s-%s-%sT%s:%s:%s+00:00', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]);
			$dateOnly = $m[1] . '-' . $m[2] . '-' . $m[3];

			if ($from && $dateOnly < substr($from, 0, 10)) {
				continue;
			}
			if ($to && $dateOnly > substr($to, 0, 10)) {
				continue;
			}

			$candidates[] = ['file' => $file, 'name' => $name, 'timestamp' => $timestamp];
		}

		// Sort newest first
		usort($candidates, fn ($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

		// Apply limit early so we do not read more file content than needed
		if ($limit > 0 && count($candidates) > $limit) {
			$candidates = array_slice($candidates, 0, $limit);
		}

		$snapshots = [];
		foreach ($candidates as $c) {
			$entry = [
				'timestamp' => $c['timestamp'],
				'filename' => $c['name'],
			];

			// Only open the file if we actually need its content
			if ($includePayload) {
				try {
					$data = json_decode($c['file']->getContent(), true);
					if (!is_array($data)) {
						continue;
					}
					// Exact range check using the real timestamp from the payload
					if (!empty($data['timestamp'])) {
						$entry['timestamp'] = $data['timestamp'];
						if ($from && $data['timestamp'] < $from) {
							continue;
						}
						if ($to && $data['timestamp'] > $to) {
							continue;
						}
					}
					$entry['payload'] = $data;
				} catch (\Exception $e) {
					continue;
				}
			}

			$snapshots[] = $entry;
		}

		return $snapshots;
	}

	/**
	 * Get the most recent snapshot's full payload.
	 *
	 * Reads only the newest matching file (O(1) file reads) by sorting filenames
	 * lexicographically (filenames are timestamps, so this matches chronological order).
	 */
	public function getLatestSnapshot(): ?array {
		try {
			$folder = $this->appData->getFolder(self::SNAPSHOTS_FOLDER);
		} catch (NotFoundException $e) {
			return null;
		}

		$newestFile = null;
		$newestName = '';
		foreach ($folder->getDirectoryListing() as $file) {
			$name = $file->getName();
			if (!str_ends_with($name, '.json') || !str_starts_with($name, 'snapshot_')) {
				continue;
			}
			if ($name > $newestName) {
				$newestName = $name;
				$newestFile = $file;
			}
		}

		if ($newestFile === null) {
			return null;
		}

		try {
			$data = json_decode($newestFile->getContent(), true);
			return is_array($data) ? $data : null;
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Get a specific snapshot by filename.
	 */
	public function getSnapshot(string $filename): ?array {
		// Sanitize filename to prevent path traversal
		if (!preg_match('/^snapshot_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.json$/', $filename)) {
			return null;
		}
		try {
			$folder = $this->appData->getFolder(self::SNAPSHOTS_FOLDER);
			$file = $folder->getFile($filename);
			$data = json_decode($file->getContent(), true);
			return is_array($data) ? $data : null;
		} catch (NotFoundException $e) {
			return null;
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Delete snapshots older than the given number of days.
	 */
	public function cleanupOlderThan(int $days): int {
		$cutoffDate = (new \DateTimeImmutable('-' . $days . ' days'))->format('Y-m-d');
		$deleted = 0;

		try {
			$folder = $this->appData->getFolder(self::SNAPSHOTS_FOLDER);
		} catch (NotFoundException $e) {
			return 0;
		}

		foreach ($folder->getDirectoryListing() as $file) {
			$name = $file->getName();
			if (!str_ends_with($name, '.json')) {
				continue;
			}
			if (preg_match('/^snapshot_(\d{4}-\d{2}-\d{2})/', $name, $matches)) {
				if ($matches[1] < $cutoffDate) {
					$file->delete();
					$deleted++;
				}
			}
		}

		return $deleted;
	}

	private function getOrCreateFolder(string $name): \OCP\Files\SimpleFS\ISimpleFolder {
		try {
			return $this->appData->getFolder($name);
		} catch (NotFoundException $e) {
			return $this->appData->newFolder($name);
		}
	}
}
