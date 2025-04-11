<?php

namespace OCA\OpenConnector\Db;

use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\BaseMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\Uid\Uuid;

/**
 * Class RuleMapper
 *
 * Handles database operations for rules
 *
 * @package OCA\OpenConnector\Db
 * @extends BaseMapper<Rule>
 */
class RuleMapper extends BaseMapper
{
    /**
     * The name of the database table for rules
     */
    private const TABLE_NAME = 'openconnector_rules';


    /**
     * @param IDBConnection $db
     */
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
     * Create a new Rule entity instance
     *
     * @return Rule A new Rule instance
     */
    protected function createEntity(): Entity
    {
        return new Rule();

    }//end createEntity()


    /**
     * Create a new rule from array data
     *
     * @param  array<string,mixed> $object
     * @return Rule
     */
    public function createFromArray(array $object): Rule
    {
        // Create and hydrate new rule object
        $obj = parent::createFromArray($object);

        // Rule-specific logic
        // If no order is specified, append to the end
        if ($obj->getOrder() === null) {
            $maxOrder = $this->getMaxOrder();
            $obj->setOrder($maxOrder + 1);
            $this->update($obj);
        }

        return $obj;

    }//end createFromArray()


    /**
     * Get the highest order number for rules
     *
     * @return int
     */
    private function getMaxOrder(): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COALESCE(MAX(`order`), 0) as max_order'))
            ->from($this->getTableName());

        $result = $qb->execute();
        $row    = $result->fetch();
        $result->closeCursor();

        return (int) ($row['max_order']);

    }//end getMaxOrder()


    /**
     * Reorder rules
     *
     * @param  array<int,int> $orderMap Array of rule ID => new order
     * @return void
     */
    public function reorder(array $orderMap): void
    {
        foreach ($orderMap as $ruleId => $newOrder) {
            $qb = $this->db->getQueryBuilder();
            $qb->update($this->getTableName())
                ->set('order', $qb->createNamedParameter($newOrder, IQueryBuilder::PARAM_INT))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($ruleId, IQueryBuilder::PARAM_INT)))
                ->execute();
        }

    }//end reorder()


}//end class
