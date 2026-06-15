<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Controller;

use OCA\StatsCollector\AppInfo\Application;
use OCA\StatsCollector\Service\CollectionService;
use OCA\StatsCollector\Service\SnapshotService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Admin-only controller for the local admin dashboard.
 */
class StatsController extends Controller {
	public function __construct(
		IRequest $request,
		private CollectionService $collectionService,
		private SnapshotService $snapshotService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Get the latest cached snapshot.
	 */
	public function dashboard(): JSONResponse {
		$snapshot = $this->collectionService->getLastSnapshot();
		if ($snapshot === null) {
			return new JSONResponse(['empty' => true]);
		}
		return new JSONResponse($snapshot);
	}

	/**
	 * Collect now and return the fresh payload (also caches it).
	 */
	public function collectNow(): JSONResponse {
		$payload = $this->collectionService->collectAll();
		$this->snapshotService->storeSnapshot($payload);
		return new JSONResponse($payload);
	}

	/**
	 * List historical snapshots within optional date range.
	 */
	public function snapshots(): JSONResponse {
		$from = $this->request->getParam('from');
		$to = $this->request->getParam('to');
		$limit = max(1, min(1000, (int)$this->request->getParam('limit', 100)));
		$includePayload = $this->request->getParam('include_payload') === 'true';

		$snapshots = $this->snapshotService->listSnapshots(
			is_string($from) ? $from : null,
			is_string($to) ? $to : null,
			$limit,
			$includePayload,
		);

		return new JSONResponse($snapshots);
	}
}
