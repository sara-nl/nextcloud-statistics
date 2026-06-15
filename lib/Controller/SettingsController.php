<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Controller;

use OCA\StatsCollector\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;

class SettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private IConfig $config,
		private IClientService $clientService,
		private IGroupManager $groupManager,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Search groups by name for the admin UI.
	 */
	public function searchGroups(): JSONResponse {
		$search = (string)$this->request->getParam('search', '');
		$groups = $this->groupManager->search($search, 25);
		$results = array_map(fn ($g) => [
			'id' => $g->getGID(),
			'displayName' => $g->getDisplayName(),
		], $groups);
		return new JSONResponse(array_values($results));
	}

	public function get(): JSONResponse {
		$appId = Application::APP_ID;

		return new JSONResponse([
			'cron_interval' => $this->config->getAppValue($appId, 'cron_interval', 'hourly'),
			'instance_label' => $this->config->getAppValue($appId, 'instance_label', ''),
			'snapshot_retention_days' => $this->config->getAppValue($appId, 'snapshot_retention_days', '90'),
			'chart_logo_url' => $this->config->getAppValue($appId, 'chart_logo_url', ''),
			'chart_font' => $this->config->getAppValue($appId, 'chart_font', ''),
			'chart_colors' => json_decode($this->config->getAppValue($appId, 'chart_colors', '[]'), true) ?: [],
			'allowed_groups' => json_decode($this->config->getAppValue($appId, 'allowed_groups', '[]'), true) ?: [],
		]);
	}

	public function update(): JSONResponse {
		$appId = Application::APP_ID;

		$fields = [
			'cron_interval',
			'instance_label',
			'snapshot_retention_days',
			'chart_logo_url',
			'chart_font',
		];

		$allowlists = [
			'cron_interval' => ['5min', '15min', 'hourly', 'daily', 'weekly'],
		];

		foreach ($fields as $field) {
			$value = $this->request->getParam($field);
			if ($value !== null) {
				if (isset($allowlists[$field]) && !in_array($value, $allowlists[$field], true)) {
					continue;
				}
				$this->config->setAppValue($appId, $field, (string)$value);
			}
		}

		// chart_colors is stored as JSON array
		$chartColors = $this->request->getParam('chart_colors');
		if ($chartColors !== null) {
			$this->config->setAppValue($appId, 'chart_colors', json_encode(
				is_array($chartColors) ? $chartColors : []
			));
		}

		// allowed_groups is stored as JSON array of group IDs
		$allowedGroups = $this->request->getParam('allowed_groups');
		if ($allowedGroups !== null) {
			$this->config->setAppValue($appId, 'allowed_groups', json_encode(
				is_array($allowedGroups) ? $allowedGroups : []
			));
		}

		return new JSONResponse(['status' => 'ok']);
	}

	/**
	 * Proxy the configured chart logo to avoid CORS issues.
	 */
	public function proxyLogo(): DataDownloadResponse {
		$url = $this->config->getAppValue(Application::APP_ID, 'chart_logo_url', '');
		if ($url === '') {
			return new DataDownloadResponse('', 'logo.png', 'image/png');
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->get($url, ['timeout' => 10]);
			$body = $response->getBody();
			$contentType = $response->getHeader('Content-Type') ?: 'image/png';

			return new DataDownloadResponse($body, 'logo', $contentType);
		} catch (\Exception $e) {
			return new DataDownloadResponse('', 'logo.png', 'image/png');
		}
	}
}
