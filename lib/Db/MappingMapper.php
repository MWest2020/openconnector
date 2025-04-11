<?php

namespace OCA\OpenConnector\Db;

use OCA\OpenConnector\Db\Mapping;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\BaseMapper;
use OCP\IDBConnection;
use Symfony\Component\Uid\Uuid;

/**
 * Class MappingMapper
 *
 * This class is responsible for mapping Mapping entities to the database.
 * It provides methods for finding, creating, and updating Mapping objects.
 *
 * @package OCA\OpenConnector\Db
 * @extends BaseMapper<Mapping>
 */
class MappingMapper extends BaseMapper
{
    /**
     * The name of the database table for mappings
     */
    private const TABLE_NAME = 'openconnector_mappings';


    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, self::TABLE_NAME);

    }//end __construct()


    /**
     * Get the name of the database table
     *
     * @return string The table name
     */
    protected function getTableName(): string
    {
        return self::TABLE_NAME;

    }//end getTableName()


    /**
     * Create a new Mapping entity instance
     *
     * @return Mapping A new Mapping instance
     */
    protected function createEntity(): Entity
    {
        return new Mapping();

    }//end createEntity()


}//end class
