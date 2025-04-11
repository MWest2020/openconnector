<?php

namespace OCA\OpenConnector\Db;

use OCA\OpenConnector\Db\Endpoint;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\BaseMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\Uid\Uuid;

/**
 * Mapper class for handling Endpoint database operations
 *
 * @package OCA\OpenConnector\Db
 * @extends BaseMapper<Endpoint>
 */
class EndpointMapper extends BaseMapper
{
    /**
     * The name of the database table for endpoints
     */
    private const TABLE_NAME = 'openconnector_endpoints';


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
     * Create a new Endpoint entity instance
     *
     * @return Endpoint A new Endpoint instance
     */
    protected function createEntity(): Entity
    {
        return new Endpoint();

    }//end createEntity()


    private function createEndpointRegex(string $endpoint): string
    {
        $regex = '#^'.preg_replace(
            [
                '#\/{{([^}}]+)}}\/#',
                '#\/{{([^}}]+)}}$#',
            ],
            [
                '/([^/]+)/',
                '(/([^/]+))?',
            ],
            $endpoint
        ).'#';

        // Replace only the LAST occurrence of "(/([^/]+))?#" with "(?:/([^/]+))?$#"
        $regex = preg_replace_callback(
            '/\(\/\(\[\^\/\]\+\)\)\?#/',
            function ($matches) {
                return '(?:/([^/]+))?$#';
            },
            $regex,
            1
            // Limit to only one replacement
        );

        if (str_ends_with($regex, '?#') === false && str_ends_with($regex, '$#') === false) {
            $regex = substr($regex, 0, -1).'$#';
        }

        return $regex;

    }//end createEndpointRegex()


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

    }//end createFromArray()


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
                $version[2]        = ((int) $version[2] + 1);
                $object['version'] = implode('.', $version);
            }
        }

        $obj->hydrate($object);

        // Endpoint-specific logic
        $obj->setEndpointRegex($this->createEndpointRegex($obj->getEndpoint()));
        $obj->setEndpointArray(explode('/', $obj->getEndpoint()));

        return $this->update($obj);

    }//end updateFromArray()


    /**
     * Find endpoints that match a given path and method using regex comparison
     *
     * @param  string $path   The path to match against endpoint regex patterns
     * @param  string $method The HTTP method to filter by (GET, POST, etc)
     * @return array Array of matching Endpoint entities
     */
    public function findByPathRegex(string $path, string $method): array
    {
        // Get all endpoints first since we need to do regex comparison
        $endpoints = $this->findAll();

        // Filter endpoints where both path matches regex pattern and method matches
        return array_filter(
            $endpoints,
            function (Endpoint $endpoint) use ($path, $method) {
                // Get the regex pattern from the endpoint
                $pattern = $endpoint->getEndpointRegex();

                // Skip if no regex pattern is set
                if (empty($pattern) === true) {
                    return false;
                }

                // Check if both path matches the regex pattern and method matches
                return preg_match($pattern, $path) === 1 &&
                       $endpoint->getMethod() === $method;
            }
        );

    }//end findByPathRegex()


    /**
     * Find endpoints that are linked to a specific register
     *
     * @param int $registerId The ID of the register to find endpoints for
     *
     * @return array<Endpoint> Array of Endpoint entities linked to the register
     */
    public function getByRegister(int $registerId): array
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->eq('target_type', $qb->createNamedParameter('register/schema')),
                    // Use LIKE to match the part before the '/' in target_id
                    $qb->expr()->like('target_id', $qb->createNamedParameter($registerId . '/%'))
                )
            );

        return $this->findEntities($qb);

    }//end getByRegister()


}//end class
