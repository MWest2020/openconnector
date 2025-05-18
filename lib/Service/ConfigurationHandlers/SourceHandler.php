<?php

namespace OCA\OpenConnector\Service\ConfigurationHandlers;

use OCA\OpenConnector\Db\Source;
use OCA\OpenConnector\Db\SourceMapper;
use OCP\AppFramework\Db\Entity;

/**
 * Class SourceHandler
 *
 * Handler for exporting and importing source configurations.
 *
 * @package OCA\OpenConnector\Service\ConfigurationHandlers
 * @category Service
 * @author OpenConnector Team
 * @copyright 2024 OpenConnector
 * @license AGPL-3.0
 * @version 1.0.0
 * @link https://github.com/OpenConnector/openconnector
 */
class SourceHandler implements ConfigurationHandlerInterface
{
    /**
     * @param SourceMapper $sourceMapper The source mapper
     */
    public function __construct(
        private readonly SourceMapper $sourceMapper
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function export(Entity $entity, array $mappings): array
    {
        if (!$entity instanceof Source) {
            throw new \InvalidArgumentException('Entity must be an instance of Source');
        }

        $sourceArray = $entity->jsonSerialize();
        unset($sourceArray['id'], $sourceArray['uuid']);
        return $sourceArray;
    }

    /**
     * {@inheritDoc}
     */
    public function import(array $data, array $mappings): Entity
    {
        // Check if source with this slug already exists.
        if (isset($data['slug']) && isset($mappings['source']['slugToId'][$data['slug']])) {
            // Update existing source
            $source = $this->sourceMapper->find($mappings['source']['slugToId'][$data['slug']]);
        } else {
            // Create new source.
            $source = new Source();
        }

        // Update source with new data.
        $source->hydrate($data);

        // Save changes.
        if ($source->getId() === null) {
            return $this->sourceMapper->insert($source);
        }
        return $this->sourceMapper->update($source);
    }

    /**
     * {@inheritDoc}
     */
    public function getEntityType(): string
    {
        return 'source';
    }
} 