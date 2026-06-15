<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Collector;

use OCP\App\IAppManager;
use Psr\Log\LoggerInterface;

class CollectorRegistry {
	/** @var ICollector[] */
	private array $collectors;

	public function __construct(
		private IAppManager $appManager,
		private LoggerInterface $logger,
		UsersCollector $users,
		FilesCollector $files,
		SharesCollector $shares,
		SystemCollector $system,
		TalkCollector $talk,
		DeckCollector $deck,
		MailCollector $mail,
		CalendarCollector $calendar,
		ContactsCollector $contacts,
		ActivityCollector $activity,
		FormsCollector $forms,
		RichdocumentsCollector $richdocuments,
	) {
		$this->collectors = [
			$users, $files, $shares, $system,
			$talk, $deck, $mail, $calendar, $contacts, $activity, $forms, $richdocuments,
		];
	}

	/**
	 * Get all collectors whose required app is installed (or "core").
	 *
	 * @return ICollector[]
	 */
	public function getAvailable(): array {
		return array_values(array_filter(
			$this->collectors,
			fn (ICollector $c) => $c->getAppId() === 'core'
				|| $this->appManager->isInstalled($c->getAppId())
		));
	}

	/**
	 * Get a specific collector by ID (only if available).
	 */
	public function get(string $id): ?ICollector {
		foreach ($this->getAvailable() as $collector) {
			if ($collector->getId() === $id) {
				return $collector;
			}
		}
		return null;
	}

	/**
	 * Get all collectors (available + unavailable) with metadata for the admin UI.
	 */
	public function getCollectorsInfo(): array {
		$availableIds = array_map(
			fn (ICollector $c) => $c->getId(),
			$this->getAvailable()
		);

		return array_map(fn (ICollector $c) => [
			'id' => $c->getId(),
			'name' => $c->getName(),
			'description' => $c->getDescription(),
			'app_id' => $c->getAppId(),
			'icon' => $c->getIcon(),
			'metrics' => $c->getAvailableMetrics(),
			'available' => in_array($c->getId(), $availableIds, true),
		], $this->collectors);
	}
}
