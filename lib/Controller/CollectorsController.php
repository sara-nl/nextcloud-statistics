<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Controller;

use OCA\StatsCollector\AppInfo\Application;
use OCA\StatsCollector\Collector\CollectorRegistry;
use OCA\StatsCollector\Service\CollectionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class CollectorsController extends Controller {
	public function __construct(
		IRequest $request,
		private CollectorRegistry $registry,
		private CollectionService $collectionService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	public function index(): JSONResponse {
		$collectorsInfo = $this->registry->getCollectorsInfo();
		$enabledMetrics = $this->collectionService->getEnabledMetrics();

		foreach ($collectorsInfo as &$info) {
			$info['enabled_metrics'] = $enabledMetrics[$info['id']] ?? [];
		}

		return new JSONResponse($collectorsInfo);
	}

	public function updateMetrics(string $id): JSONResponse {
		$metricIds = $this->request->getParam('metrics', []);

		// Validate: only allow known metric IDs
		$collector = $this->registry->get($id);
		if ($collector === null) {
			return new JSONResponse(['error' => 'Collector not found or not available'], 404);
		}

		$validIds = array_map(
			fn (array $m) => $m['id'],
			$collector->getAvailableMetrics()
		);
		$metricIds = array_values(array_intersect($metricIds, $validIds));

		$allEnabled = $this->collectionService->getEnabledMetrics();
		$allEnabled[$id] = $metricIds;
		$this->collectionService->setEnabledMetrics($allEnabled);

		return new JSONResponse(['status' => 'ok', 'enabled_metrics' => $metricIds]);
	}
}
