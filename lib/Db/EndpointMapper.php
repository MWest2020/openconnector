<?php

namespace OCA\OpenConnector\Db;

use OCA\OpenConnector\Db\Endpoint;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\Uid\Uuid;

/**
 * Mapper class for handling Endpoint database operations
 */
class EndpointMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'openconnector_endpoints');
	}

	public function find(int $id): Endpoint
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('openconnector_endpoints')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity(query: $qb);
	}

	public function findByRef(string $reference): array
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('openconnector_endpoints')
			->where(
				$qb->expr()->eq('reference', $qb->createNamedParameter($reference))
			);

		return $this->findEntities(query: $qb);
	}

	public function findAll(?int $limit = null, ?int $offset = null, ?array $filters = [], ?array $searchConditions = [], ?array $searchParams = []): array
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('openconnector_endpoints')
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

	private function createEndpointRegex(string $endpoint): string {
		$regex = '#^' . preg_replace(
			['#\/{{([^}}]+)}}\/#', '#\/{{([^}}]+)}}$#'],
			['/([^/]+)/', '(/([^/]+))?'],
			$endpoint
		) . '#';

		// Replace only the LAST occurrence of "(/([^/]+))?#" with "(?:/([^/]+))?$#"
		$regex = preg_replace_callback(
			'/\(\/\(\[\^\/\]\+\)\)\?#/',
			function ($matches) {
				return '(?:/([^/]+))?$#';
			},
			$regex,
			1 // Limit to only one replacement
		);

		if (str_ends_with($regex, '?#') === false && str_ends_with($regex, '$#') === false) {
			$regex = substr($regex, 0, -1) . '$#';
		}

		return $regex;
	}

	public function createFromArray(array $object): Endpoint
	{
		$obj = new Endpoint();
		$obj->hydrate($object);

		// Set uuid
		if ($obj->getUuid() === null) {
			$obj->setUuid(Uuid::v4());
		}

		// Set version
		if (empty($obj->getVersion()) === true) {
			$obj->setVersion('0.0.1');
		}

		// Endpoint-specific logic
		$obj->setEndpointRegex($this->createEndpointRegex($obj->getEndpoint()));
		$obj->setEndpointArray(explode('/', $obj->getEndpoint()));

		return $this->insert(entity: $obj);
	}

	public function updateFromArray(int $id, array $object): Endpoint
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

		// Endpoint-specific logic
		$obj->setEndpointRegex($this->createEndpointRegex($obj->getEndpoint()));
		$obj->setEndpointArray(explode('/', $obj->getEndpoint()));

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
           ->from('openconnector_endpoints');

        $result = $qb->execute();
        $row = $result->fetch();

        // Return the total count
        return (int)$row['count'];
    }

    /**
     * Find endpoints that match a given path and method using regex comparison
     *
     * @param string $path The path to match against endpoint regex patterns
     * @param string $method The HTTP method to filter by (GET, POST, etc)
     * @return array Array of matching Endpoint entities
     */
    public function findByPathRegex(string $path, string $method): array
    {
        // Get all endpoints first since we need to do regex comparison
        $endpoints = $this->findAll();

        // Filter endpoints where both path matches regex pattern and method matches
        return array_filter($endpoints, function(Endpoint $endpoint) use ($path, $method) {
            // Get the regex pattern from the endpoint
            $pattern = $endpoint->getEndpointRegex();

            // Skip if no regex pattern is set
            if (empty($pattern) === true) {
                return false;
            }

            // Check if both path matches the regex pattern and method matches
            return preg_match($pattern, $path) === 1 &&
                   $endpoint->getMethod() === $method;
        });
    }

    /**
     * Find all endpoints that belong to a specific configuration.
     *
     * @param string $configurationId The ID of the configuration to find endpoints for
     * @return array<Endpoint> Array of Endpoint entities
     */
    public function findByConfiguration(string $configurationId): array
    {
        $sql = 'SELECT * FROM `' . $this->getTableName() . '` WHERE JSON_CONTAINS(configurations, ?)';
        return $this->findEntities($sql, [$configurationId]);
    }

    /**
     * Find all endpoints that are connected to a specific register and/or schema.
     * Endpoints are considered connected if:
     * 1. Their targetType is 'register/schema'
     * 2. The targetId matches the provided register and/or schema
     *
     * @param string|null $registerId The ID of the register to find endpoints for
     * @param string|null $schemaId The ID of the schema to find endpoints for
     * @return array<Endpoint> Array of Endpoint entities
     * @throws \InvalidArgumentException If neither registerId nor schemaId is provided
     */
    public function getByTarget(?string $registerId = null, ?string $schemaId = null): array
    {
        // Validate that at least one parameter is provided
        if ($registerId === null && $schemaId === null) {
            throw new \InvalidArgumentException('Either registerId or schemaId must be provided');
        }

        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('target_type', $qb->createNamedParameter('register/schema'))
            );

        // Build the target_id condition based on provided parameters
        if ($registerId !== null && $schemaId !== null) {
            // Both register and schema are provided - exact match
            $qb->andWhere(
                $qb->expr()->eq('target_id', $qb->createNamedParameter($registerId . '/' . $schemaId))
            );
        } elseif ($registerId !== null) {
            // Only register is provided - match any schema
            $qb->andWhere(
                $qb->expr()->like('target_id', $qb->createNamedParameter($registerId . '/%'))
            );
        } else {
            // Only schema is provided - match any register
            $qb->andWhere(
                $qb->expr()->like('target_id', $qb->createNamedParameter('%/' . $schemaId))
            );
        }

        return $this->findEntities($qb);
    }
}
