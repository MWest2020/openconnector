<?php

namespace OCA\OpenConnector\Service;

use Exception;
use OCA\OpenConnector\Db\Rule;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use DateTime;

/**
 * Service for handling Rule processing in the OpenConnector app.
 * 
 * This service provides functionality to process various types of Rules,
 * applying transformations and business logic to data based on rule configurations.
 * 
 * Note: The custom rules functionality is experimental and subject to change.
 */
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
        $organisatieSchemaId = $config['OrganisatieSchema'];
        $voorzieningAanbodSchemaId = $config['VoorzieningAanbodSchema'];
        
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

        // // Fetch Organisatie objects
        // $openRegisters->setSchema($organisatieSchemaId);
        // $objectEntityMapper = $openRegisters->getMapper('objectEntity');
        // $organisaties = $objectEntityMapper->findAll(
        //     filters: [
        //         'register' => $registerId,
        //         'schema' => $organisatieSchemaId
        //     ]
        // );

        // // Fetch VoorzieningAanbod objects
        // $openRegisters->setSchema($voorzieningAanbodSchemaId);
        // $objectEntityMapper = $openRegisters->getMapper('objectEntity');
        // $voorzieningAanbod = $objectEntityMapper->findAll(
        //     filters: [
        //         'register' => $registerId,
        //         'schema' => $voorzieningAanbodSchemaId
        //     ]
        // );

        // Check if property definitions already exist
        $propertyIds = [
            'id-3093daaa7d93748d2e1aed59caa28192' => false, // Datum export
            'id-c3355444b6cb8fb34b62e241dd073043' => false, // SWC type
            'id-d222f71c083de2460625d0914174ee9d' => false, // Extern Pakket
            'id-e896da96437b4e4f821b3103f6b9c1b4' => false, // Omschrijving gebruik
        ];
        
        foreach ($data['body']['propertyDefinitions'] as $propertyDefinition) {
            if (isset($propertyDefinition['identifier']) === true && isset($propertyIds[$propertyDefinition['identifier']]) === true) {
                $propertyIds[$propertyDefinition['identifier']] = true;
            }
        }
        
        // Add property definitions that don't exist yet
        if ($propertyIds['id-3093daaa7d93748d2e1aed59caa28192'] === false) {
            $data['body']['propertyDefinitions'][] = [
                'identifier' => 'id-3093daaa7d93748d2e1aed59caa28192',
                'type' => 'string',
                'name' => 'Datum export'
            ];
        }
        
        if ($propertyIds['id-c3355444b6cb8fb34b62e241dd073043'] === false) {
            $data['body']['propertyDefinitions'][] = [
                'identifier' => 'id-c3355444b6cb8fb34b62e241dd073043',
                'type' => 'string',
                'name' => 'SWC type'
            ];
        }
        
        if ($propertyIds['id-d222f71c083de2460625d0914174ee9d'] === false) {
            $data['body']['propertyDefinitions'][] = [
                'identifier' => 'id-d222f71c083de2460625d0914174ee9d',
                'type' => 'string',
                'name' => 'Extern Pakket'
            ];
        }
        
        if ($propertyIds['id-e896da96437b4e4f821b3103f6b9c1b4'] === false) {
            $data['body']['propertyDefinitions'][] = [
                'identifier' => 'id-e896da96437b4e4f821b3103f6b9c1b4',
                'type' => 'string',
                'name' => 'Omschrijving gebruik'
            ];
        }

        // Add datum export
        $datumExport = new DateTime();
        $data['body']['properties'][] = [
            'propertyDefinitionRef' => 'id-3093daaa7d93748d2e1aed59caa28192', // Datum export
            'value' => $datumExport->format('Y-m-d H:i:s'),
            'value-lang' => 'nl',
        ];

        // Update Model name
        $data['body']['name'] = "Turfburg (test VNG Realisatie)";

        // Update filename
        $filename = $datumExport->format('d-m-Y') . '_GEMMA 2_' . $data['body']['name'] . '_ameff_model.xml';
        $data['headers']['Content-Disposition'] = 'attachment; filename="' . $filename . '"';

        // Get all folder keys
        foreach ($data['body']['organizations'] as $key => $organization) {
            if (isset($organization['label']) === true && $organization['label'] === "Application") {
                $applicationFolderKey = $key;
            }
        }

        // Add the Applicaties / Pakketten (Softwarecatalogus) folder
        $applicationFolderCount = count($data['body']['organizations'][$applicationFolderKey]['item']); // Index for adding to this organization/folder later on.
        $data['body']['organizations'][$applicationFolderKey]['item'][] = [
            'identifier' => "id-29ec7061-0aba-c9eb-25fd-7c9232e4f0",
            'label' => "Pakketten (Softwarecatalogus)",
            'label-lang' => "nl"
        ];

        // Count the total amount of children we are going to add for each referentieComponent.
        $newChildrenCount = [];
        foreach ($voorzieningen as $voorziening) {
            $voorziening = $voorziening->jsonSerialize();

            foreach ($voorziening['referentieComponenten'] as $referentieComponent) {
                if (isset($newChildrenCount[$referentieComponent]) === false) {
                    $newChildrenCount[$referentieComponent] = 0;
                }
                $newChildrenCount[$referentieComponent]++;
            }
        }

        // @TODO: replace this with a way to store these uuids somewhere.
        $uuidList = [
            [
                "a1b2c3d4-e5f6-4321-87a9-b1c2d3e4f5g6",
                "b2c3d4e5-f6a1-4321-87a9-c3d4e5f6a1b2",
                "c3d4e5f6-a1b2-4321-87a9-d4e5f6a1b2c3"
            ],
            [
                "d4e5f6a1-b2c3-4321-87a9-e5f6a1b2c3d4",
                "e5f6a1b2-c3d4-4321-87a9-f6a1b2c3d4e5",
                "f6a1b2c3-d4e5-4321-87a9-a1b2c3d4e5f6"
            ],
            [
                "a2b3c4d5-e6f7-5432-98ba-c2d3e4f5g6h7",
                "b3c4d5e6-f7a2-5432-98ba-d3e4f5g6h7a2",
                "c4d5e6f7-a2b3-5432-98ba-e4f5g6h7a2b3"
            ]
        ];

        // Add voorzieningen (pakketten/applicaties) to response data
        foreach ($voorzieningen as $voorzieningIndex => $voorziening) {
            $voorziening = $voorziening->jsonSerialize();
            $elementId = "id-{$voorziening['id']}";

            // Add voorziening to Application folder
            $data['body']['organizations'][$applicationFolderKey]['item'][$applicationFolderCount]['item'][] = [
                'identifierRef' => $elementId
            ];

            // Add voorziening to elements
            $data['body']['elements'][] = [
                'identifier' => $elementId,
                'name' => $voorziening['naam'],
                'name-lang' => 'nl',
                'documentation' => $voorziening['beschrijving'],
                'documentation-lang' => 'nl',
                'type' => 'ApplicationComponent',
                'properties' => [
                    0 => [
                        'propertyDefinitionRef' => 'id-c3355444b6cb8fb34b62e241dd073043', // SWC type
                        'value' => 'Pakket',
                    ],
                    1 => [
                        'propertyDefinitionRef' => 'propid-2', // Object ID
                        'value' => $voorziening['id'],
                    ],
                    2 => [
                        'propertyDefinitionRef' => 'propid-39', // URL
                        'value' => '',
                    ],
                    3 => [
                        'propertyDefinitionRef' => 'id-d222f71c083de2460625d0914174ee9d', // Extern Pakket
                        'value' => 'n',
                    ],
                    4 => [
                        'propertyDefinitionRef' => 'id-e896da96437b4e4f821b3103f6b9c1b4', // Omschrijving gebruik
                        'value' => '',
                    ],
                ],
            ];

            foreach ($voorziening['referentieComponenten'] as $refCompIndex => $referentieComponent) {
                // Search for nodes with elementRef matching the voorzienings identificatie and create subnodes
                if (isset($data['body']['views']) && is_array($data['body']['views'])) {
                    foreach ($data['body']['views'] as &$view) {
                        if (isset($view['nodes']) && is_array($view['nodes'])) {
                            // Make sure identificatie exists
                            $identificatie = $referentieComponent;
                            $this->processNodes($view['nodes'], $identificatie, $elementId, $newChildrenCount[$referentieComponent]);
                        }
                    }
                }

                // Add relations between voorziening and referentiecomponent
                if ($voorzieningIndex <= 2 && $refCompIndex <= 2) {
                    $relationUuid = $uuidList[$voorzieningIndex][$refCompIndex];
                }
                $relationId = "id-{$relationUuid}";
                $data['body']['relationships'][] = [
                    'identifier' => $relationId,
                    'source' => $elementId,
                    'target' => $referentieComponent,
                    'type' => 'Specialization',
                    'properties' => [
                        0 => [
                            'propertyDefinitionRef' => 'propid-2', // Object ID
                            'value' => $relationUuid,
                        ],
                    ],
                ];
            }
        }
        
        // foreach ($voorzieningGebruiken as $voorzieningGebruik) {
        //     $voorzieningGebruik = $voorzieningGebruik->jsonSerialize();
        // }

        // // Add organisaties (leveranciers) to response data
        // foreach ($organisaties as $organisatie) {
        //     $organisatie = $organisatie->jsonSerialize();

        //     $newUuid = Uuid::v4();
        //     $elementId = "id-{$newUuid}";
        //     $data['body']['elements'][] = [
        //         'identifier' => $elementId,
        //         'name' => $organisatie['naam'],
        //         'name-lang' => 'nl',
        //         'documentation' => $organisatie['beschrijving'],
        //         'documentation-lang' => 'nl',
        //         'type' => 'BusinessActor',
        //         'properties' => [
        //             0 => [
        //                 'propertyDefinitionRef' => 'id-c3355444b6cb8fb34b62e241dd073043', // SWC type
        //                 'value' => 'Leverancier',
        //             ],
        //             1 => [
        //                 'propertyDefinitionRef' => 'propid-2', // Object ID
        //                 'value' => $newUuid,
        //             ],
        //             2 => [
        //                 'propertyDefinitionRef' => 'propid-39', // URL
        //                 'value' => '',
        //             ],
        //         ],
        //     ];
        // }

        // foreach ($voorzieningAanbod as $voorzieningAanbod) {
        //     $voorzieningAanbod = $voorzieningAanbod->jsonSerialize();
        // }
        
        return $data;
    }

    /**
     * Recursively processes nodes and their nested nodes to find matches and create subnodes
     * 
     * @param array &$nodes The nodes to process
     * @param string|null $matchIdentificatie The identificatie to match against elementRef
     * @param string $newElementId The ID of the new element to reference
     * @param int $totalNewChildren The total amount of children we are going to add for the current $matchIdentificatie.
     * 
     * @return void
     */
    private function processNodes(array &$nodes, ?string $matchIdentificatie, string $newElementId, int $totalNewChildren): void
    {
        // If matchIdentificatie is null, return early
        if ($matchIdentificatie === null) {
            return;
        }
        
        // Loop through each node in the array
        foreach ($nodes as $index => &$node) {
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

                // Count the total amount of children including the new subnodes and store it in the node array.
                if (isset($node['totalChildren']) === false) {
                    $node['totalChildren'] = count($node['nodes']) + $totalNewChildren;
                }

                // Count the total amount of children including the new subnode
                $totalChildren = $node['totalChildren'];

                // Calculate the index of the child node
                $childIndex = count($node['nodes']) + 1;

                $parentPadding = 20;
                $childSpacing = 8;
                $parentWidth = $node['position']['w'] - ($parentPadding * 2);
                $parentHeight = $node['position']['h'] - ($parentPadding * 2);
                
                // Calculate child width: 
                // Available width = (parent width - left/right padding - spacing between children) / number of children
                $childWidth = min(
                    ($parentWidth - ($childSpacing * ($totalChildren - 1))) / $totalChildren, 
                    120 // Maximum width of 120px
                );

                // Child height at least 30px and no more than 100px
                $childHeight = max(30, min($parentHeight, 100));
                // If there is another child node, use the height of that child node, but no more than the parent height.
                if (isset($node['nodes'][0]) === true) {
                    $childHeight = max(30, min($node['nodes'][0]['position']['h'], $parentHeight));
                }

                // Calculate X position:
                // Start from parent's left edge + padding + (child's index × (child width + spacing))
                $absoluteX = $node['position']['x'] + $parentPadding + (($childIndex - 1) * ($childWidth + $childSpacing));

                // Calculate Y position:
                // Position from bottom of parent
                $absoluteY = $node['position']['y'] + ($node['position']['h'] - $childHeight - 10);
                
                // Add subnode with reference to new element
                $node['nodes'][] = [
                    'identifier' => $subnodeId,
                    'elementRef' => $newElementId,
                    'type' => 'Element',
                    'position' => [
                        'x' => (int) $absoluteX,
                        'y' => (int) $absoluteY,
                        'w' => (int) $childWidth,
                        'h' => (int) $childHeight
                    ],
                    'style' => [
                        'lineWidth' => "1",
                        'fillColor' => [
                            'r' => "100",
                            'g' => "149",
                            'b' => "237",
                            'a' => "100"
                        ],
                        'lineColor' => [
                            'r' => "0",
                            'g' => "0",
                            'b' => "0",
                            'a' => "100"
                        ],
                        'font' => [
                            'name' => 'Arial',
                            'size' => "10",
                            'style' => 'plain'
                        ],
                        'color' => [
                            'r' => "0",
                            'g' => "0",
                            'b' => "0"
                        ] // Somehow when all 3 are 0, color is removed from the style array...
                    ]
                ];
            }
            
            // Process nested nodes recursively if they exist
            if (isset($node['nodes']) === true && is_array($node['nodes']) === true) {
                // Call this function recursively on the nested nodes
                $this->processNodes($node['nodes'], $matchIdentificatie, $newElementId, $totalNewChildren);
            }
        }
    }
} 