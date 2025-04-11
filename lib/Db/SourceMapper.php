<?php

namespace OCA\OpenConnector\Db;

use OCA\OpenConnector\Db\Source;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\BaseMapper;
use OCP\IDBConnection;
use Symfony\Component\Uid\Uuid;

/**
 * Class SourceMapper
 *
 * This class is responsible for mapping Source entities to the database.
 * It provides methods for finding, creating, and updating Source objects.
 *
 * @package OCA\OpenConnector\Db
 * @extends BaseMapper<Source>
 */
class SourceMapper extends BaseMapper
{
	/**
	 * The name of the database table for sources
	 */
	private const TABLE_NAME = 'openconnector_sources';

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
	 * Create a new Source entity instance
	 *
	 * @return Source A new Source instance
	 */
	protected function createEntity(): Entity
	{
		return new Source();
	}
}
