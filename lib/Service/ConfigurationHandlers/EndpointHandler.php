<?php

namespace OCA\OpenConnector\Service\ConfigurationHandlers;

use OCA\OpenConnector\Db\Endpoint;
use OCA\OpenConnector\Db\EndpointMapper;
use OCP\AppFramework\Db\Entity;

/**
 * Class EndpointHandler
 *
 * Handler for exporting and importing endpoint configurations.
 *
 * @package OCA\OpenConnector\Service\ConfigurationHandlers
 * @category Service
 * @author OpenConnector Team
 * @copyright 2024 OpenConnector
 * @license AGPL-3.0
 * @version 1.0.0
 * @link https://github.com/OpenConnector/openconnector
 */
class EndpointHandler implements ConfigurationHandlerInterface
{
    /**
     * @param EndpointMapper $endpointMapper The endpoint mapper
     */
    public function __construct(
        private readonly EndpointMapper $endpointMapper
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function export(Entity $entity, array $mappings, array &$mappingIds = []): array
    {
        if (!$entity instanceof Endpoint) {
            throw new \InvalidArgumentException('Entity must be an instance of Endpoint');
        }

        $endpointArray = $entity->jsonSerialize();
        unset($endpointArray['id'], $endpointArray['uuid']);
        
        // Ensure slug is set
        if (empty($endpointArray['slug'])) {
            $endpointArray['slug'] = $entity->getSlug();
        }

        // Handle targetId based on targetType.
        if (isset($endpointArray['targetId']) && isset($endpointArray['targetType'])) {
            switch ($endpointArray['targetType']) {
                case 'api':
                case 'database':
                    // For api/database targets, use source mapping.
                    if (isset($mappings['source']['idToSlug'][$endpointArray['targetId']])) {
                        $endpointArray['targetId'] = $mappings['source']['idToSlug'][$endpointArray['targetId']];
                    }
                    break;

                case 'register/schema':
                    // For register/schema targets, split the ID and map both parts.
                    if (str_contains($endpointArray['targetId'], '/')) {
                        [$registerId, $schemaId] = explode('/', $endpointArray['targetId']);

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

                        // Combine the slugs
                        $endpointArray['targetId'] = $registerSlug . '/' . $schemaSlug;
                    }
                    break;
            }
        }

        // Handle mapping IDs
        if (isset($endpointArray['inputMapping']) && isset($mappings['mapping']['idToSlug'][$endpointArray['inputMapping']])) {
            $endpointArray['inputMapping'] = $mappings['mapping']['idToSlug'][$endpointArray['inputMapping']];
        }
        if (isset($endpointArray['outputMapping']) && isset($mappings['mapping']['idToSlug'][$endpointArray['outputMapping']])) {
            $endpointArray['outputMapping'] = $mappings['mapping']['idToSlug'][$endpointArray['outputMapping']];
        }

        if (isset($endpointArray['rules']) === true) {
		    $endpointArray['rules'] = array_filter(array_map(function(int|string $rule) use ($mappings) {
                if(is_numeric($rule)) {
                    $rule = (int)$rule;
                }
                if(isset($mappings['rule']['idToSlug'][$rule]) === true) {

                    return $mappings['rule']['idToSlug'][$rule];
                }
                return null;
            }, $endpointArray['rules']));
        }




        return $endpointArray;
    }

    /**
     * {@inheritDoc}
     */
    public function import(array $data, array $mappings): Entity
    {
        // Convert slugs back to IDs.
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

                        // Combine the IDs.
                        $data['targetId'] = $registerId . '/' . $schemaId;
                    }
                    break;
            }
        }

        // Handle mapping IDs.
        if (isset($data['inputMapping']) && isset($mappings['mapping']['slugToId'][$data['inputMapping']])) {
            $data['inputMapping'] = $mappings['mapping']['slugToId'][$data['inputMapping']];
        }
        if (isset($data['outputMapping']) && isset($mappings['mapping']['slugToId'][$data['outputMapping']])) {
            $data['outputMapping'] = $mappings['mapping']['slugToId'][$data['outputMapping']];
        }

		$data['rules'] = array_filter(array_map(function(int|string $rule) use ($mappings) {
			if(isset($mappings['rule']['slugToId'][$rule]) === true) {

				return $mappings['rule']['slugToId'][$rule];
			}
			return null;
		}, $data['rules']));


		// Check if endpoint with this slug already exists.
        if (isset($data['slug']) && isset($mappings['endpoint']['slugToId'][$data['slug']])) {
            // Update existing endpoint.
            return $this->endpointMapper->updateFromArray($mappings['endpoint']['slugToId'][$data['slug']], $data);
        }

        // Create new endpoint.
        return $this->endpointMapper->createFromArray($data);
    }

    /**
     * {@inheritDoc}
     */
    public function getEntityType(): string
    {
        return 'endpoint';
    }
}
