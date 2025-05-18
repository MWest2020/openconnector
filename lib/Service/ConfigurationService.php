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
use OCA\OpenRegister\Db\RegisterMapper;
use OCA\OpenRegister\Db\SchemaMapper;

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
     * @var RegisterMapper
     */
    private RegisterMapper $registerMapper;

    /**
     * @var SchemaMapper
     */
    private SchemaMapper $schemaMapper;

    /**
     * Global mapping structure for entity ID and slug relationships.
     * This structure is used during export/import operations to maintain consistent
     * references between entities.
     *
     * Structure:
     * [
     *     'endpoint' => [
     *         'idToSlug' => ['id1' => 'slug1', 'id2' => 'slug2', ...],
     *         'slugToId' => ['slug1' => 'id1', 'slug2' => 'id2', ...]
     *     ],
     *     'synchronization' => [
     *         'idToSlug' => ['id1' => 'slug1', 'id2' => 'slug2', ...],
     *         'slugToId' => ['slug1' => 'id1', 'slug2' => 'id2', ...]
     *     ],
     *     'mapping' => [...],
     *     'rule' => [...],
     *     'source' => [...],
     *     'register' => [...],
     *     'schema' => [...],
     *     'job' => [...]
     * ]
     *
     * Purpose:
     * - During export: Used to replace entity IDs with their corresponding slugs
     * - During import: Used to replace entity slugs with their corresponding IDs
     * - Maintains bidirectional mapping for efficient lookups
     * - Ensures consistent references between related entities
     *
     * Usage:
     * - Access ID to slug: $this->mappings['entityType']['idToSlug'][$id]
     * - Access slug to ID: $this->mappings['entityType']['slugToId'][$slug]
     *
     * @var array<string,array{idToSlug:array<string,string>,slugToId:array<string,string>}>
     */
    private array $mappings = [];

    /**
     * Map of entity IDs to their full entities.
     * Used during import to resolve references and maintain entity relationships.
     *
     * Structure:
     * [
     *     'entityType' => [
     *         'id1' => EntityObject1,
     *         'id2' => EntityObject2,
     *         ...
     *     ]
     * ]
     *
     * @var array<string,array<string,Entity>>
     */
    private array $entityMap = [];

    /**
     * ConfigurationService constructor.
     *
     * @param SourceMapper $sourceMapper
     * @param EndpointMapper $endpointMapper
     * @param MappingMapper $mappingMapper
     * @param RuleMapper $ruleMapper
     * @param JobMapper $jobMapper
     * @param SynchronizationMapper $synchronizationMapper
     * @param RegisterMapper $registerMapper
     * @param SchemaMapper $schemaMapper
     */
    public function __construct(
        SourceMapper $sourceMapper,
        EndpointMapper $endpointMapper,
        MappingMapper $mappingMapper,
        RuleMapper $ruleMapper,
        JobMapper $jobMapper,
        SynchronizationMapper $synchronizationMapper,
        RegisterMapper $registerMapper,
        SchemaMapper $schemaMapper
    ) {
        $this->sourceMapper = $sourceMapper;
        $this->endpointMapper = $endpointMapper;
        $this->mappingMapper = $mappingMapper;
        $this->ruleMapper = $ruleMapper;
        $this->jobMapper = $jobMapper;
        $this->synchronizationMapper = $synchronizationMapper;
        $this->registerMapper = $registerMapper;
        $this->schemaMapper = $schemaMapper;
    }

    /**
     * Reset all mapping variables to their initial state and build new mappings
     */
    private function resetMappings(): void
    {
        // Reset mappings
        $this->mappings = [
            'endpoint' => ['idToSlug' => [], 'slugToId' => []],
            'synchronization' => ['idToSlug' => [], 'slugToId' => []],
            'mapping' => ['idToSlug' => [], 'slugToId' => []],
            'rule' => ['idToSlug' => [], 'slugToId' => []],
            'source' => ['idToSlug' => [], 'slugToId' => []],
            'register' => ['idToSlug' => [], 'slugToId' => []],
            'schema' => ['idToSlug' => [], 'slugToId' => []],
            'job' => ['idToSlug' => [], 'slugToId' => []]
        ];

        // Build all mappings at once
        $this->mappings['endpoint']['idToSlug'] = $this->endpointMapper->getIdToSlugMap();
        $this->mappings['endpoint']['slugToId'] = $this->endpointMapper->getSlugToIdMap();
        $this->mappings['job']['idToSlug'] = $this->jobMapper->getIdToSlugMap();
        $this->mappings['job']['slugToId'] = $this->jobMapper->getSlugToIdMap();
        $this->mappings['synchronization']['idToSlug'] = $this->synchronizationMapper->getIdToSlugMap();
        $this->mappings['synchronization']['slugToId'] = $this->synchronizationMapper->getSlugToIdMap();
        $this->mappings['mapping']['idToSlug'] = $this->mappingMapper->getIdToSlugMap();
        $this->mappings['mapping']['slugToId'] = $this->mappingMapper->getSlugToIdMap();
        $this->mappings['rule']['idToSlug'] = $this->ruleMapper->getIdToSlugMap();
        $this->mappings['rule']['slugToId'] = $this->ruleMapper->getSlugToIdMap();
        $this->mappings['source']['idToSlug'] = $this->sourceMapper->getIdToSlugMap();
        $this->mappings['source']['slugToId'] = $this->sourceMapper->getSlugToIdMap();
        $this->mappings['register']['idToSlug'] = $this->registerMapper->getIdToSlugMap();
        $this->mappings['register']['slugToId'] = $this->registerMapper->getSlugToIdMap();
        $this->mappings['schema']['idToSlug'] = $this->schemaMapper->getIdToSlugMap();
        $this->mappings['schema']['slugToId'] = $this->schemaMapper->getSlugToIdMap();
    }

    /**
     * Add an entity to the entity map
     *
     * @param Entity $entity The entity to add
     */
    private function addEntityToMap(Entity $entity): void
    {
        $id = $entity->getId();
        if ($id === null) {
            return;
        }

        // Determine entity type
        $type = match(true) {
            $entity instanceof Endpoint => 'endpoint',
            $entity instanceof Job => 'job',
            $entity instanceof Synchronization => 'synchronization',
            $entity instanceof Mapping => 'mapping',
            $entity instanceof Rule => 'rule',
            $entity instanceof Source => 'source',
            $entity instanceof Register => 'register',
            $entity instanceof Schema => 'schema',
            default => null
        };

        if ($type !== null) {
            $this->entityMap[$type][$id] = $entity;
        }
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
     * Export a source to OpenAPI format
     *
     * @param Source $source The source to export
     * @return array The OpenAPI source specification
     */
    private function exportSource(Source $source): array
    {
        $sourceArray = $source->jsonSerialize();
        unset($sourceArray['id'], $sourceArray['uuid']);
        return $sourceArray;
    }

    /**
     * Export an endpoint to OpenAPI format
     *
     * @param Endpoint $endpoint The endpoint to export
     * @return array The OpenAPI endpoint specification
     */
    private function exportEndpoint(Endpoint $endpoint): array
    {
        $endpointArray = $endpoint->jsonSerialize();
        unset($endpointArray['id'], $endpointArray['uuid']);

        // Replace IDs with slugs where applicable
        if (isset($endpointArray['inputMapping']) && isset($this->mappings['mapping']['idToSlug'][$endpointArray['inputMapping']])) {
            $endpointArray['inputMapping'] = $this->mappings['mapping']['idToSlug'][$endpointArray['inputMapping']];
        }
        if (isset($endpointArray['outputMapping']) && isset($this->mappings['mapping']['idToSlug'][$endpointArray['outputMapping']])) {
            $endpointArray['outputMapping'] = $this->mappings['mapping']['idToSlug'][$endpointArray['outputMapping']];
        }

        // Handle targetId based on targetType
        if (isset($endpointArray['targetId']) && isset($endpointArray['targetType'])) {
            switch ($endpointArray['targetType']) {
                case 'api':
                case 'database':
                    // For api/database targets, use source mapping
                    if (isset($this->mappings['source']['idToSlug'][$endpointArray['targetId']])) {
                        $endpointArray['targetId'] = $this->mappings['source']['idToSlug'][$endpointArray['targetId']];
                    }
                    break;

                case 'register/schema':
                    // For register/schema targets, split the ID and map both parts
                    if (str_contains($endpointArray['targetId'], '/')) {
                        [$registerId, $schemaId] = explode('/', $endpointArray['targetId']);
                        
                        // Map register ID to slug
                        if (isset($this->mappings['register']['idToSlug'][$registerId])) {
                            $registerSlug = $this->mappings['register']['idToSlug'][$registerId];
                        } else {
                            $registerSlug = $registerId; // Fallback to original ID if no mapping found
                        }

                        // Map schema ID to slug
                        if (isset($this->mappings['schema']['idToSlug'][$schemaId])) {
                            $schemaSlug = $this->mappings['schema']['idToSlug'][$schemaId];
                        } else {
                            $schemaSlug = $schemaId; // Fallback to original ID if no mapping found
                        }

                        // Combine the slugs
                        $endpointArray['targetId'] = $registerSlug . '/' . $schemaSlug;
                    }
                    break;
            }
        }

        return $endpointArray;
    }

    /**
     * Export a mapping to OpenAPI format
     *
     * @param Mapping $mapping The mapping to export
     * @return array The OpenAPI mapping specification
     */
    private function exportMapping(Mapping $mapping): array
    {
        $mappingArray = $mapping->jsonSerialize();
        unset($mappingArray['id'], $mappingArray['uuid']);

        // Replace IDs with slugs where applicable
        if (isset($mappingArray['source_id']) && isset($this->mappings['source']['idToSlug'][$mappingArray['source_id']])) {
            $mappingArray['source_id'] = $this->mappings['source']['idToSlug'][$mappingArray['source_id']];
        }
        if (isset($mappingArray['target_id']) && isset($this->mappings['source']['idToSlug'][$mappingArray['target_id']])) {
            $mappingArray['target_id'] = $this->mappings['source']['idToSlug'][$mappingArray['target_id']];
        }

        return $mappingArray;
    }

    /**
     * Export a rule to OpenAPI format
     *
     * @param Rule $rule The rule to export
     * @return array The OpenAPI rule specification
     */
    private function exportRule(Rule $rule): array
    {
        $ruleArray = $rule->jsonSerialize();
        unset($ruleArray['id'], $ruleArray['uuid']);

        // Replace IDs with slugs where applicable
        if (isset($ruleArray['source_id']) && isset($this->mappings['source']['idToSlug'][$ruleArray['source_id']])) {
            $ruleArray['source_id'] = $this->mappings['source']['idToSlug'][$ruleArray['source_id']];
        }
        if (isset($ruleArray['target_id']) && isset($this->mappings['source']['idToSlug'][$ruleArray['target_id']])) {
            $ruleArray['target_id'] = $this->mappings['source']['idToSlug'][$ruleArray['target_id']];
        }

        return $ruleArray;
    }

    /**
     * Export a job to OpenAPI format
     *
     * @param Job $job The job to export
     * @return array The OpenAPI job specification
     */
    private function exportJob(Job $job): array
    {
        $jobArray = $job->jsonSerialize();
        unset($jobArray['id'], $jobArray['uuid']);

        // Replace IDs with slugs in arguments JSON
        if (isset($jobArray['arguments'])) {
            $arguments = json_decode($jobArray['arguments'], true);
            if (is_array($arguments)) {
                if (isset($arguments['synchronizationId']) && isset($this->mappings['synchronization']['idToSlug'][$arguments['synchronizationId']])) {
                    $arguments['synchronizationId'] = $this->mappings['synchronization']['idToSlug'][$arguments['synchronizationId']];
                }
                if (isset($arguments['endpointId']) && isset($this->mappings['endpoint']['idToSlug'][$arguments['endpointId']])) {
                    $arguments['endpointId'] = $this->mappings['endpoint']['idToSlug'][$arguments['endpointId']];
                }
                if (isset($arguments['sourceId']) && isset($this->mappings['source']['idToSlug'][$arguments['sourceId']])) {
                    $arguments['sourceId'] = $this->mappings['source']['idToSlug'][$arguments['sourceId']];
                }
                $jobArray['arguments'] = json_encode($arguments);
            }
        }

        return $jobArray;
    }

    /**
     * Export a synchronization to OpenAPI format
     *
     * @param Synchronization $synchronization The synchronization to export
     * @return array The OpenAPI synchronization specification
     */
    private function exportSynchronization(Synchronization $synchronization): array
    {
        $syncArray = $synchronization->jsonSerialize();
        unset($syncArray['id'], $syncArray['uuid']);

        // Handle sourceId based on sourceType
        if (isset($syncArray['sourceId']) && isset($syncArray['sourceType'])) {
            switch ($syncArray['sourceType']) {
                case 'api':
                case 'database':
                    // For api/database sources, use source mapping
                    if (isset($this->mappings['source']['idToSlug'][$syncArray['sourceId']])) {
                        $syncArray['sourceId'] = $this->mappings['source']['idToSlug'][$syncArray['sourceId']];
                    }
                    break;

                case 'register/schema':
                    // For register/schema sources, split the ID and map both parts
                    if (str_contains($syncArray['sourceId'], '/')) {
                        [$registerId, $schemaId] = explode('/', $syncArray['sourceId']);
                        
                        // Map register ID to slug
                        if (isset($this->mappings['register']['idToSlug'][$registerId])) {
                            $registerSlug = $this->mappings['register']['idToSlug'][$registerId];
                        } else {
                            $registerSlug = $registerId; // Fallback to original ID if no mapping found
                        }

                        // Map schema ID to slug
                        if (isset($this->mappings['schema']['idToSlug'][$schemaId])) {
                            $schemaSlug = $this->mappings['schema']['idToSlug'][$schemaId];
                        } else {
                            $schemaSlug = $schemaId; // Fallback to original ID if no mapping found
                        }

                        // Combine the slugs
                        $syncArray['sourceId'] = $registerSlug . '/' . $schemaSlug;
                    }
                    break;
            }
        }

        // Handle targetId based on targetType
        if (isset($syncArray['targetId']) && isset($syncArray['targetType'])) {
            switch ($syncArray['targetType']) {
                case 'api':
                case 'database':
                    // For api/database targets, use source mapping
                    if (isset($this->mappings['source']['idToSlug'][$syncArray['targetId']])) {
                        $syncArray['targetId'] = $this->mappings['source']['idToSlug'][$syncArray['targetId']];
                    }
                    break;

                case 'register/schema':
                    // For register/schema targets, split the ID and map both parts
                    if (str_contains($syncArray['targetId'], '/')) {
                        [$registerId, $schemaId] = explode('/', $syncArray['targetId']);
                        
                        // Map register ID to slug
                        if (isset($this->mappings['register']['idToSlug'][$registerId])) {
                            $registerSlug = $this->mappings['register']['idToSlug'][$registerId];
                        } else {
                            $registerSlug = $registerId; // Fallback to original ID if no mapping found
                        }

                        // Map schema ID to slug
                        if (isset($this->mappings['schema']['idToSlug'][$schemaId])) {
                            $schemaSlug = $this->mappings['schema']['idToSlug'][$schemaId];
                        } else {
                            $schemaSlug = $schemaId; // Fallback to original ID if no mapping found
                        }

                        // Combine the slugs
                        $syncArray['targetId'] = $registerSlug . '/' . $schemaSlug;
                    }
                    break;
            }
        }

        // Handle mapping IDs
        if (isset($syncArray['sourceTargetMapping']) && isset($this->mappings['mapping']['idToSlug'][$syncArray['sourceTargetMapping']])) {
            $syncArray['sourceTargetMapping'] = $this->mappings['mapping']['idToSlug'][$syncArray['sourceTargetMapping']];
        }
        if (isset($syncArray['targetSourceMapping']) && isset($this->mappings['mapping']['idToSlug'][$syncArray['targetSourceMapping']])) {
            $syncArray['targetSourceMapping'] = $this->mappings['mapping']['idToSlug'][$syncArray['targetSourceMapping']];
        }

        return $syncArray;
    }

    /**
     * Build mappings for registers and schemas
     *
     * @param array<string> $registerIds Array of register IDs
     * @param array<string> $schemaIds Array of schema IDs
     */
    private function buildRegisterAndSchemaMappings(array $registerIds = [], array $schemaIds = []): void
    {
        // Get register slugs
        if (!empty($registerIds)) {
            $registers = $this->registerMapper->findAll(filters: ['id' => $registerIds]);
            foreach ($registers as $register) {
                $this->addEntityToMap($register);
            }
        }

        // Get schema slugs
        if (!empty($schemaIds)) {
            $schemas = $this->schemaMapper->findAll(filters: ['id' => $schemaIds]);
            foreach ($schemas as $schema) {
                $this->addEntityToMap($schema);
            }
        }
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
        // Reset all mappings
        $this->resetMappings();

        $components = [
            'components' => [],
        ];

        // Collect all entity IDs for batch processing
        $ruleIds = [];
        $mappingIds = [];
        $sourceIds = [];
        $endpointIds = [];
        $synchronizationIds = [];
        $registerIds = [$registerId];
        $schemaIds = [];

        // Get and organize endpoints if requested
        if ($includeEndpoints) {
            $endpoints = $this->endpointMapper->getByTarget(registerId: $registerId);
            foreach ($endpoints as $endpoint) {
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
        }

        // Get and organize synchronizations if requested
        if ($includeSynchronizations) {
            $synchronizations = $this->synchronizationMapper->getByTarget(
                registerId: $registerId,
                searchSource: $searchSource,
                searchTarget: $searchTarget
            );
            foreach ($synchronizations as $synchronization) {
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
        }

        // Remove duplicates from collected IDs
        $ruleIds = array_unique($ruleIds);
        $mappingIds = array_unique($mappingIds);
        $sourceIds = array_unique($sourceIds);
        $endpointIds = array_unique($endpointIds);
        $synchronizationIds = array_unique($synchronizationIds);
        $registerIds = array_unique($registerIds);
        $schemaIds = array_unique($schemaIds);

        // Build initial ID to slug maps for registers and schemas
        $this->buildRegisterAndSchemaMappings($registerIds, $schemaIds);

        // Batch fetch and export related entities
        if (!empty($mappingIds)) {
            $mappings = $this->mappingMapper->findAll(filters: ['id' => $mappingIds]);
            $indexedMappings = [];
            foreach ($mappings as $mapping) {
                $this->addEntityToMap($mapping);
                $indexedMappings[$mapping->getSlug()] = $this->exportMapping($mapping);
            }
            $components['components']['mappings'] = $indexedMappings;
        }

        if (!empty($sourceIds)) {
            $sources = $this->sourceMapper->findAll(filters: ['id' => $sourceIds]);
            $indexedSources = [];
            foreach ($sources as $source) {
                $this->addEntityToMap($source);
                $indexedSources[$source->getSlug()] = $this->exportSource($source);
            }
            $components['components']['sources'] = $indexedSources;
        }

        if (!empty($ruleIds)) {
            $rules = $this->ruleMapper->findAll(filters: ['id' => $ruleIds]);
            $indexedRules = [];
            foreach ($rules as $rule) {
                $this->addEntityToMap($rule);
                $indexedRules[$rule->getSlug()] = $this->exportRule($rule);
            }
            $components['components']['rules'] = $indexedRules;
        }

        // Export endpoints
        if ($includeEndpoints && !empty($endpointIds)) {
            $endpoints = $this->endpointMapper->findAll(filters: ['id' => $endpointIds]);
            $indexedEndpoints = [];
            foreach ($endpoints as $endpoint) {
                $this->addEntityToMap($endpoint);
                $indexedEndpoints[$endpoint->getSlug()] = $this->exportEndpoint($endpoint);
            }
            $components['components']['endpoints'] = $indexedEndpoints;
        }

        // Export synchronizations
        if ($includeSynchronizations && !empty($synchronizationIds)) {
            $synchronizations = $this->synchronizationMapper->findAll(filters: ['id' => $synchronizationIds]);
            $indexedSynchronizations = [];
            foreach ($synchronizations as $synchronization) {
                $this->addEntityToMap($synchronization);
                $indexedSynchronizations[$synchronization->getSlug()] = $this->exportSynchronization($synchronization);
            }
            $components['components']['synchronizations'] = $indexedSynchronizations;
        }

        // Get and export related jobs
        if (!empty($endpointIds) || !empty($synchronizationIds) || !empty($sourceIds)) {
            $jobs = $this->jobMapper->findByArgumentIds(
                synchronizationIds: $synchronizationIds,
                endpointIds: $endpointIds,
                sourceIds: $sourceIds
            );
            $indexedJobs = [];
            foreach ($jobs as $job) {
                $this->addEntityToMap($job);
                $indexedJobs[$job->getSlug()] = $this->exportJob($job);
            }
            $components['components']['jobs'] = $indexedJobs;
        }

        return $components;
    }
} 