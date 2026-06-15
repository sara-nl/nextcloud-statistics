<?php

declare(strict_types=1);

namespace OCA\StatsCollector\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'stats_collector';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(AddContentSecurityPolicyEvent::class, CSPListener::class);
	}

	public function boot(IBootContext $context): void {
		// Register the navigation entry only for users who have access to the
		// personal dashboard (admins, or members of an allowed group).
		// We must skip registration entirely when there's no access, because
		// Nextcloud's navigation manager doesn't tolerate a closure that
		// returns an empty array.
		$context->injectFn(function (
			INavigationManager $navigationManager,
			IUserSession $userSession,
			IGroupManager $groupManager,
			IConfig $config,
			IURLGenerator $urlGenerator,
		): void {
			if (!$this->userHasAccess($userSession, $groupManager, $config)) {
				return;
			}
			$navigationManager->add(fn () => [
				'id' => self::APP_ID,
				'name' => 'Stats Collector',
				'href' => $urlGenerator->linkToRoute('stats_collector.personal_dashboard.index'),
				'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
				'order' => 50,
				'type' => 'link',
			]);
		});
	}

	private function userHasAccess(
		IUserSession $userSession,
		IGroupManager $groupManager,
		IConfig $config,
	): bool {
		$user = $userSession->getUser();
		if ($user === null) {
			return false;
		}
		// Admins always see the dashboard
		if ($groupManager->isAdmin($user->getUID())) {
			return true;
		}
		$allowedJson = $config->getAppValue(self::APP_ID, 'allowed_groups', '[]');
		$allowed = json_decode($allowedJson, true);
		if (!is_array($allowed) || empty($allowed)) {
			return false;
		}
		foreach ($allowed as $groupId) {
			if (is_string($groupId) && $groupManager->isInGroup($user->getUID(), $groupId)) {
				return true;
			}
		}
		return false;
	}
}

class CSPListener implements \OCP\EventDispatcher\IEventListener {
	public function handle(\OCP\EventDispatcher\Event $event): void {
		if (!($event instanceof AddContentSecurityPolicyEvent)) {
			return;
		}

		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('data:');
		$csp->addAllowedImageDomain('blob:');
		$event->addPolicy($csp);
	}
}
