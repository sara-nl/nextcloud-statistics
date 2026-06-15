<?php

declare(strict_types=1);

return [
	'routes' => [
		// Public pull API for external systems (plain JSON, Bearer auth)
		['name' => 'pull#listSnapshots', 'url' => '/api/v1/snapshots', 'verb' => 'GET'],
		['name' => 'pull#latestSnapshot', 'url' => '/api/v1/snapshots/latest', 'verb' => 'GET'],
		['name' => 'pull#getSnapshot', 'url' => '/api/v1/snapshots/{filename}', 'verb' => 'GET',
			'requirements' => ['filename' => 'snapshot_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.json']],

		// Admin settings
		['name' => 'settings#get', 'url' => '/api/settings', 'verb' => 'GET'],
		['name' => 'settings#update', 'url' => '/api/settings', 'verb' => 'PUT'],
		['name' => 'settings#searchGroups', 'url' => '/api/settings/groups', 'verb' => 'GET'],
		['name' => 'settings#proxyLogo', 'url' => '/api/settings/logo', 'verb' => 'GET'],

		// Collectors (admin)
		['name' => 'collectors#index', 'url' => '/api/collectors', 'verb' => 'GET'],
		['name' => 'collectors#updateMetrics', 'url' => '/api/collectors/{id}/metrics', 'verb' => 'PUT'],

		// Admin dashboard
		['name' => 'stats#dashboard', 'url' => '/api/stats/dashboard', 'verb' => 'GET'],
		['name' => 'stats#collectNow', 'url' => '/api/stats/collect', 'verb' => 'POST'],
		['name' => 'stats#snapshots', 'url' => '/api/stats/snapshots', 'verb' => 'GET'],

		// API Keys for external pull access (admin)
		['name' => 'api_keys#listKeys', 'url' => '/api/keys', 'verb' => 'GET'],
		['name' => 'api_keys#createKey', 'url' => '/api/keys', 'verb' => 'POST'],
		['name' => 'api_keys#revokeKey', 'url' => '/api/keys/{id}', 'verb' => 'DELETE'],

		// Personal dashboard (top-level page)
		['name' => 'personal_dashboard#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'personal_dashboard#dashboard', 'url' => '/api/personal/dashboard', 'verb' => 'GET'],
		['name' => 'personal_dashboard#history', 'url' => '/api/personal/history', 'verb' => 'GET'],
		['name' => 'personal_dashboard#getPreferences', 'url' => '/api/personal/preferences', 'verb' => 'GET'],
		['name' => 'personal_dashboard#updatePreferences', 'url' => '/api/personal/preferences', 'verb' => 'PUT'],
	],
];
