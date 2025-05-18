<?php

namespace OCA\OpenConnector\Service\ConfigurationHandlers;

use OCA\OpenConnector\Db\Rule;
use OCA\OpenConnector\Db\RuleMapper;
use OCP\AppFramework\Db\Entity;

/**
 * Class RuleHandler
 *
 * Handler for exporting and importing rule configurations.
 *
 * @package OCA\OpenConnector\Service\ConfigurationHandlers
 * @category Service
 * @author OpenConnector Team
 * @copyright 2024 OpenConnector
 * @license AGPL-3.0
 * @version 1.0.0
 * @link https://github.com/OpenConnector/openconnector
 */
class RuleHandler implements ConfigurationHandlerInterface
{
    /**
     * @param RuleMapper $ruleMapper The rule mapper
     */
    public function __construct(
        private readonly RuleMapper $ruleMapper
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function export(Entity $entity, array $mappings): array
    {
        if (!$entity instanceof Rule) {
            throw new \InvalidArgumentException('Entity must be an instance of Rule');
        }

        $ruleArray = $entity->jsonSerialize();
        unset($ruleArray['id'], $ruleArray['uuid']);

        // Replace IDs with slugs where applicable
        if (isset($ruleArray['source_id']) && isset($mappings['source']['idToSlug'][$ruleArray['source_id']])) {
            $ruleArray['source_id'] = $mappings['source']['idToSlug'][$ruleArray['source_id']];
        }
        if (isset($ruleArray['target_id']) && isset($mappings['source']['idToSlug'][$ruleArray['target_id']])) {
            $ruleArray['target_id'] = $mappings['source']['idToSlug'][$ruleArray['target_id']];
        }

        return $ruleArray;
    }

    /**
     * {@inheritDoc}
     */
    public function import(array $data, array $mappings): Entity
    {
        // Check if rule with this slug already exists
        if (isset($data['slug']) && isset($mappings['rule']['slugToId'][$data['slug']])) {
            // Update existing rule
            $rule = $this->ruleMapper->find($mappings['rule']['slugToId'][$data['slug']]);
        } else {
            // Create new rule
            $rule = new Rule();
        }

        // Convert slugs back to IDs
        if (isset($data['source_id']) && isset($mappings['source']['slugToId'][$data['source_id']])) {
            $data['source_id'] = $mappings['source']['slugToId'][$data['source_id']];
        }
        if (isset($data['target_id']) && isset($mappings['source']['slugToId'][$data['target_id']])) {
            $data['target_id'] = $mappings['source']['slugToId'][$data['target_id']];
        }

        // Update rule with new data
        $rule->hydrate($data);

        // Save changes
        if ($rule->getId() === null) {
            return $this->ruleMapper->insert($rule);
        }
        return $this->ruleMapper->update($rule);
    }

    /**
     * {@inheritDoc}
     */
    public function getEntityType(): string
    {
        return 'rule';
    }
} 