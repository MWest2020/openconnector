<?php

namespace OCA\OpenConnector\Db;

use DateInterval;
use DatePeriod;
use DateTime;
use OCA\OpenConnector\Db\SynchronizationContractLog;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\BaseMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\ISession;
use OCP\IUserSession;
use Symfony\Component\Uid\Uuid;
use OCP\Session\Exceptions\SessionNotAvailableException;

/**
 * Class SynchronizationContractLogMapper
 *
 * This class is responsible for mapping SynchronizationContractLog entities to the database.
 * It provides methods for finding, creating, and updating SynchronizationContractLog objects.
 *
 * @package OCA\OpenConnector\Db
 * @extends BaseMapper<SynchronizationContractLog>
 */
class SynchronizationContractLogMapper extends BaseMapper
{
	/**
	 * The name of the database table for synchronization contract logs
	 */
	private const TABLE_NAME = 'openconnector_synchronization_contract_logs';

	public function __construct(
		IDBConnection $db,
		private readonly IUserSession $userSession,
		private readonly ISession $session
	) {
		parent::__construct($db, self::TABLE_NAME);
	}

	/**
	 * Get the name of the database table
	 *
	 * @return string The table name
	 */
	protected function getTableName(): string
	{
		return self::TABLE_NAME;
	}

	/**
	 * Create a new SynchronizationContractLog entity instance
	 *
	 * @return SynchronizationContractLog A new SynchronizationContractLog instance
	 */
	protected function createEntity(): Entity
	{
		return new SynchronizationContractLog();
	}

	/**
	 * Find a synchronization contract log by synchronization ID
	 *
	 * @param string $synchronizationId The synchronization ID to search for
	 * @return SynchronizationContractLog|null The found log or null if not found
	 */
	public function findOnSynchronizationId(string $synchronizationId): ?SynchronizationContractLog
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('synchronization_id', $qb->createNamedParameter($synchronizationId))
			);

		try {
			return $this->findEntity($qb);
		} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
			return null;
		}
	}


	public function createFromArray(array $object): SynchronizationContractLog
	{
		$obj = new SynchronizationContractLog();
		$obj->hydrate($object);

		// Set uuid if not provided
		if ($obj->getUuid() === null) {
			$obj->setUuid(Uuid::v4());
		}

		// Auto-fill userId from current user session
		if ($obj->getUserId() === null && $this->userSession->getUser() !== null) {
			$obj->setUserId($this->userSession->getUser()->getUID());
		}

		// Auto-fill sessionId from current session
		if ($obj->getSessionId() === null) {
			// Try catch because we could run this from a Job and in that case have no session.
			try {
				$obj->setSessionId($this->session->getId());
			} catch (SessionNotAvailableException $exception) {
				$obj->setSessionId(null);
			}
		}

		// If no synchronizationLogId is provided, we assume that the contract is run directly from the synchronization log and set the synchronizationLogId to n.a.
		if ($obj->getSynchronizationLogId() === null) {
			$obj->setSynchronizationLogId('n.a.');
		}

		return $this->insert($obj);
	}


	/**
	 * Get synchronization execution counts by date for a specific date range
	 *
	 * @param DateTime $from Start date
	 * @param DateTime $to End date
	 *
	 * @return array Array of daily execution counts
	 * @throws Exception
	 */
	public function getSyncStatsByDateRange(DateTime $from, DateTime $to): array
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select(
				$qb->createFunction('DATE(created) as date'),
				$qb->createFunction('COUNT(*) as executions')
			)
			->from($this->getTableName())
			->where($qb->expr()->gte('created', $qb->createNamedParameter($from->format('Y-m-d H:i:s'))))
			->andWhere($qb->expr()->lte('created', $qb->createNamedParameter($to->format('Y-m-d H:i:s'))))
			->groupBy('date')
			->orderBy('date', 'ASC');

		$result = $qb->execute();
		$stats = [];

		// Create DatePeriod to iterate through all dates
		$period = new DatePeriod(
			$from,
			new DateInterval('P1D'),
			$to->modify('+1 day')
		);

		// Initialize all dates with zero values
		foreach ($period as $date) {
			$dateStr = $date->format('Y-m-d');
			$stats[$dateStr] = 0;
		}

		// Fill in actual values where they exist
		while ($row = $result->fetch()) {
			$stats[$row['date']] = (int)$row['executions'];
		}

		return $stats;
	}

	/**
	 * Get synchronization execution counts by hour for a specific date range
	 *
	 * @param DateTime $from Start date
	 * @param DateTime $to End date
	 * 
	 * @return array Array of hourly execution counts
	 * @throws Exception
	 */
	public function getSyncStatsByHourRange(DateTime $from, DateTime $to): array
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select(
				$qb->createFunction('HOUR(created) as hour'),
				$qb->createFunction('COUNT(*) as executions')
			)
			->from($this->getTableName())
			->where($qb->expr()->gte('created', $qb->createNamedParameter($from->format('Y-m-d H:i:s'))))
			->andWhere($qb->expr()->lte('created', $qb->createNamedParameter($to->format('Y-m-d H:i:s'))))
			->groupBy('hour')
			->orderBy('hour', 'ASC');

		$result = $qb->execute();
		$stats = [];

		while ($row = $result->fetch()) {
			$stats[$row['hour']] = (int)$row['executions'];
		}

		return $stats;
	}

	/**
	 * Cleans up expired log entries
	 *
	 * @return int Number of deleted entries
	 */
	public function cleanupExpired(): int
	{
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->lt('expires', $qb->createNamedParameter(new DateTime(), IQueryBuilder::PARAM_DATE)));

		return $qb->executeStatement();
	}
}
