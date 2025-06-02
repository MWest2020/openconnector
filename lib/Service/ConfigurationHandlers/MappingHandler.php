<?php

namespace OCA\OpenConnector\Service\ConfigurationHandlers;

use OCA\OpenConnector\Db\Mapping;
use OCA\OpenConnector\Db\MappingMapper;
use OCP\AppFramework\Db\Entity;

/**
 * Class MappingHandler
 *
 * Handler for exporting and importing mapping configurations.
 *
 * @package OCA\OpenConnector\Service\ConfigurationHandlers
 * @category Service
 * @author OpenConnector Team
 * @copyright 2024 OpenConnector
 * @license AGPL-3.0
 * @version 1.0.0
 * @link https://github.com/OpenConnector/openconnector
 */
class MappingHandler implements ConfigurationHandlerInterface
{
    /**
     * @param MappingMapper $mappingMapper The mapping mapper
     */
    public function __construct(
        private readonly MappingMapper $mappingMapper
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function export(Entity $entity, array $mappings): array
    {
        if (!$entity instanceof Mapping) {
            throw new \InvalidArgumentException('Entity must be an instance of Mapping');
        }

        $mappingArray = $entity->jsonSerialize();
        unset($mappingArray['id'], $mappingArray['uuid']);
        
        // Ensure slug is set
        if (empty($mappingArray['slug'])) {
            $mappingArray['slug'] = $entity->getSlug();
        }

        // Replace IDs with slugs where applicable.
        if (isset($mappingArray['source_id']) && isset($mappings['source']['idToSlug'][$mappingArray['source_id']])) {
            $mappingArray['source_id'] = $mappings['source']['idToSlug'][$mappingArray['source_id']];
        }
        if (isset($mappingArray['target_id']) && isset($mappings['source']['idToSlug'][$mappingArray['target_id']])) {
            $mappingArray['target_id'] = $mappings['source']['idToSlug'][$mappingArray['target_id']];
        }

        return $mappingArray;
    }

    /**
     * {@inheritDoc}
     */
    public function import(array $data, array $mappings): Entity
    {
        // Convert slugs back to IDs.
        if (isset($data['source_id']) && isset($mappings['source']['slugToId'][$data['source_id']])) {
            $data['source_id'] = $mappings['source']['slugToId'][$data['source_id']];
        }
        if (isset($data['target_id']) && isset($mappings['source']['slugToId'][$data['target_id']])) {
            $data['target_id'] = $mappings['source']['slugToId'][$data['target_id']];
        }

        // Check if mapping with this slug already exists.
        if (isset($data['slug']) && isset($mappings['mapping']['slugToId'][$data['slug']])) {
            // Update existing mapping.
            return $this->mappingMapper->updateFromArray($mappings['mapping']['slugToId'][$data['slug']], $data);
        }
        
        // Create new mapping.
        return $this->mappingMapper->createFromArray($data);
    }

    /**
     * {@inheritDoc}
     */
    public function getEntityType(): string
    {
        return 'mapping';
    }
} 