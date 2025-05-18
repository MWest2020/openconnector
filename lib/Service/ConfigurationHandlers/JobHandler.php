<?php

namespace OCA\OpenConnector\Service\ConfigurationHandlers;

use OCA\OpenConnector\Db\Job;
use OCA\OpenConnector\Db\JobMapper;
use OCP\AppFramework\Db\Entity;

/**
 * Class JobHandler
 *
 * Handler for exporting and importing job configurations.
 *
 * @package OCA\OpenConnector\Service\ConfigurationHandlers
 * @category Service
 * @author OpenConnector Team
 * @copyright 2024 OpenConnector
 * @license AGPL-3.0
 * @version 1.0.0
 * @link https://github.com/OpenConnector/openconnector
 */
class JobHandler implements ConfigurationHandlerInterface
{
    /**
     * @param JobMapper $jobMapper The job mapper
     */
    public function __construct(
        private readonly JobMapper $jobMapper
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function export(Entity $entity, array $mappings): array
    {
        if (!$entity instanceof Job) {
            throw new \InvalidArgumentException('Entity must be an instance of Job');
        }

        $jobArray = $entity->jsonSerialize();
        unset($jobArray['id'], $jobArray['uuid']);

        // Replace IDs with slugs in arguments JSON.
        if (isset($jobArray['arguments'])) {
            $arguments = json_decode($jobArray['arguments'], true);
            if (is_array($arguments)) {
                if (isset($arguments['synchronizationId']) && isset($mappings['synchronization']['idToSlug'][$arguments['synchronizationId']])) {
                    $arguments['synchronizationId'] = $mappings['synchronization']['idToSlug'][$arguments['synchronizationId']];
                }
                if (isset($arguments['endpointId']) && isset($mappings['endpoint']['idToSlug'][$arguments['endpointId']])) {
                    $arguments['endpointId'] = $mappings['endpoint']['idToSlug'][$arguments['endpointId']];
                }
                if (isset($arguments['sourceId']) && isset($mappings['source']['idToSlug'][$arguments['sourceId']])) {
                    $arguments['sourceId'] = $mappings['source']['idToSlug'][$arguments['sourceId']];
                }
                $jobArray['arguments'] = json_encode($arguments);
            }
        }

        return $jobArray;
    }

    /**
     * {@inheritDoc}
     */
    public function import(array $data, array $mappings): Entity
    {
        // Check if job with this slug already exists.
        if (isset($data['slug']) && isset($mappings['job']['slugToId'][$data['slug']])) {
            // Update existing job.
            $job = $this->jobMapper->find($mappings['job']['slugToId'][$data['slug']]);
        } else {
            // Create new job.
            $job = new Job();
        }

        // Convert slugs back to IDs in arguments JSON.
        if (isset($data['arguments'])) {
            $arguments = json_decode($data['arguments'], true);
            if (is_array($arguments)) {
                if (isset($arguments['synchronizationId']) && isset($mappings['synchronization']['slugToId'][$arguments['synchronizationId']])) {
                    $arguments['synchronizationId'] = $mappings['synchronization']['slugToId'][$arguments['synchronizationId']];
                }
                if (isset($arguments['endpointId']) && isset($mappings['endpoint']['slugToId'][$arguments['endpointId']])) {
                    $arguments['endpointId'] = $mappings['endpoint']['slugToId'][$arguments['endpointId']];
                }
                if (isset($arguments['sourceId']) && isset($mappings['source']['slugToId'][$arguments['sourceId']])) {
                    $arguments['sourceId'] = $mappings['source']['slugToId'][$arguments['sourceId']];
                }
                $data['arguments'] = json_encode($arguments);
            }
        }

        // Update job with new data.
        $job->hydrate($data);

        // Save changes.
        if ($job->getId() === null) {
            return $this->jobMapper->insert($job);
        }
        return $this->jobMapper->update($job);
    }

    /**
     * {@inheritDoc}
     */
    public function getEntityType(): string
    {
        return 'job';
    }
} 