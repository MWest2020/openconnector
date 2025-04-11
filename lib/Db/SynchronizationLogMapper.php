<?php

namespace OCA\OpenConnector\Db;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\ISession;
use OCP\IUserSession;
use Symfony\Component\Uid\Uuid;
use OCP\Session\Exceptions\SessionNotAvailableException;

/**
 * Class SynchronizationLogMapper
 *
 * This class is responsible for mapping SynchronizationLog entities to the database.
 * It provides methods for finding, creating, and updating SynchronizationLog objects.
 *
 * @package OCA\OpenConnector\Db
 */
class SynchronizationLogMapper extends QBMapper
{
	/**
	 * The name of the database table for synchronization logs
	 */
	private const TABLE_NAME = 'openconnector_synchronization_logs';

	public function __construct(
		IDBConnection $db,
		private readonly IUserSession $userSession,
		private readonly ISession $session
	) {
		parent::__construct($db, self::TABLE_NAME);
	}

	/**
	 * Find a synchronization log by its ID
	 *
	 * @param int $id The ID of the synchronization log to find
	 * @return SynchronizationLog The found synchronization log
	 */
	public function find(int $id): SynchronizationLog
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE_NAME)
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * Find all synchronization logs with optional filtering and pagination
	 *
	 * @param int|null $limit Maximum number of logs to return
	 * @param int|null $offset Number of logs to skip
	 * @param array $filters Additional filters to apply
	 * @param array $searchConditions Search conditions for the query
	 * @param array $searchParams Parameters for the search conditions
	 * @return array Array of SynchronizationLog objects
	 */
	public function findAll(
		?int $limit = null, 
		?int $offset = null, 
		?array $filters = [], 
		?array $searchConditions = [], 
		?array $searchParams = []
	): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE_NAME)
			->orderBy('created', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		foreach ($filters as $filter => $value) {
			if ($value === 'IS NOT NULL') {
				$qb->andWhere($qb->expr()->isNotNull($filter));
			} elseif ($value === 'IS NULL') {
				$qb->andWhere($qb->expr()->isNull($filter));
			} else {
				$qb->andWhere($qb->expr()->eq($filter, $qb->createNamedParameter($value)));
			}
		}

		if (empty($searchConditions) === false) {
			$qb->andWhere('(' . implode(' OR ', $searchConditions) . ')');
			foreach ($searchParams as $param => $value) {
				$qb->setParameter($param, $value);
			}
		}

		return $this->findEntities($qb);
	}

	/**
	 * Process contracts array to ensure it only contains valid UUIDs
	 *
	 * @param array $contracts Array of contracts or contract objects
	 * @return array Processed array containing only valid UUIDs
	 */
	private function processContracts(array $contracts): array 
	{
		return array_values(array_filter(
			array_map(
				function ($contract) {
					if (is_object($contract)) {
						// If it's an object with getUuid method, use that
						if (method_exists($contract, 'getUuid')) {
							return $contract->getUuid() ?: null;
						}
						return null;
					}
					// If it's already a string (UUID), return it
					return is_string($contract) ? $contract : null;
				},
				$contracts
			)
		));
	}

	/**
	 * Creates a new synchronization log entry
	 *
	 * @param array $object The log data
	 * @return SynchronizationLog The created log entry
	 */
	public function createFromArray(array $object): SynchronizationLog
	{
		$obj = new SynchronizationLog();

		// Auto-fill system fields
		$object['uuid'] = $object['uuid'] ?? Uuid::v4();
		$object['userId'] = $object['userId'] ?? $this->userSession->getUser()?->getUID();

		// Catch error from session, because when running from a Job this might cause an error preventing the Job from being ran.
		try {
			$object['sessionId'] = $object['sessionId'] ?? $this->session->getId();
		} catch (SessionNotAvailableException $exception) {
			$object['sessionId'] = null;
		}

		$object['created'] = $object['created'] ?? new DateTime();
		$object['expires'] = $object['expires'] ?? new DateTime('+30 days');
		$object['test'] = $object['test'] ?? false;
		$object['force'] = $object['force'] ?? false;

		// Process contracts in results if they exist
		if (isset($object['result']['contracts']) && is_array($object['result']['contracts'])) {
			$object['result']['contracts'] = $this->processContracts($object['result']['contracts']);
		}

		$obj->hydrate($object);

		// Set uuid
		if ($obj->getUuid() === null){
			$obj->setUuid(Uuid::v4());
		}

		return $this->insert($obj);
	}

	/**
	 * Updates an existing synchronization log entry
	 *
	 * @param int $id The ID of the log entry to update
	 * @param array $object The updated log data
	 * @return SynchronizationLog The updated log entry
	 */
	public function updateFromArray(int $id, array $object): SynchronizationLog
	{
		$obj = $this->find($id);
		
		// Process contracts in results if they exist
		if (isset($object['result']['contracts']) && is_array($object['result']['contracts'])) {
			$object['result']['contracts'] = $this->processContracts($object['result']['contracts']);
		}
		
		$obj->hydrate($object);

		return $this->update($obj);
	}

	/**
	 * Get the total count of all synchronization logs
	 *
	 * @return int The total number of synchronization logs in the database
	 */
	public function getTotalCallCount(): int
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->createFunction('COUNT(*) as count'))
			->from(self::TABLE_NAME);

		$result = $qb->execute();
		$row = $result->fetch();

		return (int)$row['count'];
	}

	/**
	 * Clean up expired synchronization logs
	 *
	 * @return int Number of deleted entries
	 */
	public function cleanupExpired(): int
	{
		$qb = $this->db->getQueryBuilder();

		$qb->delete(self::TABLE_NAME)
			->where($qb->expr()->lt('expires', $qb->createNamedParameter(new DateTime(), IQueryBuilder::PARAM_DATE)));

		return $qb->executeStatement();
	}
}
