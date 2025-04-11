<?php

namespace OCA\OpenConnector\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\Uid\Uuid;

/**
 * Class EventSubscriptionMapper
 *
 * Handles database operations for event subscriptions
 *
 * @package OCA\OpenConnector\Db
 */
class EventSubscriptionMapper extends QBMapper
{
    /**
     * The name of the database table for event subscriptions
     */
    private const TABLE_NAME = 'openconnector_event_subscriptions';

    /**
     * Constructor
     *
     * @param IDBConnection $db Database connection
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, self::TABLE_NAME);
    }
}
