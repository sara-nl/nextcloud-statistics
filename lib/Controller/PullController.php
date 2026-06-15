<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Controller;

use OCA\StatsCollector\AppInfo\Application;
use OCA\StatsCollector\Service\SnapshotService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\ISession;

/**
 * Plain REST API for external systems to pull statistics snapshots.
 * Authenticated via Bearer token (API key).
 *
 * Returns JSON directly (no OCS wrapper).
 */
class PullController extends Controller {
	private const BFP_ACTION = 'stats_collector_pull';

	public function __construct(
		IRequest $request,
		private SnapshotService $snapshotService,
		private IConfig $config,
		private ISession $session,
	) {
		parent::__construct(Application::APP_ID, $request);
		// Stateless API: do not persist sessions on every authenticated call.
		$this->session->close();
	}

	/**
	 * GET /apps/stats_collector/api/v1/snapshots
	 *
	 * Query params:
	 *   - from: ISO 8601 timestamp (inclusive)
	 *   - to: ISO 8601 timestamp (inclusive)
	 *   - limit: max number of entries (default 100, max 1000)
	 *   - include_payload: any truthy value to inline the full snapshot data
	 */
	#[PublicPage]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: self::BFP_ACTION)]
	public function listSnapshots(): JSONResponse {
		if (($auth = $this->authenticate()) instanceof JSONResponse) {
			return $auth;
		}

		// Strict input validation
		$rawLimit = $this->request->getParam('limit', '100');
		if (!is_string($rawLimit) || !ctype_digit($rawLimit)) {
			return new JSONResponse(
				['error' => 'limit must be a non-negative integer'],
				Http::STATUS_BAD_REQUEST,
			);
		}
		$limit = max(1, min(1000, (int)$rawLimit));

		$from = $this->validateTimestamp('from');
		if ($from instanceof JSONResponse) {
			return $from;
		}

		$to = $this->validateTimestamp('to');
		if ($to instanceof JSONResponse) {
			return $to;
		}

		$includePayload = filter_var(
			$this->request->getParam('include_payload', false),
			FILTER_VALIDATE_BOOLEAN,
		);

		$snapshots = $this->snapshotService->listSnapshots($from, $to, $limit, $includePayload);

		return new JSONResponse([
			'instance_id' => $this->config->getSystemValue('instanceid', ''),
			'instance_label' => $this->config->getAppValue(Application::APP_ID, 'instance_label', ''),
			'count' => count($snapshots),
			'snapshots' => $snapshots,
		]);
	}

	/**
	 * GET /apps/stats_collector/api/v1/snapshots/latest
	 */
	#[PublicPage]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: self::BFP_ACTION)]
	public function latestSnapshot(): JSONResponse {
		if (($auth = $this->authenticate()) instanceof JSONResponse) {
			return $auth;
		}

		$snapshot = $this->snapshotService->getLatestSnapshot();
		if ($snapshot === null) {
			return new JSONResponse(['error' => 'No snapshots available'], Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse($snapshot);
	}

	/**
	 * GET /apps/stats_collector/api/v1/snapshots/{filename}
	 */
	#[PublicPage]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: self::BFP_ACTION)]
	public function getSnapshot(string $filename): JSONResponse {
		if (($auth = $this->authenticate()) instanceof JSONResponse) {
			return $auth;
		}

		$snapshot = $this->snapshotService->getSnapshot($filename);
		if ($snapshot === null) {
			return new JSONResponse(['error' => 'Snapshot not found'], Http::STATUS_NOT_FOUND);
		}

		$response = new JSONResponse($snapshot);
		// Snapshots are immutable once written; allow private caching by clients.
		if (!empty($snapshot['timestamp'])) {
			$etag = '"' . md5($filename . ($snapshot['timestamp'] ?? '')) . '"';
			$ifNoneMatch = $this->request->getHeader('If-None-Match');
			if ($ifNoneMatch === $etag) {
				return new JSONResponse(null, Http::STATUS_NOT_MODIFIED);
			}
			$response->addHeader('ETag', $etag);
			$response->addHeader('Cache-Control', 'private, max-age=86400, immutable');
		}
		return $response;
	}

	private function validateTimestamp(string $param): string|JSONResponse|null {
		$value = $this->request->getParam($param);
		if ($value === null || $value === '') {
			return null;
		}
		if (!is_string($value)) {
			return new JSONResponse(
				['error' => "$param must be a string"],
				Http::STATUS_BAD_REQUEST,
			);
		}
		// Accept full ISO 8601 or just a date prefix (YYYY-MM-DD).
		$accepted = false;
		foreach ([\DateTimeInterface::ATOM, 'Y-m-d\TH:i:sP', 'Y-m-d H:i:s', 'Y-m-d'] as $fmt) {
			if (\DateTimeImmutable::createFromFormat($fmt, $value) !== false) {
				$accepted = true;
				break;
			}
		}
		if (!$accepted) {
			return new JSONResponse(
				['error' => "$param must be ISO 8601 (YYYY-MM-DD or full timestamp)"],
				Http::STATUS_BAD_REQUEST,
			);
		}
		return $value;
	}

	private function authenticate(): ?JSONResponse {
		$authHeader = (string)$this->request->getHeader('Authorization');
		if (!str_starts_with($authHeader, 'Bearer ')) {
			$response = new JSONResponse(['error' => 'Missing Bearer token'], Http::STATUS_UNAUTHORIZED);
			$response->throttle(['action' => self::BFP_ACTION]);
			return $response;
		}
		$key = trim(substr($authHeader, 7));
		// Reject obviously-malformed keys without paying the decrypt cost.
		if (strlen($key) !== 64 || !ctype_xdigit($key) || $this->snapshotService->validateApiKey($key) === null) {
			$response = new JSONResponse(['error' => 'Invalid API key'], Http::STATUS_UNAUTHORIZED);
			$response->throttle(['action' => self::BFP_ACTION]);
			return $response;
		}
		return null;
	}
}
