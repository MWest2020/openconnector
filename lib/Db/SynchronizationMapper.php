<?php

namespace OCA\OpenConnector\Db;

use OCA\OpenConnector\Db\Synchronization;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\Uid\Uuid;

class SynchronizationMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'openconnector_synchronizations');
	}

	public function find(int|string $id): Synchronization
	{
		// If it's a string but can be converted to a numeric value without data loss, use as ID
		// Otherwise try to find by UUID
		if (is_string($id) && ctype_digit($id) === false) {
			return $this->findByUuid($id);
		}

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('openconnector_synchronizations')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity(query: $qb);
	}

	public function findByUuid(string $uuid): Synchronization
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('openconnector_synchronizations')
			->where(
				$qb->expr()->eq('uuid', $qb->createNamedParameter($uuid))
			);

		return $this->findEntity(query: $qb);
	}

	public function findByRef(string $reference): array
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('openconnector_synchronizations')
			->where(
				$qb->expr()->eq('reference', $qb->createNamedParameter($reference))
			);

		return $this->findEntities(query: $qb);
	}

	public function findAll(?int $limit = null, ?int $offset = null, ?array $filters = [], ?array $searchConditions = [], ?array $searchParams = []): array
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('openconnector_synchronizations')
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

		return $this->findEntities(query: $qb);
	}

	public function createFromArray(array $object): Synchronization
	{
		$obj = new Synchronization();
		$obj->hydrate($object);

		// Set uuid
		if ($obj->getUuid() === null) {
			$obj->setUuid(Uuid::v4());
		}

		// Set version
		if (empty($obj->getVersion()) === true) {
			$obj->setVersion('0.0.1');
		}

		return $this->insert(entity: $obj);
	}

	public function updateFromArray(int $id, array $object): Synchronization
	{
		$obj = $this->find($id);

		// Set version
		if (empty($obj->getVersion()) === true) {
			$object['version'] = '0.0.1';
		} else if (empty($object['version']) === true) {
			// Update version
			$version = explode('.', $obj->getVersion());
			if (isset($version[2]) === true) {
				$version[2] = (int) $version[2] + 1;
				$object['version'] = implode('.', $version);
			}
		}

		$obj->hydrate($object);

		return $this->update($obj);
	}

    /**
     * Get the total count of all call logs.
     *
     * @return int The total number of call logs in the database.
     */
    public function getTotalCallCount(): int
    {
        $qb = $this->db->getQueryBuilder();

        // Select count of all logs
        $qb->select($qb->createFunction('COUNT(*) as count'))
           ->from('openconnector_synchronizations');

        $result = $qb->execute();
        $row = $result->fetch();

        // Return the total count
        return (int)$row['count'];
    }

    /**
     * Find all synchronizations that belong to a specific configuration.
     *
     * @param string $configurationId The ID of the configuration to find synchronizations for
     * @return array<Synchronization> Array of Synchronization entities
     */
    public function findByConfiguration(string $configurationId): array
    {
        $sql = 'SELECT * FROM `' . $this->getTableName() . '` WHERE JSON_CONTAINS(configurations, ?)';
        return $this->findEntities($sql, [$configurationId]);
    }

    /**
     * Find all synchronizations that are connected to a specific register and/or schema.
     * Synchronizations are considered connected if:
     * 1. Their sourceType or targetType is 'register/schema'
     * 2. The sourceId or targetId matches the provided register and/or schema
     *
     * @param string|null $registerId The ID of the register to find synchronizations for
     * @param string|null $schemaId The ID of the schema to find synchronizations for
     * @param bool $searchSource Whether to search in source fields (default: true)
     * @param bool $searchTarget Whether to search in target fields (default: true)
     * @return array<Synchronization> Array of Synchronization entities
     * @throws \InvalidArgumentException If neither registerId nor schemaId is provided
     */
    public function getByTarget(?string $registerId = null, ?string $schemaId = null, bool $searchSource = true, bool $searchTarget = true): array
    {
        // Validate that at least one parameter is provided
        if ($registerId === null && $schemaId === null) {
            throw new \InvalidArgumentException('Either registerId or schemaId must be provided');
        }

        // Validate that at least one search location is specified
        if (!$searchSource && !$searchTarget) {
            throw new \InvalidArgumentException('At least one of searchSource or searchTarget must be true');
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName());

        // Build the conditions for source and target
        $conditions = [];
        $params = [];

        if ($searchSource) {
            $sourceConditions = [];
            $sourceConditions[] = $qb->expr()->eq('source_type', $qb->createNamedParameter('register/schema'));
            
            if ($registerId !== null && $schemaId !== null) {
                $sourceConditions[] = $qb->expr()->eq('source_id', $qb->createNamedParameter($registerId . '/' . $schemaId));
            } elseif ($registerId !== null) {
                $sourceConditions[] = $qb->expr()->like('source_id', $qb->createNamedParameter($registerId . '/%'));
            } else {
                $sourceConditions[] = $qb->expr()->like('source_id', $qb->createNamedParameter('%/' . $schemaId));
            }
            
            $conditions[] = $qb->expr()->andX(...$sourceConditions);
        }

        if ($searchTarget) {
            $targetConditions = [];
            $targetConditions[] = $qb->expr()->eq('target_type', $qb->createNamedParameter('register/schema'));
            
            if ($registerId !== null && $schemaId !== null) {
                $targetConditions[] = $qb->expr()->eq('target_id', $qb->createNamedParameter($registerId . '/' . $schemaId));
            } elseif ($registerId !== null) {
                $targetConditions[] = $qb->expr()->like('target_id', $qb->createNamedParameter($registerId . '/%'));
            } else {
                $targetConditions[] = $qb->expr()->like('target_id', $qb->createNamedParameter('%/' . $schemaId));
            }
            
            $conditions[] = $qb->expr()->andX(...$targetConditions);
        }

        // Combine conditions with OR
        $qb->where($qb->expr()->orX(...$conditions));

        return $this->findEntities($qb);
    }
}
