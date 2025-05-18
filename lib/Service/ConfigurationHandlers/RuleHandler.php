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

        // Handle nested configuration structures
        if (isset($ruleArray['configuration']) && is_array($ruleArray['configuration'])) {
            $ruleArray['configuration'] = $this->convertIdsToSlugs($ruleArray['configuration'], $mappings);
        }

        return $ruleArray;
    }

    /**
     * Recursively convert IDs to slugs in configuration arrays
     *
     * @param array $config The configuration array to process
     * @param array $mappings The mappings array containing idToSlug mappings
     * @return array The processed configuration with IDs converted to slugs
     */
    private function convertIdsToSlugs(array $config, array $mappings): array
    {
        $entityTypes = ['source', 'job', 'endpoint', 'mapping', 'register', 'schema'];
        
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                // Recursively process nested arrays
                $config[$key] = $this->convertIdsToSlugs($value, $mappings);
            } else {
                // Check if the key is an entity reference
                foreach ($entityTypes as $type) {
                    // Check for exact match (e.g., 'source')
                    if ($key === $type && isset($mappings[$type]['idToSlug'][$value])) {
                        $config[$key] = $mappings[$type]['idToSlug'][$value];
                    }
                    // Check for ID suffix (e.g., 'sourceId')
                    if (str_ends_with($key, $type . 'Id') && isset($mappings[$type]['idToSlug'][$value])) {
                        $config[$key] = $mappings[$type]['idToSlug'][$value];
                    }
                }
            }
        }

        return $config;
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