<?php

namespace OCA\OpenConnector\Service\ConfigurationHandlers;

use OCA\OpenConnector\Db\Synchronization;
use OCA\OpenConnector\Db\SynchronizationMapper;
use OCP\AppFramework\Db\Entity;

/**
 * Class SynchronizationHandler
 *
 * Handler for exporting and importing synchronization configurations.
 *
 * @package OCA\OpenConnector\Service\ConfigurationHandlers
 * @category Service
 * @author OpenConnector Team
 * @copyright 2024 OpenConnector
 * @license AGPL-3.0
 * @version 1.0.0
 * @link https://github.com/OpenConnector/openconnector
 */
class SynchronizationHandler implements ConfigurationHandlerInterface
{
    /**
     * @param SynchronizationMapper $synchronizationMapper The synchronization mapper
     */
    public function __construct(
        private readonly SynchronizationMapper $synchronizationMapper
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function export(Entity $entity, array $mappings): array
    {
        if (!$entity instanceof Synchronization) {
            throw new \InvalidArgumentException('Entity must be an instance of Synchronization');
        }

        $syncArray = $entity->jsonSerialize();
        unset($syncArray['id'], $syncArray['uuid']);

        // Handle sourceId based on sourceType.
        if (isset($syncArray['sourceId']) && isset($syncArray['sourceType'])) {
            switch ($syncArray['sourceType']) {
                case 'api':
                case 'database':
                    // For api/database sources, use source mapping.
                    if (isset($mappings['source']['idToSlug'][$syncArray['sourceId']])) {
                        $syncArray['sourceId'] = $mappings['source']['idToSlug'][$syncArray['sourceId']];
                    }
                    break;

                case 'register/schema':
                    // For register/schema sources, split the ID and map both parts.
                    if (str_contains($syncArray['sourceId'], '/')) {
                        [$registerId, $schemaId] = explode('/', $syncArray['sourceId']);
                        
                        // Map register ID to slug
                        if (isset($mappings['register']['idToSlug'][$registerId])) {
                            $registerSlug = $mappings['register']['idToSlug'][$registerId];
                        } else {
                            $registerSlug = $registerId; // Fallback to original ID if no mapping found.
                        }

                        // Map schema ID to slug
                        if (isset($mappings['schema']['idToSlug'][$schemaId])) {
                            $schemaSlug = $mappings['schema']['idToSlug'][$schemaId];
                        } else {
                            $schemaSlug = $schemaId; // Fallback to original ID if no mapping found.
                        }

                        // Combine the slugs.isterSlug . '/' . $schemaSlug;
                    }
                    break;
            }
        }

        // Handle targetId based on targetType
        if (isset($syncArray['targetId']) && isset($syncArray['targetType'])) {
            switch ($syncArray['targetType']) {
                case 'api':
                case 'database':
                    // For api/database targets, use source mapping.
                    if (isset($mappings['source']['idToSlug'][$syncArray['targetId']])) {
                        $syncArray['targetId'] = $mappings['source']['idToSlug'][$syncArray['targetId']];
                    }
                    break;

                case 'register/schema':
                    // For register/schema targets, split the ID and map both parts.
                    if (str_contains($syncArray['targetId'], '/')) {
                        [$registerId, $schemaId] = explode('/', $syncArray['targetId']);
                        
                        // Map register ID to slug
                        if (isset($mappings['register']['idToSlug'][$registerId])) {
                            $registerSlug = $mappings['register']['idToSlug'][$registerId];
                        } else {
                            $registerSlug = $registerId; // Fallback to original ID if no mapping found.
                        }

                        // Map schema ID to slug
                        if (isset($mappings['schema']['idToSlug'][$schemaId])) {
                            $schemaSlug = $mappings['schema']['idToSlug'][$schemaId];
                        } else {
                            $schemaSlug = $schemaId; // Fallback to original ID if no mapping found.
                        }

                        // Combine the slugs.
                        $syncArray['targetId'] = $registerSlug . '/' . $schemaSlug;
                    }
                    break;
            }
        }

        // Handle mapping IDs.
        if (isset($syncArray['sourceTargetMapping']) && isset($mappings['mapping']['idToSlug'][$syncArray['sourceTargetMapping']])) {
            $syncArray['sourceTargetMapping'] = $mappings['mapping']['idToSlug'][$syncArray['sourceTargetMapping']];
        }
        if (isset($syncArray['targetSourceMapping']) && isset($mappings['mapping']['idToSlug'][$syncArray['targetSourceMapping']])) {
            $syncArray['targetSourceMapping'] = $mappings['mapping']['idToSlug'][$syncArray['targetSourceMapping']];
        }

        return $syncArray;
    }

    /**
     * {@inheritDoc}
     */
    public function import(array $data, array $mappings): Entity
    {
        // Check if synchronization with this slug already exists.
        if (isset($data['slug']) && isset($mappings['synchronization']['slugToId'][$data['slug']])) {
            // Update existing synchronization.
            $synchronization = $this->synchronizationMapper->find($mappings['synchronization']['slugToId'][$data['slug']]);
        } else {
            // Create new synchronization.
            $synchronization = new Synchronization();
        }

        // Convert source slugs back to IDs.
        if (isset($data['sourceId']) && isset($data['sourceType'])) {
            switch ($data['sourceType']) {
                case 'api':
                case 'database':
                    // For api/database sources, use source mapping.
                    if (isset($mappings['source']['slugToId'][$data['sourceId']])) {
                        $data['sourceId'] = $mappings['source']['slugToId'][$data['sourceId']];
                    }
                    break;

                case 'register/schema':
                    // For register/schema sources, split the ID and map both parts.
                    if (str_contains($data['sourceId'], '/')) {
                        [$registerSlug, $schemaSlug] = explode('/', $data['sourceId']);
                        
                        // Map register slug to ID
                        if (isset($mappings['register']['slugToId'][$registerSlug])) {
                            $registerId = $mappings['register']['slugToId'][$registerSlug];
                        } else {
                            $registerId = $registerSlug; // Fallback to original slug if no mapping found.
                        }

                        // Map schema slug to ID
                        if (isset($mappings['schema']['slugToId'][$schemaSlug])) {
                            $schemaId = $mappings['schema']['slugToId'][$schemaSlug];
                        } else {
                            $schemaId = $schemaSlug; // Fallback to original slug if no mapping found.
                        }

                        // Combine the IDs
                        $data['sourceId'] = $registerId . '/' . $schemaId;
                    }
                    break;
            }
        }

        // Convert target slugs back to IDs
        if (isset($data['targetId']) && isset($data['targetType'])) {
            switch ($data['targetType']) {
                case 'api':
                case 'database':
                    // For api/database targets, use source mapping.
                    if (isset($mappings['source']['slugToId'][$data['targetId']])) {
                        $data['targetId'] = $mappings['source']['slugToId'][$data['targetId']];
                    }
                    break;

                case 'register/schema':
                    // For register/schema targets, split the ID and map both parts.
                    if (str_contains($data['targetId'], '/')) {
                        [$registerSlug, $schemaSlug] = explode('/', $data['targetId']);
                        
                        // Map register slug to ID
                        if (isset($mappings['register']['slugToId'][$registerSlug])) {
                            $registerId = $mappings['register']['slugToId'][$registerSlug];
                        } else {
                            $registerId = $registerSlug; // Fallback to original slug if no mapping found.
                        }

                        // Map schema slug to ID
                        if (isset($mappings['schema']['slugToId'][$schemaSlug])) {
                            $schemaId = $mappings['schema']['slugToId'][$schemaSlug];
                        } else {
                            $schemaId = $schemaSlug; // Fallback to original slug if no mapping found.
                        }

                        // Combine the IDs
                        $data['targetId'] = $registerId . '/' . $schemaId;
                    }
                    break;
            }
        }

        // Handle mapping IDs.
        if (isset($data['sourceTargetMapping']) && isset($mappings['mapping']['slugToId'][$data['sourceTargetMapping']])) {
            $data['sourceTargetMapping'] = $mappings['mapping']['slugToId'][$data['sourceTargetMapping']];
        }
        if (isset($data['targetSourceMapping']) && isset($mappings['mapping']['slugToId'][$data['targetSourceMapping']])) {
            $data['targetSourceMapping'] = $mappings['mapping']['slugToId'][$data['targetSourceMapping']];
        }

        // Update synchronization with new data.
        $synchronization->hydrate($data);

        // Save changes.
        if ($synchronization->getId() === null) {
            return $this->synchronizationMapper->insert($synchronization);
        }
        return $this->synchronizationMapper->update($synchronization);
    }

    /**
     * {@inheritDoc}
     */
    public function getEntityType(): string
    {
        return 'synchronization';
    }
} 