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

                // zoeken naar nodes met elementref = $voorziening['identificatie']
                // voor elke node ene subnode maken met elementref = NEW id-UUID (type = element)
            }
        }
        
        foreach ($voorzieningGebruiken as $voorzieningGebruik) {
            $data['body']['views'][] = $voorzieningGebruik;
        }
        
        return $data;
    }
} 