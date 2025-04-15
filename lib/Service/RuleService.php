<?php

namespace OCA\OpenConnector\Service;

use Exception;
use OCA\OpenConnector\Db\Rule;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class RuleService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ObjectService $objectService
    )
    {
    }

    /**
     * Process a custom rule
     *
     * @param Rule $rule The rule to process
     * @param array $data The data to process
     *
     * @return array The updated data array.
     */
    public function processCustomRule(Rule $rule, array $data): array
    {
        $type = $rule->getConfiguration()['type'];

        // Process custom rule based on type
        $data = match ($type) {
            'softwareCatalogus' => $this->processSoftwareCatalogusRule($rule, $data),
            default => throw new Exception('Unsupported custom rule type: ' . $rule->getType()),
        };

        return $data;
    }
    
    /**
     * Process a Software Catalogus rule
     *
     * @param Rule $rule The rule to process
     * @param array $data The data to process
     *
     * @return array The updated data array
     */
    private function processSoftwareCatalogusRule(Rule $rule, array $data): array
    {
        $config = $rule->getConfiguration()['configuration'];
        
        // Get register ID
        $registerId = $config['register'];
        
        // Get schema IDs
        $voorzieningSchemaId = $config['VoorzieningSchema'];
        $voorzieningGebruikSchemaId = $config['VoorzieningGebruikSchema'];
        
        // Get OpenRegisters instance and set the register
        $openRegisters = $this->objectService->getOpenRegisters();
        $openRegisters->setRegister($registerId);
        
        // Fetch Voorziening objects
        $openRegisters->setSchema($voorzieningSchemaId);
        $objectEntityMapper = $openRegisters->getMapper('objectEntity');
        $voorzieningen = $objectEntityMapper->findAll(
            filters: [
                'register' => $registerId,
                'schema' => $voorzieningSchemaId
            ]
        );
        
        // Fetch VoorzieningGebruik objects
        $openRegisters->setSchema($voorzieningGebruikSchemaId);
        $objectEntityMapper = $openRegisters->getMapper('objectEntity');
        $voorzieningGebruiken = $objectEntityMapper->findAll(
            filters: [
                'register' => $registerId,
                'schema' => $voorzieningGebruikSchemaId
            ]
        );

        // Add to response data
        foreach ($voorzieningen as $voorziening) {
            $voorziening = $voorziening->jsonSerialize();
            foreach ($voorziening['referentieComponenten'] as $referentieComponent) {
                $newUuid = Uuid::v4();
                $elementId = "id-{$newUuid}";
                $data['body']['results'][0]['elements'][] = [
                    'identificatie' => $elementId,
                    'name' => $voorziening['naam'],
                    'name-lang' => 'nl',
                    'documentation' => $voorziening['beschrijving'],
                    'documentation-lang' => 'nl',
                    'type' => 'Constraint',
                    // 'properties' => [
                    //     'todo' => '',
                    // ],
                ];

                // Search for nodes with elementRef matching the voorzienings identificatie and create subnodes
                if (isset($data['body']['results'][0]['views']) && is_array($data['body']['results'][0]['views'])) {
                    foreach ($data['body']['results'][0]['views'] as &$view) {
                        if (isset($view['nodes']) && is_array($view['nodes'])) {
                            // Make sure identificatie exists
                            $identificatie = $referentieComponent;
                            $this->processNodes($view['nodes'], $identificatie, $elementId);
                        }
                    }
                }
            }
        }
        
        // foreach ($voorzieningGebruiken as $voorzieningGebruik) {
        //     $data['body']['views'][] = $voorzieningGebruik;
        // }
        
        return $data;
    }

    /**
     * Recursively processes nodes and their nested nodes to find matches and create subnodes
     * 
     * @param array &$nodes The nodes to process
     * @param string|null $matchIdentificatie The identificatie to match against elementRef
     * @param string $newElementId The ID of the new element to reference
     * 
     * @return void
     */
    private function processNodes(array &$nodes, ?string $matchIdentificatie, string $newElementId): void
    {
        // If matchIdentificatie is null, return early
        if ($matchIdentificatie === null) {
            return;
        }
        
        // Loop through each node in the array
        foreach ($nodes as &$node) {
            // Check if current node has an elementRef property and if it matches the target identificatie
            if (isset($node['elementRef']) === true && $node['elementRef'] === $matchIdentificatie) {
                // Create a subnode with reference to the newly created element
                $subnodeUuid = Uuid::v4();
                $subnodeId = "id-{$subnodeUuid}";
                
                // Check if the nodes array doesn't exist or is not an array
                if (isset($node['nodes']) === false || is_array($node['nodes']) === false) {
                    // Initialize the nodes array if it doesn't exist properly
                    $node['nodes'] = [];
                }
                
                // Add subnode with reference to new element
                $node['nodes'][] = [
                    'identifier' => $subnodeId,
                    'elementRef' => $newElementId,
                    'type' => 'element',
                    'position' => [
                        'x' => 0,
                        'y' => 0
                    ],
                    'style' => []
                ];
            }
            
            // Process nested nodes recursively if they exist
            if (isset($node['nodes']) === true && is_array($node['nodes']) === true) {
                // Call this function recursively on the nested nodes
                $this->processNodes($node['nodes'], $matchIdentificatie, $newElementId);
            }
        }
    }
} 