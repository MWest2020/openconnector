<?php

namespace OCA\OpenConnector\Service;

use OCA\OpenRegister\Service\ObjectService;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Db\SchemaMapper;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Service for handling Software Catalogue operations.
 * 
 * This service provides functionality for managing software catalogue items,
 * including version management, synchronization, and event handling.
 * 
 * @category Service
 * @package  OCA\OpenConnector\Service
 * @version  1.0.0
 * @license  AGPL-3.0-or-later
 * @author   Conduction b.v.
 * @link     https://github.com/ConductionNL/OpenConnector
 */
class SoftwareCatalogueService
{
    /**
     * Array to store all elements from the model
     * 
     * @var array
     */
    private array $elements = [];

    /**
     * Array to store all relations from the model
     * 
     * @var array
     */
    private array $relations = [];

    /**
     * Array to store all nodes from the view
     * 
     * @var array
     */
    private array $nodes = [];

    /**
     * Constructor for SoftwareCatalogueService
     *
     * @param LoggerInterface $logger The logger instance
     * @param ObjectService $objectService The object service for accessing OpenRegister    
     * @param SchemaMapper $schemaMapper The schema mapper for accessing OpenRegister
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ObjectService $objectService,
        private readonly SchemaMapper $schemaMapper,
    ) {
    }

    /**
     * Extends a view by fetching it from the object store and processing its nodes in parallel.
     *
     * @param int $viewId The ID of the view to extend
     * @param int $modelId The ID of the model to use for extending
     * 
     * @return PromiseInterface A promise that resolves when all nodes have been processed
     * 
     * @throws DoesNotExistException If the view or model does not exist in the object store
     * 
     * @psalm-return PromiseInterface<void>
     */
    public function extendView(int $viewId, int $modelId): PromiseInterface
    {
        // Create a deferred object to manage the promise
        $deferred = new Deferred();

        // Get the OpenRegister service
        $openRegister = $this->objectService->getOpenRegisters();
        if ($openRegister === null) {
            $deferred->reject(new \Exception('OpenRegister service is not available'));
            return $deferred->promise();
        }

        // Fetch both view and model objects
        $viewPromise = $openRegister->find($viewId);
        $modelPromise = $openRegister->find($modelId);        

        // Lets get the extendView from the schema mapper by slug
        $extendViewSchema = $this->schemaMapper->find('extendView');
        $viewPromise['view'] =  $viewPromise['id'];
        unset($viewPromise['@self'], $viewPromise['id']);

        // Lets prepare the object service for saving to the extend view
        $this->objectService->setRegister($viewPromise['register']);
        $this->objectService->setSchema($extendViewSchema['id']);

        // Get elements and relations from the model
        $this->elements = $openRegister->findAll([
            'ids' => $modelPromise['elements']
        ]);

        $this->relations = $openRegister->findAll([
            'ids' => $modelPromise['relations']
        ]);

        $nodes = $openRegister->findAll([
            'ids' => $viewPromise['nodes']
        ]);

        // Process both objects
        \React\Promise\all([$viewPromise, $modelPromise, $nodes])
            ->then(function (array $results) use ($deferred, $openRegister) {
                [$view, $model, $nodes] = $results;

                if ($view === null || $model === null) {
                    $deferred->reject(new DoesNotExistException('View or model not found'));
                    return;
                }

                // Process each node in parallel using ReactPHP
                $promises = array_map([$this, 'extendNode'], $nodes);

                // Wait for all node processing to complete
                \React\Promise\all($promises)
                    ->then(function (array $extendedNodes) use ($deferred, $view) {
                        // Update the view with the extended nodes
                        $view['nodes'] = $extendedNodes;
                        
                        // Save the updated view
                        $this->objectService->save($view)
                            ->then(function () use ($deferred) {
                                $deferred->resolve();
                            })
                            ->otherwise(function ($error) use ($deferred) {
                                $deferred->reject($error);
                            });
                    })
                    ->otherwise(function ($error) use ($deferred) {
                        $deferred->reject($error);
                    });
            })
            ->otherwise(function ($error) use ($deferred) {
                $deferred->reject($error);
            });

        return $deferred->promise();
    }

    /**
     * Extends a single node using the global elements and relations.
     *
     * @param array $node The node to extend
     * 
     * @return PromiseInterface A promise that resolves with the extended node
     * 
     * @psalm-return PromiseInterface<array>
     */
    private function extendNode(array $node): PromiseInterface
    {
        return new Promise(function ($resolve, $reject) use ($node) {
            try {
                // Find matching element for this node
                $element = $this->findElementForNode($node);
                if ($element === null) {
                    $this->logger->warning('No matching element found for node', ['node' => $node]);
                    $resolve($node);
                    return;
                }

                // Find relations for this element
                $relations = $this->findRelationsForElement($element);
                
                // Extend the node with element properties
                $node['element'] = $element;
                $node['relations'] = $relations;

                // Check if the node has nested nodes that need to be extended
                if (isset($node['nodes']) && is_array($node['nodes'])) {
                    // Process nested nodes in parallel
                    $nestedPromises = array_map([$this, 'extendNode'], $node['nodes']);
                    
                    \React\Promise\all($nestedPromises)
                        ->then(function (array $extendedNestedNodes) use ($node, $resolve) {
                            $node['nodes'] = $extendedNestedNodes;
                            $resolve($node);
                        })
                        ->otherwise(function ($error) use ($reject) {
                            $reject($error);
                        });
                } else {
                    $resolve($node);
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to extend node: ' . $e->getMessage(), [
                    'exception' => $e,
                    'node' => $node
                ]);
                $reject($e);
            }
        });
    }

    /**
     * Finds the matching element for a given node
     *
     * @param array $node The node to find an element for
     * @return array|null The matching element or null if not found
     */
    private function findElementForNode(array $node): ?array
    {
        foreach ($this->elements as $element) {
            if ($element['id'] === $node['elementRef']) {
                return $element;
            }
        }
        return null;
    }

    /**
     * Finds all relations for a given element
     *
     * @param array $element The element to find relations for
     * @return array Array of relations
     */
    private function findRelationsForElement(array $element): array
    {
        $relations = [];
        // Iterate over each relation to find those associated with the given element
        foreach ($this->relations as $relation) {
            // Check if the element's ID matches the source or target of the relation
            if ($relation['source'] === $element['id'] || 
                $relation['target'] === $element['id']) {
                // Add the matching relation to the relations array
                $relations[] = $relation;
            }
        }
        // Return the array of found relations
        return $relations;
    }
}