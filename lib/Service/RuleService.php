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
        $voorzieningenMapper = $openRegisters->getMapper('objectEntity', $registerId, $voorzieningSchemaId);
        $voorzieningen = $voorzieningenMapper->findAll();
        
        // Fetch VoorzieningGebruik objects
        $openRegisters->setSchema($voorzieningGebruikSchemaId);
        $voorzieningGebruikMapper = $openRegisters->getMapper('objectEntity', $registerId, $voorzieningGebruikSchemaId);
        $voorzieningGebruiken = $voorzieningGebruikMapper->findAll();
        
        // Add to response data
        foreach ($voorzieningen as $voorziening) {
            $voorziening = $voorziening->jsonSerialize();
            foreach ($voorziening['referentieComponenten'] as $referentieComponent) {
                $newUuid = Uuid::v4();
                $elementId = "id-{$newUuid}";
                $data['body']['elements'][] = [
                    'identificatie' => $elementId,
                    'name' => $voorziening['title'],
                    'name-lang' => 'nl',
                    'documentation' => $voorziening['beschrijving'],
                    'documentation-lang' => 'nl',
                    'type' => 'Constraint',
                    // 'properties' => [
                    //     'todo' => '',
                    // ],
                ];

                // Search for nodes with elementRef matching the voorzienings identificatie and create subnodes
                if (isset($data['body']['views']) && is_array($data['body']['views'])) {
                    foreach ($data['body']['views'] as &$view) {
                        if (isset($view['nodes']) && is_array($view['nodes'])) {
                            foreach ($view['nodes'] as &$node) {
                                if (isset($node['elementRef']) && $node['elementRef'] === $voorziening['identificatie']) {
                                    // Create a subnode with reference to the newly created element
                                    $subnodeUuid = Uuid::v4();
                                    $subnodeId = "id-{$subnodeUuid}";
                                    
                                    // If nodes doesn't exist yet, initialize it
                                    if (isset($node['nodes']) === false || is_array($node['nodes']) === false) {
                                        $node['nodes'] = [];
                                    }
                                    
                                    // Add subnode with reference to new element
                                    $node['nodes'][] = [
                                        'identifier' => $subnodeId,
                                        'elementRef' => $elementId,
                                        'type' => 'element',
                                        'position' => [
                                            'x' => 0,
                                            'y' => 0
                                        ],
                                        'style' => []
                                    ];
                                }
                            }
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
} 