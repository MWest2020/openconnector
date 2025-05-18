<?php

namespace OCA\OpenConnector\Service;

use OCA\OpenConnector\Db\Source;
use OCA\OpenConnector\Db\Endpoint;
use OCA\OpenConnector\Db\Mapping;
use OCA\OpenConnector\Db\Rule;
use OCA\OpenConnector\Db\Job;
use OCA\OpenConnector\Db\Synchronization;
use OCA\OpenConnector\Db\SourceMapper;
use OCA\OpenConnector\Db\EndpointMapper;
use OCA\OpenConnector\Db\MappingMapper;
use OCA\OpenConnector\Db\RuleMapper;
use OCA\OpenConnector\Db\JobMapper;
use OCA\OpenConnector\Db\SynchronizationMapper;

/**
 * Class ConfigurationService
 *
 * Service class for managing configurations and their associated entities.
 *
 * @package OCA\OpenConnector\Service
 * @category Service
 * @author OpenConnector Team
 * @copyright 2024 OpenConnector
 * @license AGPL-3.0
 * @version 1.0.0
 * @link https://github.com/OpenConnector/openconnector
 */
class ConfigurationService
{
    /**
     * @var SourceMapper
     */
    private SourceMapper $sourceMapper;

    /**
     * @var EndpointMapper
     */
    private EndpointMapper $endpointMapper;

    /**
     * @var MappingMapper
     */
    private MappingMapper $mappingMapper;

    /**
     * @var RuleMapper
     */
    private RuleMapper $ruleMapper;

    /**
     * @var JobMapper
     */
    private JobMapper $jobMapper;

    /**
     * @var SynchronizationMapper
     */
    private SynchronizationMapper $synchronizationMapper;

    /**
     * ConfigurationService constructor.
     *
     * @param SourceMapper $sourceMapper
     * @param EndpointMapper $endpointMapper
     * @param MappingMapper $mappingMapper
     * @param RuleMapper $ruleMapper
     * @param JobMapper $jobMapper
     * @param SynchronizationMapper $synchronizationMapper
     */
    public function __construct(
        SourceMapper $sourceMapper,
        EndpointMapper $endpointMapper,
        MappingMapper $mappingMapper,
        RuleMapper $ruleMapper,
        JobMapper $jobMapper,
        SynchronizationMapper $synchronizationMapper
    ) {
        $this->sourceMapper = $sourceMapper;
        $this->endpointMapper = $endpointMapper;
        $this->mappingMapper = $mappingMapper;
        $this->ruleMapper = $ruleMapper;
        $this->jobMapper = $jobMapper;
        $this->synchronizationMapper = $synchronizationMapper;
    }

    /**
     * Get all entities associated with a specific configuration ID, indexed by their slug.
     *
     * @param string $configurationId The ID of the configuration to get entities for
     * @return array<string,array> Array containing all entities grouped by type and indexed by slug
     */
    public function getEntitiesByConfiguration(string $configurationId): array
    {
        // Helper function to index entities by slug
        $indexBySlug = function(array $entities): array {
            $indexedEntities = [];
            foreach ($entities as $entity) {
                if (isset($entity['slug'])) {
                    $indexedEntities[$entity['slug']] = $entity;
                }
            }
            return $indexedEntities;
        };

        return [
            'sources' => $indexBySlug($this->sourceMapper->findByConfiguration($configurationId)),
            'endpoints' => $indexBySlug($this->endpointMapper->findByConfiguration($configurationId)),
            'mappings' => $indexBySlug($this->mappingMapper->findByConfiguration($configurationId)),
            'rules' => $indexBySlug($this->ruleMapper->findByConfiguration($configurationId)),
            'jobs' => $indexBySlug($this->jobMapper->findByConfiguration($configurationId)),
            'synchronizations' => $indexBySlug($this->synchronizationMapper->findByConfiguration($configurationId)),
        ];
    }

    /**
     * Export all entities associated with a specific configuration ID to JSON.
     * Entities are organized by components following OAS structure.
     *
     * @param string $configurationId The ID of the configuration to export
     * @return array<string,array> JSON-serializable array containing all entities
     */
    public function exportConfiguration(string $configurationId): array
    {
        $entities = $this->getEntitiesByConfiguration($configurationId);
        
        // Organize entities by components
        $components = [
            'components' => [
                'sources' => $this->organizeEntitiesByComponent($entities['sources']),
                'endpoints' => $this->organizeEntitiesByComponent($entities['endpoints']),
                'mappings' => $this->organizeEntitiesByComponent($entities['mappings']),
                'rules' => $this->organizeEntitiesByComponent($entities['rules']),
                'jobs' => $this->organizeEntitiesByComponent($entities['jobs']),
                'synchronizations' => $this->organizeEntitiesByComponent($entities['synchronizations']),
            ],
        ];
        
        return $components;
    }

    /**
     * Organize entities by their component type.
     *
     * @param array $entities Array of entities to organize
     * @return array Organized entities by component
     */
    private function organizeEntitiesByComponent(array $entities): array
    {
        $organized = [];
        foreach ($entities as $entity) {
            $component = $this->getEntityComponent($entity);
            if (!isset($organized[$component])) {
                $organized[$component] = [];
            }
            $organized[$component][] = $entity;
        }
        return $organized;
    }

    /**
     * Get the component type for an entity.
     *
     * @param mixed $entity The entity to get the component type for
     * @return string The component type
     */
    private function getEntityComponent($entity): string
    {
        if ($entity instanceof Source) {
            return $entity->getType() ?? 'default';
        }
        if ($entity instanceof Endpoint) {
            return $entity->getTargetType() ?? 'default';
        }
        if ($entity instanceof Mapping) {
            return 'mapping';
        }
        if ($entity instanceof Rule) {
            return $entity->getType() ?? 'default';
        }
        if ($entity instanceof Job) {
            return 'job';
        }
        if ($entity instanceof Synchronization) {
            return 'sync';
        }
        return 'default';
    }

    /**
     * Export all entities (endpoints and synchronizations) connected to a specific register.
     * Entities are organized by their type and indexed by slug.
     * Also includes related rules, mappings, and sources.
     *
     * @param string $registerId The ID of the register to export entities for
     * @param bool $includeEndpoints Whether to include endpoints in the export (default: true)
     * @param bool $includeSynchronizations Whether to include synchronizations in the export (default: true)
     * @param bool $searchSource Whether to search in source fields for synchronizations (default: true)
     * @param bool $searchTarget Whether to search in target fields for synchronizations (default: true)
     * @return array<string,array> JSON-serializable array containing all connected entities
     */
    public function exportRegister(
        string $registerId,
        bool $includeEndpoints = true,
        bool $includeSynchronizations = true,
        bool $searchSource = true,
        bool $searchTarget = true
    ): array {
        $components = [
            'components' => [],
        ];

        // Collect all entity IDs for batch processing
        $ruleIds = [];
        $mappingIds = [];
        $sourceIds = [];
        $endpointIds = [];
        $synchronizationIds = [];

        // Get and organize endpoints if requested
        if ($includeEndpoints) {
            $endpoints = $this->endpointMapper->getByTarget(registerId: $registerId);
            $indexedEndpoints = [];
            foreach ($endpoints as $endpoint) {
                $indexedEndpoints[$endpoint->getSlug()] = $endpoint;
                $endpointIds[] = $endpoint->getId();
                
                // Collect related IDs
                if ($endpoint->getInputMapping() !== null) {
                    $mappingIds[] = $endpoint->getInputMapping();
                }
                if ($endpoint->getOutputMapping() !== null) {
                    $mappingIds[] = $endpoint->getOutputMapping();
                }
                if ($endpoint->getTargetType() === 'api') {
                    $sourceIds[] = $endpoint->getTargetId();
                }
            }
            $components['components']['endpoints'] = $indexedEndpoints;
        }

        // Get and organize synchronizations if requested
        if ($includeSynchronizations) {
            $synchronizations = $this->synchronizationMapper->getByTarget(
                registerId: $registerId,
                searchSource: $searchSource,
                searchTarget: $searchTarget
            );
            $indexedSynchronizations = [];
            foreach ($synchronizations as $synchronization) {
                $indexedSynchronizations[$synchronization->getSlug()] = $synchronization;
                $synchronizationIds[] = $synchronization->getId();
                
                // Collect related IDs
                if ($synchronization->getSourceTargetMapping() !== null) {
                    $mappingIds[] = $synchronization->getSourceTargetMapping();
                }
                if ($synchronization->getTargetSourceMapping() !== null) {
                    $mappingIds[] = $synchronization->getTargetSourceMapping();
                }
                if ($synchronization->getSourceType() === 'api') {
                    $sourceIds[] = $synchronization->getSourceId();
                }
                if ($synchronization->getTargetType() === 'api') {
                    $sourceIds[] = $synchronization->getTargetId();
                }
            }
            $components['components']['synchronizations'] = $indexedSynchronizations;
        }

        // Remove duplicates from collected IDs
        $ruleIds = array_unique($ruleIds);
        $mappingIds = array_unique($mappingIds);
        $sourceIds = array_unique($sourceIds);
        $endpointIds = array_unique($endpointIds);
        $synchronizationIds = array_unique($synchronizationIds);

        // Batch fetch related entities
        if (!empty($mappingIds)) {
            $mappings = $this->mappingMapper->findAll(filters: ['id' => $mappingIds]);
            $indexedMappings = [];
            foreach ($mappings as $mapping) {
                $indexedMappings[$mapping->getSlug()] = $mapping;
            }
            $components['components']['mappings'] = $indexedMappings;
        }

        if (!empty($sourceIds)) {
            $sources = $this->sourceMapper->findAll(filters: ['id' => $sourceIds]);
            $indexedSources = [];
            foreach ($sources as $source) {
                $indexedSources[$source->getSlug()] = $source;
            }
            $components['components']['sources'] = $indexedSources;
        }

        if (!empty($ruleIds)) {
            $rules = $this->ruleMapper->findAll(filters: ['id' => $ruleIds]);
            $indexedRules = [];
            foreach ($rules as $rule) {
                $indexedRules[$rule->getSlug()] = $rule;
            }
            $components['components']['rules'] = $indexedRules;
        }

        // Get related jobs
        if (!empty($endpointIds) || !empty($synchronizationIds) || !empty($sourceIds)) {
            $jobs = $this->jobMapper->findByArgumentIds(
                synchronizationIds: $synchronizationIds,
                endpointIds: $endpointIds,
                sourceIds: $sourceIds
            );
            $indexedJobs = [];
            foreach ($jobs as $job) {
                $indexedJobs[$job->getSlug()] = $job;
            }
            $components['components']['jobs'] = $indexedJobs;
        }

        return $components;
    }
} 