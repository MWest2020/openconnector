<?php

namespace OCA\OpenConnector\Db;

use OCA\OpenConnector\Db\Job;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\BaseMapper;
use OCP\IDBConnection;
use Symfony\Component\Uid\Uuid;

/**
 * Class JobMapper
 *
 * This class is responsible for mapping Job entities to the database.
 * It provides methods for finding, creating, and updating Job objects.
 *
 * @package OCA\OpenConnector\Db
 * @extends BaseMapper<Job>
 */
class JobMapper extends BaseMapper
{
	/**
	 * The name of the database table for jobs
	 */
	private const TABLE_NAME = 'openconnector_jobs';

	public function __construct(IDBConnection $db)
	{
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
	 * Create a new Job entity instance
	 *
	 * @return Job A new Job instance
	 */
	protected function createEntity(): Entity
	{
		return new Job();
	}
}
