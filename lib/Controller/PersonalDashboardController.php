<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Controller;

use OCA\StatsCollector\AppInfo\Application;
use OCA\StatsCollector\Service\SnapshotService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Util;

class PersonalDashboardController extends Controller {
	private const PREF_KEY = 'dashboard_preferences';
	private const ALLOWED_DENSITIES = ['comfortable', 'compact', 'dense'];

	public function __construct(
		IRequest $request,
		private IConfig $config,
		private IGroupManager $groupManager,
		private IUserSession $userSession,
		private IInitialState $initialState,
		private SnapshotService $snapshotService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Render the top-level dashboard page.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$this->initialState->provideInitialState('allowed', $this->isAllowed());
		$this->initialState->provideInitialState('instance_label', $this->config->getAppValue(Application::APP_ID, 'instance_label', ''));
		$this->initialState->provideInitialState('preferences', $this->loadPreferences());

		Util::addScript(Application::APP_ID, 'stats_collector-personal-settings');
		Util::addStyle(Application::APP_ID, 'admin');

		return new TemplateResponse(Application::APP_ID, 'personal');
	}

	/**
	 * Return the cached dashboard payload. The snapshot already contains
	 * only the metrics enabled in the Collectors tab, so no extra filtering needed.
	 */
	#[NoAdminRequired]
	public function dashboard(): JSONResponse {
		if (!$this->isAllowed()) {
			return new JSONResponse(['error' => 'Access denied'], Http::STATUS_FORBIDDEN);
		}

		$snapshotJson = $this->config->getAppValue(Application::APP_ID, 'last_snapshot', '');
		if ($snapshotJson === '') {
			return new JSONResponse(['empty' => true]);
		}

		$snapshot = json_decode($snapshotJson, true);
		if (!is_array($snapshot)) {
			return new JSONResponse(['empty' => true]);
		}

		return new JSONResponse($snapshot);
	}

	/**
	 * Return historical snapshots within a range for trend visualization.
	 *
	 * Query params:
	 *   - range: '24h' | '7d' | '30d' | '90d' | 'all' (default '7d')
	 */
	#[NoAdminRequired]
	public function history(): JSONResponse {
		if (!$this->isAllowed()) {
			return new JSONResponse(['error' => 'Access denied'], Http::STATUS_FORBIDDEN);
		}

		$range = (string)$this->request->getParam('range', '7d');
		$rangeDays = match ($range) {
			'24h' => 1,
			'7d' => 7,
			'30d' => 30,
			'90d' => 90,
			'all' => 0,
			default => 7,
		};

		$from = null;
		if ($rangeDays > 0) {
			$from = (new \DateTimeImmutable('-' . $rangeDays . ' days'))->format('c');
		}

		// Cap snapshots at 500 to keep response size reasonable
		$snapshots = $this->snapshotService->listSnapshots($from, null, 500, true);

		// Sort oldest first for time-series rendering
		usort($snapshots, fn ($a, $b) => strcmp($a['timestamp'], $b['timestamp']));

		// History only needs numeric scalars per metric (sparklines + spotlight).
		// Nested objects (installed_apps, mimetypes_distribution, etc.) and string
		// values (versions, hashes) inflate the payload by an order of magnitude
		// without contributing to any rendered chart, so we drop them here.
		$series = array_map(function ($s) {
			$collectors = $s['payload']['collectors'] ?? [];
			$slim = [];
			foreach ($collectors as $collectorId => $metrics) {
				if (!is_array($metrics)) {
					continue;
				}
				$kept = [];
				foreach ($metrics as $metricId => $value) {
					if (is_int($value) || is_float($value)) {
						$kept[$metricId] = $value;
					}
				}
				if (!empty($kept)) {
					$slim[$collectorId] = $kept;
				}
			}
			return [
				'timestamp' => $s['timestamp'],
				'collectors' => $slim,
			];
		}, $snapshots);

		return new JSONResponse([
			'range' => $range,
			'count' => count($series),
			'snapshots' => $series,
		]);
	}

	/**
	 * Return the current user's saved dashboard preferences (or defaults).
	 */
	#[NoAdminRequired]
	public function getPreferences(): JSONResponse {
		if (!$this->isAllowed()) {
			return new JSONResponse(['error' => 'Access denied'], Http::STATUS_FORBIDDEN);
		}
		return new JSONResponse($this->loadPreferences());
	}

	/**
	 * Persist the current user's dashboard preferences. Body is the same
	 * shape returned by getPreferences(); unknown keys are ignored, and
	 * each key is independently validated and clamped to safe values.
	 */
	#[NoAdminRequired]
	public function updatePreferences(): JSONResponse {
		if (!$this->isAllowed()) {
			return new JSONResponse(['error' => 'Access denied'], Http::STATUS_FORBIDDEN);
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'No user'], Http::STATUS_FORBIDDEN);
		}

		$body = $this->request->getParams();
		$clean = $this->validatePreferences($body);

		// Empty / "use defaults" payload clears the row entirely.
		if ($this->isEmptyPreferences($clean)) {
			$this->config->deleteUserValue($user->getUID(), Application::APP_ID, self::PREF_KEY);
		} else {
			$this->config->setUserValue(
				$user->getUID(),
				Application::APP_ID,
				self::PREF_KEY,
				json_encode($clean, JSON_UNESCAPED_SLASHES),
			);
		}

		return new JSONResponse($clean);
	}

	private function defaultPreferences(): array {
		return [
			'hidden_sections' => [],
			'section_order' => [],
			'hero_pinned' => [],
			'default_spotlight' => '',
			'density' => 'comfortable',
		];
	}

	private function loadPreferences(): array {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return $this->defaultPreferences();
		}
		$json = $this->config->getUserValue($user->getUID(), Application::APP_ID, self::PREF_KEY, '');
		if ($json === '') {
			return $this->defaultPreferences();
		}
		$decoded = json_decode($json, true);
		if (!is_array($decoded)) {
			return $this->defaultPreferences();
		}
		// Re-validate on read so a manually edited bad row can't crash the UI.
		return $this->validatePreferences($decoded);
	}

	private function validatePreferences(array $input): array {
		$out = $this->defaultPreferences();

		if (isset($input['hidden_sections']) && is_array($input['hidden_sections'])) {
			$out['hidden_sections'] = array_values(array_filter(
				array_map(fn ($v) => is_string($v) ? $v : null, $input['hidden_sections']),
				fn ($v) => $v !== null && $v !== '' && strlen($v) <= 64,
			));
		}

		if (isset($input['section_order']) && is_array($input['section_order'])) {
			$out['section_order'] = array_values(array_filter(
				array_map(fn ($v) => is_string($v) ? $v : null, $input['section_order']),
				fn ($v) => $v !== null && $v !== '' && strlen($v) <= 64,
			));
		}

		if (isset($input['hero_pinned']) && is_array($input['hero_pinned'])) {
			$pinned = array_values(array_filter(
				array_map(fn ($v) => is_string($v) ? $v : null, $input['hero_pinned']),
				fn ($v) => $v !== null && $this->isValidMetricKey($v),
			));
			$out['hero_pinned'] = array_slice($pinned, 0, 4);
		}

		if (isset($input['default_spotlight']) && is_string($input['default_spotlight'])) {
			$key = $input['default_spotlight'];
			$out['default_spotlight'] = ($key === '' || $this->isValidMetricKey($key)) ? $key : '';
		}

		if (isset($input['density']) && is_string($input['density'])
			&& in_array($input['density'], self::ALLOWED_DENSITIES, true)) {
			$out['density'] = $input['density'];
		}

		return $out;
	}

	private function isValidMetricKey(string $key): bool {
		// "<collector>.<metric>" - both segments must be a-z0-9_, length-bounded.
		if (strlen($key) > 128) {
			return false;
		}
		return (bool)preg_match('/^[a-z0-9_]+\.[a-z0-9_]+$/i', $key);
	}

	private function isEmptyPreferences(array $p): bool {
		return empty($p['hidden_sections'])
			&& empty($p['section_order'])
			&& empty($p['hero_pinned'])
			&& ($p['default_spotlight'] ?? '') === ''
			&& ($p['density'] ?? 'comfortable') === 'comfortable';
	}

	private function isAllowed(): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}
		// Admins always have access
		if ($this->groupManager->isAdmin($user->getUID())) {
			return true;
		}
		$allowedGroups = json_decode(
			$this->config->getAppValue(Application::APP_ID, 'allowed_groups', '[]'),
			true
		);
		if (!is_array($allowedGroups) || empty($allowedGroups)) {
			return false;
		}
		foreach ($allowedGroups as $groupId) {
			if ($this->groupManager->isInGroup($user->getUID(), $groupId)) {
				return true;
			}
		}
		return false;
	}
}
