<?php

declare(strict_types=1);

namespace OCA\StatsCollector\Collector;

use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class ContactsCollector implements ICollector {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return 'contacts';
	}

	public function getName(): string {
		return 'Contacts';
	}

	public function getDescription(): string {
		return 'Address book and contact statistics';
	}

	public function getAppId(): string {
		return 'contacts';
	}

	public function getIcon(): string {
		return 'icon-contacts';
	}

	public function getAvailableMetrics(): array {
		return [
			[
				'id' => 'total_addressbooks',
				'name' => 'Total address books',
				'description' => 'Total number of address books',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'total_contacts',
				'name' => 'Total contacts',
				'description' => 'Total number of contacts across all address books',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'contacts_per_addressbook',
				'name' => 'Contacts per address book',
				'description' => 'Contact count grouped by address book',
				'type' => 'gauge',
				'method' => 'db',
			],
			[
				'id' => 'addressbooks_shared',
				'name' => 'Shared address books',
				'description' => 'Number of address books that are shared',
				'type' => 'gauge',
				'method' => 'db',
			],
		];
	}

	public function collect(array $enabledMetricIds): array {
		$result = [];

		foreach ($enabledMetricIds as $metricId) {
			try {
				$result[$metricId] = match ($metricId) {
					'total_addressbooks' => $this->getTotalAddressBooks(),
					'total_contacts' => $this->getTotalContacts(),
					'contacts_per_addressbook' => $this->getContactsPerAddressBook(),
					'addressbooks_shared' => $this->getSharedAddressBooks(),
					default => null,
				};
			} catch (\Exception $e) {
				$this->logger->warning('ContactsCollector: Failed to collect metric ' . $metricId, [
					'exception' => $e,
				]);
				$result[$metricId] = null;
			}
		}

		return $result;
	}

	private function getTotalAddressBooks(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('addressbooks');
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getTotalContacts(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('cards');
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}

	private function getContactsPerAddressBook(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('ab.displayname')
			->selectAlias($qb->func()->count('c.id'), 'count')
			->from('addressbooks', 'ab')
			->leftJoin('ab', 'cards', 'c', $qb->expr()->eq('c.addressbookid', 'ab.id'))
			->groupBy('ab.id', 'ab.displayname')
			->orderBy('count', 'DESC');
		$rows = $qb->executeQuery()->fetchAll();

		$result = [];
		foreach ($rows as $row) {
			$result[$row['displayname']] = (int)$row['count'];
		}
		return $result;
	}

	private function getSharedAddressBooks(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('dav_shares')
			->where($qb->expr()->eq('type', $qb->createNamedParameter('addressbook')));
		$row = $qb->executeQuery()->fetch();
		return (int)($row['count'] ?? 0);
	}
}
