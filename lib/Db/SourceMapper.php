<?php

namespace OCA\OpenConnector\Db;

use OCA\OpenConnector\Db\Source;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
use Symfony\Component\Uid\Uuid;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Class SourceMapper
 *
 * This class is responsible for mapping Source entities to the database.
 * It provides methods for finding, creating, and updating Source objects.
 *
 * @package OCA\OpenConnector\Db
 * @extends QBMapper<Source>
 */
class SourceMapper extends QBMapper
{
    /**
     * The name of the database table for sources
     */
    private const TABLE_NAME = 'openconnector_sources';


    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, self::TABLE_NAME);

    }//end __construct()


    /**
     * Get the name of the database table
     *
     * @return string The table name
     */
    public function getTableName(): string
    {
        return self::TABLE_NAME;

    }//end getTableName()


    /**
     * Create a new Source entity instance
     *
     * @return Source A new Source instance
     */
    protected function createEntity(): Entity
    {
        return new Source();

    }//end createEntity()


    /**
     * Find a source by ID
     *
     * @param int $id The ID of the source to find
     * @return Source The found source entity
     * @throws DoesNotExistException If the source doesn't exist
     * @throws MultipleObjectsReturnedException If multiple sources match the criteria
     */
    public function find(int $id): Source
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );

        return $this->findEntity($qb);
    }

    /**
     * Find a source by UUID
     *
     * @param string $uuid The UUID of the source to find
     * @return Source The found source entity
     * @throws DoesNotExistException If the source doesn't exist
     * @throws MultipleObjectsReturnedException If multiple sources match the criteria
     */
    public function findByUuid(string $uuid): Source
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('uuid', $qb->createNamedParameter($uuid))
            );

        return $this->findEntity($qb);
    }

    /**
     * Find all sources with optional filtering and pagination
     *
     * @param int|null $limit Maximum number of results to return
     * @param int|null $offset Number of results to skip
     * @param array|null $filters Associative array of filter conditions (column => value)
     * @return Source[] Array of matching source entities
     */
    public function findAll(?int $limit=null, ?int $offset=null, ?array $filters=[]): array
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        // Apply filters
        foreach ($filters as $filter => $value) {
            if ($value === 'IS NOT NULL') {
                $qb->andWhere($qb->expr()->isNotNull($filter));
            } else if ($value === 'IS NULL') {
                $qb->andWhere($qb->expr()->isNull($filter));
            } else {
                $qb->andWhere($qb->expr()->eq($filter, $qb->createNamedParameter($value)));
            }
        }

        return $this->findEntities($qb);
    }

}//end class
