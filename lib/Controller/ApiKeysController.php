<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Controller;

use OCA\StatsCollector\AppInfo\Application;
use OCA\StatsCollector\Service\SnapshotService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Admin controller for managing API keys used by external systems
 * to pull snapshots via the public API.
 */
class ApiKeysController extends Controller {
	public function __construct(
		IRequest $request,
		private SnapshotService $snapshotService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	public function listKeys(): JSONResponse {
		$keys = $this->snapshotService->getApiKeys();
		$safe = array_map(function ($k) {
			$preview = ($k['key_prefix'] ?? '????') . '...';
			unset($k['key_encrypted']);
			$k['key_preview'] = $preview;
			return $k;
		}, $keys);
		return new JSONResponse($safe);
	}

	public function createKey(): JSONResponse {
		$label = (string)$this->request->getParam('label', '');
		if ($label === '') {
			return new JSONResponse(['error' => 'Label is required'], Http::STATUS_BAD_REQUEST);
		}
		$key = $this->snapshotService->createApiKey($label);
		return new JSONResponse($key);
	}

	public function revokeKey(string $id): JSONResponse {
		$this->snapshotService->revokeApiKey($id);
		return new JSONResponse(['status' => 'ok']);
	}
}
