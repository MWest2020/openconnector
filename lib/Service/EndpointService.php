<?php

namespace OCA\OpenConnector\Service;

use Adbar\Dot;
use Exception;
use JWadhams\JsonLogic;
use OC\AppFramework\Http;
use OC\AppFramework\Http\Request;
use OC\AppFramework\Http\RequestId;
use OC\Config;
use OC\Files\Node\File;
use OC\Security\SecureRandom;
use OCA\OpenConnector\Db\EndpointMapper;
use OCA\OpenConnector\Db\SourceMapper;
use OCA\OpenConnector\Exception\AuthenticationException;
use OCA\OpenConnector\Service\AuthenticationService;
use OCA\OpenConnector\Service\CallService;
use OCA\OpenConnector\Service\MappingService;
use OCA\OpenConnector\Service\ObjectService;
use OCA\OpenConnector\Service\RuleService;
use OCA\OpenConnector\Db\Source;
use OCA\OpenConnector\Db\Endpoint;
use OCA\OpenConnector\Db\Mapping;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use OCA\OpenRegister\Exception\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCA\OpenRegister\Db\ObjectEntity;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Symfony\Component\Uid\Uuid;
use ValueError;
use OCA\OpenConnector\Db\Rule;
use OCA\OpenConnector\Db\RuleMapper;
use Psr\Container\ContainerInterface;
use DateTime;

/**
 * Service class for handling endpoint requests
 *
 * This class provides functionality to handle requests to endpoints, either by
 * connecting to a schema within a register or by proxying to a source.
 */
class EndpointService
{

    private const UNSET_PARAMETERS = [
        '_parameters',
        '_utility',
        '_method',
        '_headers',
        '_route',
        '_path'
    ];

    /**
     * Constructor for EndpointService
     *
     * @param ObjectService $objectService Service for handling object operations
     * @param CallService $callService Service for making external API calls
     * @param LoggerInterface $logger Logger interface for error logging
     */
    public function __construct(
        private readonly ObjectService   $objectService,
        private readonly CallService     $callService,
        private readonly LoggerInterface $logger,
        private readonly IURLGenerator   $urlGenerator,
        private readonly MappingService  $mappingService,
        private readonly EndpointMapper  $endpointMapper,
        private readonly RuleMapper      $ruleMapper,
        private readonly IConfig $config,
        private readonly IAppConfig $appConfig,
        private readonly StorageService $storageService,
        private readonly AuthorizationService $authorizationService,
        private readonly ContainerInterface $containerInterface,
        private readonly SynchronizationService $synchronizationService,
        private readonly RuleService $ruleService,
    )
    {
    }

    /**
     * Handles incoming requests to endpoints
     *
     * This method determines how to handle the request based on the endpoint configuration.
     * It either routes to a schema within a register or proxies to an external source.
     *
     * @param Endpoint $endpoint The endpoint configuration to handle
     * @param IRequest $request The incoming request object
     * @param string $path The specific path or sub-route being requested
     *
     * @return JSONResponse Response containing the result
     * @throws Exception When endpoint configuration is invalid
     */
    public function handleRequest(Endpoint $endpoint, IRequest $request, string $path): Response
    {
        $errors = $this->checkConditions($endpoint, $request);

        if ($errors !== []) {
            return new JSONResponse(['error' => 'The following parameters are not correctly set', 'fields' => $errors], 400);
        }

        try {

            // Process initial data
            $responseBody = $this->parseContent(
                request: $request,

            );

            if ($responseBody == '') {
                $responseBody = [];
            }

            $currentDate = (new DateTime())->format('c');

            // This is double becuase mapping needs it in body but other rules seek directly in data.

            $incomingMethod = $request->getMethod();
            $incomingHeaders = $this->getHeaders($request->server, true);
            $incomingParams = array_merge($request->getParams(), $responseBody);

            $incomingData = [
                'method' => $incomingMethod,
                'headers' => $incomingHeaders,
                'params' => $incomingParams
            ];


            $data = [
                'utility' => [
                    'currentDate' => $currentDate
                ],
                'parameters' => array_merge($incomingParams, $this->getPathParameters($endpoint->getEndpointArray(), $path)),
                'headers' => $incomingHeaders,
                'path' => $path,
                'method' => $incomingMethod,
                'body' => array_merge([
                    '_utility' => [
                        'currentDate' => $currentDate
                    ],
                    '_parameters' => $incomingParams,
                    '_headers' => $incomingHeaders,
                    '_path' => $path,
                    '_method' => $incomingMethod,
                ], $responseBody)];

            // Process rules before handling the request
            $ruleResult = $this->processRules(
                endpoint: $endpoint,
                request: $request,
                data: $data,
                timing: 'before'
            );

            if ($ruleResult instanceof JSONResponse === true) {
                return $ruleResult;
            }

            // Update request data with rule processing results
            $request = $this->updateRequestWithRuleData(request: $request, ruleData: $ruleResult, incomingData: $incomingData);

            // Check if endpoint connects to a schema
            if ($endpoint->getTargetType() === 'register/schema') {
                // Handle CRUD operations via ObjectService
                $result = $this->handleSchemaRequest($endpoint, $request, $path);

                // Process initial data
                $data = [
                    'utility' => [
                        'currentDate' => (new DateTime())->format('c')
                    ],
                    'parameters' => $request->getParams(),
                    'requestHeaders' => $this->getHeaders($request->server, true),
                    'headers' => $result->getHeaders(),
                    'path' => $path,
                    'method' => $request->getMethod(),
                    'body' => $result->getData(),
                ];

                $ruleResult = $this->processRules(
                    endpoint: $endpoint,
                    request: $request,
                    data: $data,
                    timing: 'after',
                    objectId: $result->getData()['id'] ?? null
                );

                if ($ruleResult instanceof Response === true) {
                    return $ruleResult;
                }

				if ($result->getStatus() !== 200 && $result->getStatus() !== 201) {
					return new JSONResponse(data: $ruleResult['body'], statusCode: $result->getStatus(), headers: $ruleResult['headers']);
				}

                // Set the proper status code for the method.
                //@TODO: we might want an override from rule processing.
                switch ($incomingMethod) {
                    case 'POST':
                        $statusCode = Http::STATUS_CREATED;
                        break;
                    case 'DELETE':
                        $statusCode = Http::STATUS_NO_CONTENT;
                        break;
                    case 'GET':
                    case 'PUT':
                    case 'PATCH':
                    default:
                        $statusCode = Http::STATUS_OK;
                        break;
                }

                return new JSONResponse(data: $ruleResult['body'], statusCode: $statusCode, headers: $ruleResult['headers']);
            }

            // Check if endpoint connects to a source
            if ($endpoint->getTargetType() === 'api') {
                // Proxy request to source via CallService
                return $this->handleSourceRequest($endpoint, $request);
            }

            // Invalid endpoint configuration
            throw new Exception('Endpoint must specify either a schema or source connection');

        } catch (Exception $e) {
            $this->logger->error('Error handling endpoint request: ' . $e->getMessage());
            return new JSONResponse(
                ['error' => $e->getMessage(),
                    'trace' => $e->getTrace()],
                400
            );
        }
    }

    /**
     * Parses a path to get the parameters in a path.
     *
     * @param array $endpointArray The endpoint array from an endpoint object.
     * @param string $path The path called by the client.
     *
     * @return array The parsed path with the fields having the correct name.
     */
    private function getPathParameters(array $endpointArray, string $path): array
    {
        $pathParts = explode(separator: '/', string: $path);

        $endpointArrayNormalized = array_map(
            function ($item) {
                return str_replace(
                    search: ['{{', '{{ ', '}}', '}}'],
                    replace: '',
                    subject: $item
                );
            },
            $endpointArray);

        try {
            $pathParams = array_combine(
                keys: $endpointArrayNormalized,
                values: $pathParts
            );
        } catch (ValueError $error) {
            array_pop($endpointArrayNormalized);
            $pathParams = array_combine(
                keys: $endpointArrayNormalized,
                values: $pathParts
            );
        }

        return $pathParams;
    }

    /**
     * Replaces internal pointers with urls and ids by endpoint urls.
     *
     * @param QBMapper|\OCA\OpenRegister\Service\ObjectService $mapper The mapper used to find objects.
     * @param ObjectEntity|null $object The object to substitute pointers in.
     * @param array $serializedObject The serialized object (if the object itself is not available).
     *
     * @return array|null The serialized object including substituted pointers.
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function replaceInternalReferences(
        QBMapper|\OCA\OpenRegister\Service\ObjectService $mapper,
        ?ObjectEntity $object = null,
        array $serializedObject = [],
		array $extend = []
    ): array
    {
        if ($serializedObject === [] && $object !== null) {
            $serializedObject = $object->jsonSerialize();
        } else if ($serializedObject === null) {
            return $serializedObject;
        } else {
            $object = $mapper->find($serializedObject['id']);
        }

        $uses = (new Dot($object->jsonSerialize()))->flatten();
//
//        if(isset($serializedObject) === true && !empty($serializedObject['@self']['relations'])) {
//            $uses = $serializedObject['@self']['relations'];
//        }

        $useUrls = [];

        $uuidToUrlMap = [];
        // Initiate schemaMapper here once for performance
        $schemaMapper = $this->containerInterface->get('OCA\OpenRegister\Db\SchemaMapper');
        $schema        = $schemaMapper->find($object->getSchema());

        // Find property names that are uris
        $validUriProperties = [];
        foreach ($schema->getProperties() as $propertyName => $property) {
            if (isset($property['objectConfiguration']['handling']) === true && $property['objectConfiguration']['handling'] === 'uri') {
                $validUriProperties[] = $propertyName;
            }

            if (isset($property['format']) === true && $property['format'] === 'uri') {
                $validUriProperties[] = $propertyName;
            }
        }

        foreach ($uses as $key => $use) {
            $baseKey = explode('.', $key, 2)[0];
            // Skip if the key (or its base form) is not in the valid URI properties
            if (in_array(needle: $baseKey, haystack: $validUriProperties) === false) {
                continue;
            }

            if (empty($use) === true) {
                continue;
            }

            if (Uuid::isValid(uuid: $use) === true) {
                $useId = $use;
            } else if (
                str_contains(haystack: $use, needle: 'localhost') === true
                || str_contains(haystack: $use, needle: 'nextcloud.local') === true
                || str_contains(haystack: $use, needle: $this->urlGenerator->getBaseUrl()) === true
            ) {
                $explodedUrl = explode(separator: '/', string: $use);
                $useId = end($explodedUrl);
            } else {
                unset($uses[$key]);
                continue;
            }

            try {
                $generatedUrl = $this->generateEndpointUrl(id: $useId, parentIds: [$object->getUuid()], schemaMapper: $schemaMapper);
                $uuidToUrlMap[$useId] = $generatedUrl;
                $useUrls[] = $generatedUrl;
            } catch (Exception $exception) {
                continue;
            }
        }


        // @TODO: correct rewriting self url. This has to be fixed with issue CONNECTOR-314
        // Add self object URI mapping
//        $uuidToUrlMap[$object->getUuid()] = $this->generateEndpointUrl(id: $object->getUuid(), schemaMapper: $schemaMapper);
        $uuidToUrlMap[$object->getUri()] = $this->generateEndpointUrl(id: $object->getUuid(), schemaMapper: $schemaMapper);

        // @TODO: temporary fix for download endpoints. This has to be fixed with issue CONNECTOR-314
        $uuidToUrlMap[$object->getUri(). '/download'] = $this->generateEndpointUrl(id: $object->getUuid(), schemaMapper: $schemaMapper). '/download';

        // Replace UUIDs in serializedObject recursively
        $serializedObject = $this->replaceUuidsInArray($serializedObject, $uuidToUrlMap, extend: $this->reduceExtendKeys($extend));

        return $serializedObject;
    }

	/**
	 * Create a reduced list of extend keys and extends for checking purposes
	 *
	 * @param array $extend The original extend array
	 * @return array The reduced extend array
	 */
	private function reduceExtendKeys(array $extend): array
	{
		$reducedKeys = [];

		foreach($extend as $key => $value) {
			if(str_contains(haystack: $value, needle: '.') === false) {
				$reducedKeys[] = $key;
				continue;
			}


			[$prefix, $newKey] = explode('.', $value, 2);

			if(($newPrefix = array_search(needle: $prefix, haystack: $extend)) !== false) {
				$reducedKeys[] = $newPrefix .'.'. $newKey;
				continue;
			}
			$reducedKeys[] = $value;
		}

		$reducedExtend = array_combine($reducedKeys, $reducedKeys);

		$serialized = (new Dot($reducedExtend, parse: true))->jsonSerialize();
		return $serialized;
	} //end reduceExtendKeys()

    /**
     * Recursively replaces UUIDs in an array with their corresponding URLs.
     *
     * This function traverses the given array and replaces any UUID values found in the
     * mapping array ($uuidToUrlMap) with their associated URLs. It ensures that 'id' and 'uuid'
     * fields remain unchanged.
     *
     * @param array $data The input array that may contain UUIDs.
     * @param array $uuidToUrlMap An associative array mapping UUIDs to URLs.
     * @param bool  $isRelatedObject Are we currently iterating through a related object.
     *
     * @return array The modified array with UUIDs replaced by URLs.
     */
    private function replaceUuidsInArray(array $data, array $uuidToUrlMap, ?bool $isRelatedObject = false, array $extend = []): array {
        foreach ($data as $key => $value) {

            // Don't check @self
            if ($key === '@self') {
                continue;
            }

            // If in array of multiple objects and has id
            if (is_array($value) === true && isset($value['id']) === true && isset($uuidToUrlMap[$value['id']]) === true && key_exists(key: $key, array: $extend) === false) {
                $data[$key] = $uuidToUrlMap[$value['id']];
                continue;
            }

            // If related object and has id
            if ($isRelatedObject === true && $key === 'id' && isset($uuidToUrlMap[$value]) === true && key_exists(key: $key, array: $extend) === false) {
                $data[$key] = $uuidToUrlMap[$value];
                continue;
            }

            // Never replace 'id' or 'uuid' fields but only in previous checks
            if ($key === 'id' || $key === 'uuid') {
                continue;
            }

			if (is_array($value) === true && array_is_list($value) === true) {
				$extend[$key] = array_fill(0, count($value), $extend[$key]);
			}


            if (is_array($value) === true && empty($value) === false) {
                $data[$key] = $this->replaceUuidsInArray(
					data: $value, uuidToUrlMap: $uuidToUrlMap,
					isRelatedObject: true,
					extend: isset($extend[$key]) === true && is_array($extend[$key]) === true ? $extend[$key] : $extend
				);
            } elseif (is_string($value) === true && isset($uuidToUrlMap[$value]) === true) {
                $data[$key] = $uuidToUrlMap[$value];
            }
        }

        return $data;
    }

    /**
     * Inverse of replaceInternalReferences, rewriting external references to internal references for query parameters.
     *
     * @param array $parameters The incoming request parameters.
     * @param \OCA\OpenRegister\Service\ObjectService|QBMapper $mapper The ObjectService containing the request schema.
     *
     * @return array The updated request parameters.
     *
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     */
    private function rewriteExternalReferences(array $parameters, \OCA\OpenRegister\Service\ObjectService|QBMapper $mapper): array
    {
        $schemaMapper = $this->containerInterface->get('OCA\OpenRegister\Db\SchemaMapper');
        $schema        = $schemaMapper->find($mapper->getSchema());

        $rewriteParameters = array_intersect(array_keys($parameters), array_keys($schema->getProperties()));

        foreach($rewriteParameters as $rewriteParameter) {
            if (
                filter_var($parameters[$rewriteParameter], FILTER_VALIDATE_URL) === false
				&& in_array(parse_url($parameters[$rewriteParameter], PHP_URL_HOST), $this->containerInterface->getParameter('kernel.trusted_hosts')) === false
            ) {
                continue;
            }

            $parsedPath = parse_url($parameters[$rewriteParameter], PHP_URL_PATH);
            $parsedPath = substr($parsedPath, 33);
            $endpoints = $this->endpointMapper->findByPathRegex(
                path: $parsedPath,
                method: 'GET'
            );

            if(count($endpoints) < 1) {
                continue;
            }

            $endpoint = array_shift($endpoints);

            $pathArray = $this->getPathParameters(endpointArray: $endpoint->getEndpointArray(), path: $parsedPath);
            $parameters[$rewriteParameter] = end($pathArray);

        }

        return $parameters;
    }

    /**
     * Fetch objects for the endpoint.
     *
     * @param \OCA\OpenRegister\Service\ObjectService|QBMapper $mapper The mapper for the object type
     * @param array $parameters The parameters from the request
     * @param array $pathParams The parameters in the path
     * @param int $status The HTTP status to return.
     * @return Entity|array The object(s) confirming to the request.
     * @throws Exception
     */
    private function getObjects(
        \OCA\OpenRegister\Service\ObjectService|QBMapper $mapper,
        array                                            $parameters,
        array                                            $pathParams,
        int                                              &$status = 200
    ): Entity|array
    {
        if (isset($pathParams['id']) === true && $pathParams['id'] === end($pathParams)) {
			$serializedObject = $this->objectService->getOpenRegisters()->renderEntity(
				entity: $mapper->find($pathParams['id'], extend: $parameters['extend'] ?? $parameters['_extend'] ?? null)->jsonSerialize(),
				extend: $parameters['_extend'] ?? $parameters['extend'] ?? null
			);
            $result = $this->replaceInternalReferences(
				mapper: $mapper,
				serializedObject: $serializedObject,
				extend: $parameters['extend'] ?? $parameters['_extend'] ?? []
            );

			return $result;

        } else if (isset($pathParams['id']) === true) {

            // Set the array pointer to the location of the id, so we can fetch the parameters further down the line in order.
            while (prev($pathParams) !== $pathParams['id']) {
            }

            $property = next($pathParams);

            if (next($pathParams) !== false) {
                $id = pos($pathParams);
            }

            $main = $this->objectService->getOpenRegisters()->renderEntity($mapper->findByUuid($pathParams['id'])->getObject());
            $ids = $main[$property];

            if(isset($main[$property]) === false) {
                return $this->objectService->getOpenRegisters()->renderEntity(entity: $this->replaceInternalReferences(mapper: $mapper, object: $mapper->find($pathParams['id'])));
            }

            if ($ids === null || empty($ids) === true) {
                $returnArray = [
                    'count' => 0,
                    'results' => []
                ];

                return $returnArray;
            }

            if (isset($id) === true && in_array(needle: $id, haystack: $ids) === true) {
                $object = $mapper->find($id);

                return $this->replaceInternalReferences(mapper: $mapper, object: $object);
            } else if (isset($id) === true) {
                $status = 404;
                return ['error' => 'not found', 'message' => "the subobject with id $id does not exist"];

            }

            $results = $mapper->findMultiple($ids);
            foreach ($results as $key => $result) {
                $results[$key] = $this->replaceInternalReferences(mapper: $mapper, object: $result);
            }

            $returnArray = [
                'count' => count($results),
                'results' => $results
            ];

            return $returnArray;
        }

        $parameters = $this->rewriteExternalReferences($parameters, $mapper);

        $result = $mapper->findAllPaginated(requestParams: $parameters);

        $result['results'] = array_map(function ($object) use ($mapper) {
            return $this->replaceInternalReferences(mapper: $mapper, serializedObject: $this->objectService->getOpenRegisters()->renderEntity(entity: $object->jsonSerialize()), extend: $parameters['extend'] ?? $parameters['_extend'] ?? []);
        }, $result['results']);

        $returnArray = [
            'count' => $result['total'],
        ];

        if ($result['page'] < $result['pages']) {
            $parameters['page'] = $result['page'] + 1;
            $parameters['_path'] = implode('/', $pathParams);

            $returnArray['next'] = $this->urlGenerator->getAbsoluteURL(
                $this->urlGenerator->linkToRoute(
                    routeName: 'openconnector.endpoints.handlepath',
                    arguments: $parameters
                )
            );
        }
        if ($result['page'] > 1) {
            $parameters['page'] = $result['page'] - 1;
            $parameters['_path'] = implode('/', $pathParams);

            $returnArray['previous'] = $this->urlGenerator->getAbsoluteURL(
                $this->urlGenerator->linkToRoute(
                    routeName: 'openconnector.endpoints.handlepath',
                    arguments: $parameters
                )
            );
        }

        $returnArray['results'] = $result['results'];

        return $returnArray;
    }

    /**
     * Handles requests for schema-based endpoints
     *
     * @param Endpoint $endpoint The endpoint configuration
     * @param IRequest $request The incoming request
     * @param string $path
     *
     * @return JSONResponse
     * @throws DoesNotExistException|LoaderError|MultipleObjectsReturnedException|SyntaxError
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     */
    private function handleSchemaRequest(Endpoint $endpoint, IRequest $request, string $path): JSONResponse
    {
        // Get request method
        $method = $request->getMethod();
        $target = explode('/', $endpoint->getTargetId());

        $register = $target[0];
        $schema = $target[1];


        $mapper = $this->objectService->getMapper(schema: (int)$schema, register: (int)$register);

        $parameters = $request->getParams();

        if ($endpoint->getInputMapping() !== null) {
            $inputMapping = $this->mappingService->getMapping($endpoint->getInputMapping());
            $parameters = $this->mappingService->executeMapping(mapping: $inputMapping, input: $parameters);
        }

		$pathParams = $this->getPathParameters($endpoint->getEndpointArray(), $path);

		if (isset($pathParams['id']) === true) {
			$parameters['id'] = $pathParams['id'];
		}
		foreach ($this::UNSET_PARAMETERS as $parameter) {
            unset($parameters[$parameter]);
        }


        $status = 200;

        $headers = $request->getHeader('Accept-Crs') === '' ? [] : ['Content-Crs' => $request->getHeader('Accept-Crs')];


        // Route to appropriate ObjectService method based on HTTP method
        try {
            switch ($method) {
                case 'GET':
                    return new JSONResponse(
                        $this->getObjects(mapper: $mapper, parameters: $parameters, pathParams: $pathParams, status: $status),
                        statusCode: $status,
                        headers: $headers
                    );
                case 'POST':
                    return new JSONResponse(
                        $this->replaceInternalReferences(
                            mapper: $mapper,
                            serializedObject: $mapper->createFromArray(object: $parameters)->jsonSerialize()
                        )
                    );
                case 'PUT':
                    return new JSONResponse(
                        $this->replaceInternalReferences(
                            mapper: $mapper,
                            serializedObject: $mapper->updateFromArray($parameters['id'], $request->getParams(), true, false)->jsonSerialize()
                        )
                    );
                case 'PATCH':
                    return new JSONResponse(
                        $this->replaceInternalReferences(
                            mapper: $mapper,
                            serializedObject: $mapper->updateFromArray($parameters['id'], $request->getParams(), true, true)->jsonSerialize()
                        )
                    );
                case 'DELETE':
                    if (isset($parameters['id']) === false) {
                        return new JSONResponse(data: ['error' => 'No id given to delete'], statusCode: 400);
                    }

                    if ($mapper->delete(['id' => $parameters['id']]) !== true) {
                        return new JSONResponse(data: ['error' => sprintf('Something went wrong deleting object: %s', $parameters['id'])], statusCode: 500);
                    }

                    return new JSONResponse(statusCode: 200);

                default:
                    throw new Exception('Unsupported HTTP method');
            }

        } catch (Exception $exception) {

            if (in_array(get_class($exception), ['OCA\OpenRegister\Exception\ValidationException', 'OCA\OpenRegister\Exception\CustomValidationException']) === true) {
                return $mapper->getValidateHandler()->handleValidationException(exception: $exception);
            }

            throw $exception;
        }

    }

    /**
     * Gets the raw content for a http request from the input stream.
     *
     * @return string The raw content body for a http request
     */
    private function getRawContent(): string
    {
        return file_get_contents(filename: 'php://input');
    }

    /**
     * Get all headers for a HTTP request.
     *
     * @param array $server The server data from the request.
     * @param bool $proxyHeaders Whether the proxy headers should be returned.
     *
     * @return array The resulting headers.
     */
    private function getHeaders(array $server, bool $proxyHeaders = false): array
    {
        $headers = array_filter(
            array: $server,
            callback: function (string $key) use ($proxyHeaders) {
                if (str_starts_with($key, 'HTTP_') === false) {
                    return false;
                } else if ($proxyHeaders === false
                    && (str_starts_with(haystack: $key, needle: 'HTTP_X_FORWARDED') === true
                        || $key === 'HTTP_X_REAL_IP' || $key === 'HTTP_X_ORIGINAL_URI'
                    )
                ) {
                    return false;
                }

                return true;
            },
            mode: ARRAY_FILTER_USE_KEY
        );

        $keys = array_keys($headers);

        return array_combine(
            array_map(
                callback: function ($key) {
                    return strtolower(string: substr(string: $key, offset: 5));
                },
                array: $keys),
            $headers
        );
    }

    /**
     * Check conditions for using an endpoint.
     *
     * @param Endpoint $endpoint The endpoint for which the checks should be done.
     * @param IRequest $request The inbound request.
     *
     * @return array
     * @throws Exception
     */
    private function checkConditions(Endpoint $endpoint, IRequest $request): array
    {
        $conditions = $endpoint->getConditions();
        $data['parameters'] = $request->getParams();
        $data['headers'] = $this->getHeaders($request->server, true);

        $result = JsonLogic::apply(logic: $conditions, data: $data);

        if ($result === true || $result === [] || $result === null) {
            return [];
        }

        return $result;
    }

    /**
     * Handles requests for source-based endpoints
     *
     * @param Endpoint $endpoint The endpoint configuration
     * @param IRequest $request The incoming request
     *
     * @return JSONResponse
     * @throws GuzzleException|LoaderError|SyntaxError|\OCP\DB\Exception
     */
    private function handleSourceRequest(Endpoint $endpoint, IRequest $request): JSONResponse
    {
        $headers = $this->getHeaders($request->server);

        // Proxy the request to the source via CallService
        $response = $this->callService->call(
            source: $endpoint->getSource(),
            endpoint: $endpoint->getPath(),
            method: $request->getMethod(),
            config: [
                'query' => $request->getParams(),
                'headers' => $headers,
                'body' => $this->getRawContent(),
            ]
        );

        return new JSONResponse(
            $response->getResponse(),
            $response->getStatusCode()
        );
    }

    /**
     * Generates url based on available endpoints for the object type.
     *
     * @param string $id The id of the object to generate an url for.
     * @param OCA\OpenRegister\Db\SchemaMapper $schemaMapper The mapper to get schemas
     * @param int|null $register The register of the object (aids performance).
     * @param int|null $schema The schema of the object (aids performance).
     * @param array $parentIds The ids of the main object on subobjects.
     *
     * @return string The generated url.
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function generateEndpointUrl(string $id, \OCA\OpenRegister\Db\SchemaMapper $schemaMapper, ?int $register = null, ?int $schema = null, array $parentIds = []): string
    {
        if ($register === null) {
            $object = $this->objectService->getOpenRegisters()->getMapper('objectEntity')->find($id);
            $register = $object->getRegister();
            $schema   = $object->getSchema();
        }

        $target = "$register/$schema";
        $endpoints = $this->endpointMapper->findAll(filters: ['target_id' => $target, 'method' => 'GET']);

        if (count($endpoints) === 0) {
            return $id;
        }

        $endpoint = $endpoints[0];
        $filteredEndpoints = array_filter($endpoints, function(Endpoint $endpoint) {return in_array(needle: '{{id}}', haystack: $endpoint->getEndpointArray()) === true;});

        if(count($filteredEndpoints) > 0) {
            $endpoint = array_shift($filteredEndpoints);
        }

        $location = $endpoint->getEndpointArray();

        // Determine schema title (lowercased)
        $schemaTitle = strtolower($schemaMapper->find($schema)->getTitle());

        // Use first parentId if available
        $parentId = $parentIds[0] ?? null;

        // Make sure we are dealing with a sub endpoint
        $isSubEndpoint = false;
        foreach ($location as $key => $part) {
            if (preg_match('#{{([^}]+)}}#', $part, $matches)) {
                $placeholder = trim($matches[1]);
                if ($placeholder === "{$schemaTitle}_id") {
                    $isSubEndpoint = true;
                }
            }
        }

        foreach ($location as $key => $part) {
            if (preg_match('#{{([^}]+)}}#', $part, $matches)) {
                $placeholder = trim($matches[1]);

                if ($placeholder === 'id' && $parentId !== null && $isSubEndpoint === true) {
                    // Replace {{id}} with parent id if set
                    $location[$key] = $parentId;
                } elseif ($placeholder === 'id') {
                    // Otherwise, replace {{id}} with current object id
                    $location[$key] = $id;
                } elseif ($placeholder === "{$schemaTitle}_id") {
                    // Replace {{schematitle_id}} with object id
                    $location[$key] = $id;
                }
            }
        }

        $path = implode(separator: '/', array: $location);
        return $this->urlGenerator->getBaseUrl().'/apps/openconnector/api/endpoint/'.$path;
    }

    /**
     * Saves object to OpenRegister
     *
     * @param Rule $rule
     * @param array $data
     *
     * @return array $data
     */
    private function processSaveObjectRule(Rule $rule, array $data): array
    {
        $configuration = $rule->getConfiguration();
        $register = $configuration['save_object']['register'];
        $schema = $configuration['save_object']['schema'];
        $mapping = $configuration['save_object']['mapping'] ?? null;

        if (isset($mapping) === true) {
            $data = $this->processMapping(rule: $rule, mapping: $mapping, data: $data);
        }

        $objectService = $this->containerInterface->get('OCA\OpenRegister\Service\ObjectService');
        $data['body'] = $objectService->saveObject(register: $register, schema: $schema, object: $data['body']);

        return $data;
    }

    /**
     * Processes rules for an endpoint request
     *
     * @param Endpoint $endpoint The endpoint being processed
     * @param IRequest $request The incoming request
     * @param array $data Current request data
     *
     * @return array|JSONResponse Returns modified data or error response if rule fails
     */
    private function processRules(Endpoint $endpoint, IRequest $request, array $data, string $timing, ?string $objectId = null): array|Response
    {

        $rules = $endpoint->getRules();
        if (empty($rules) === true) {
            return $data;
        }

        try {
            // Get all rules at once and sort by order
            $ruleEntities = array_filter(
                array_map(
                    fn($ruleId) => $this->getRuleById($ruleId),
                    $rules
                )
            );

            // Sort rules by order
            usort($ruleEntities, fn($a, $b) => $a->getOrder() - $b->getOrder());

            // Process each rule in order
            foreach ($ruleEntities as $rule) {

                // Skip if rule action doesn't match request method
                if (strtolower($rule->getAction()) !== strtolower($request->getMethod())) {
                    continue;
                }

                // Check rule conditions
                if ($this->checkRuleConditions($rule, $data) === false || $rule->getTiming() !== $timing) {
                    continue;
                }

                // Process rule based on type
                $result = match ($rule->getType()) {
                    'save_object' => $this->processSaveObjectRule($rule, $data),
                    'authentication' => $this->processAuthenticationRule($rule, $data),
                    'error' => $this->processErrorRule($rule),
                    'mapping' => $this->processMappingRule($rule, $data),
                    'synchronization' => $this->processSyncRule($rule, $data),
                    'javascript' => $this->processJavaScriptRule($rule, $data),
                    'fileparts_create' => $this->processFilePartRule($rule, $data, $endpoint, $objectId),
                    'filepart_upload' => $this->processFilePartUploadRule(rule: $rule, data: $data, request: $request, objectId: $objectId),
                    'download' => $this->processDownloadRule(rule: $rule, data: $data, objectId: $objectId),
                    'extend_input' => $this->processExtendInputRule(rule: $rule, data: $data),
					'extend_external_input' => $this->ruleService->extendExternalUrl(rule: $rule, data: $data),
                    'audit_trail' => $this->processAuditTrailRule(rule: $rule, endpoint: $endpoint, data: $data, objectId: $objectId),
                    'write_file' => $this->processWriteFileRule(rule: $rule, data: $data, objectId: $objectId),
                    'locking' => $this->processLockingRule(rule: $rule, data: $data, objectId: $objectId),
                    'custom' => $this->processCustomRule(rule: $rule, data: $data),
                    default => throw new Exception('Unsupported rule type: ' . $rule->getType()),
                };

                // If result is JSONResponse, return error immediately
                if ($result instanceof JSONResponse === true || $result instanceof DataDownloadResponse === true ) {
                    return $result;
                }

                // Update data with rule result
                $data = $result;
			}

			unset($data['body']['_extendedInput']);

			return $data;
        } catch (Exception $e) {
            $this->logger->error('Error processing rules: ' . $e->getMessage());
            return new JSONResponse(['error' => 'Rule processing failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get a rule by its ID using RuleMapper
     *
     * @param string $id The unique identifier of the rule
     *
     * @return Rule|null The rule object if found, or null if not found
     */
    private function getRuleById(string $id): ?Rule
    {
        try {
            return $this->ruleMapper->find((int)$id);
        } catch (Exception $e) {
            $this->logger->error('Error fetching rule: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Processes authentication rules
     *
     * @param Rule $rule The rule to process
     * @param array $data The data of the request
     *
     * @return array|JSONResponse the unchanged $data array if authentication succeeds, or a JSONResponse containing an error on authentication.
     */
    private function processAuthenticationRule(Rule $rule, array $data): array|JSONResponse
    {
        $configuration = $rule->getConfiguration();
        $header = $data['headers']['Authorization'] ?? $data['headers']['authorization'] ?? '';

        if ($header === '' || $header === null) {
            return new JSONResponse(['error' => 'forbidden', 'details' => 'you are not allowed to access this endpoint unauththenticated'], Http::STATUS_FORBIDDEN);
        }

        if(isset($configuration['authentication']) === false) {
            return $data;
        }

        switch($configuration['authentication']['type']) {
            case 'apikey':
                try {
                    $this->authorizationService->authorizeApiKey(header: $header, keys: $configuration['authentication']['keys']);
                } catch (AuthenticationException $exception) {
                    return new JSONResponse(
                        data: ['error' => $exception->getMessage(), 'details' => $exception->getDetails()],
                        statusCode: 401
                    );
                }
                break;
            case 'jwt':
            case 'jwt-zgw':
                try {
                    $this->authorizationService->authorizeJwt(authorization: $header);
                } catch (AuthenticationException $exception) {
                    return new JSONResponse(
                        data: ['error' => $exception->getMessage(), 'details' => $exception->getDetails()],
                        statusCode: 401
                    );
                }
                break;
            case 'basic':
                try {
                    $this->authorizationService->authorizeBasic($header, $configuration['authentication']['users'], $configuration['authentication']['groups']);
                } catch (AuthenticationException $exception) {
                    return new JSONResponse(
                        data: ['error' => $exception->getMessage(), 'details' => $exception->getDetails()],
                        statusCode: 401
                    );
                }
                break;
            case 'oauth':
                try {
                    $this->authorizationService->authorizeOAuth($header, $configuration['authentication']['users'], $configuration['authentication']['groups']);
                } catch (AuthenticationException $exception) {
                    return new JSONResponse(
                        data: ['error' => $exception->getMessage(), 'details' => $exception->getDetails()],
                        statusCode: 401
                    );
                }
                break;
            default:
                return new JSONResponse(data: ['error' => 'The authentication method is not supported'], statusCode: Http::STATUS_NOT_IMPLEMENTED);
        }

        return $data;
    }

    /**
     * Processes an error rule
     *
     * @param Rule $rule The rule object containing error details
     *
     * @return JSONResponse Response containing error details and HTTP status code
     */
    private function processErrorRule(Rule $rule): JSONResponse
    {
        $config = $rule->getConfiguration();
        return new JSONResponse(
            [
                'error' => $config['error']['name'],
                'message' => $config['error']['message']
            ],
            $config['error']['code']
        );
    }

    /**
     * Executes mapping on data from endpoint flow
     *
     * @param mapping $mapping
     * @param array $data
     *
     * @return array $data
     */
    private function processMapping(Rule $rule, Mapping $mapping, array $data): array
    {
        $config = $rule->getConfiguration();
        // Todo: We should just remove this if statement and use mapping to loop through results instead.
        if (isset($data['body']['results']) === true
            && strtolower($rule->getAction()) === 'get'
            && (isset($config['mapResults']) === false || $config['mapResults'] === true)
        ) {
            foreach (($data['body']['results']) as $key => $result) {
                $data['body']['results'][$key] = $this->mappingService->executeMapping($mapping, $result);
            }

            return $data;
        }

        $data['body'] = $this->mappingService->executeMapping($mapping, $data['body']);

        return $data;
    }

    /**
     * Processes a mapping rule
     *
     * @param Rule $rule The rule object containing mapping details
     * @param array $data The data to be processed through the mapping rule
     *
     * @return array The processed data after applying the mapping rule
     * @throws DoesNotExistException When the mapping configuration does not exist
     * @throws MultipleObjectsReturnedException When multiple mapping objects are returned unexpectedly
     * @throws LoaderError When there is an error loading the mapping
     * @throws SyntaxError When there is a syntax error in the mapping configuration
     */
    private function processMappingRule(Rule $rule, array $data): array
    {
        $config = $rule->getConfiguration();
        $mapping = $this->mappingService->getMapping($config['mapping']);

        $data = $this->processMapping(rule: $rule, mapping: $mapping, data: $data);

        return $data;
    }

    /**
     * Extends input for performing business logic
     *
     * @param Rule $rule The rule containing the configuration which parameters could be extended
     * @param array $data The data array containing the input parameters.
     *
     * @return array The data array with the extended parameters in the 'extendedParameters' key.
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function processExtendInputRule(Rule $rule, array $data): array
    {
        $parameters = new Dot($data['parameters']);
        $config = $rule->getConfiguration();
        $extendedParameters = new Dot();

        foreach ($config['extend_input']['properties'] as $property) {
            $value = $parameters->get($property);

            if(filter_var($value, FILTER_VALIDATE_URL) !== false) {
                $exploded = explode(separator: '/', string: $value);
                $value = end($exploded);
            }

            try {
                $object = $this->objectService->getOpenRegisters()->getMapper('objectEntity')->find(identifier: $value);
            } catch (DoesNotExistException $exception) {
                continue;
            }
            $extendedParameters->add($property, $this->objectService->getOpenRegisters()->renderEntity($object->jsonSerialize()));

        }

		if (isset($data['extendedParameters']) === true) {
			$data['extendedParameters'] = array_merge($extendedParameters->all(), $data['extendedParameters']);
		} else {
            $data['extendedParameters'] = $extendedParameters->all();
        }

		$data['body']['_extendedInput'] = $data['extendedParameters'];

        return $data;
    }

    /**
     * Fetches the audit trail for an object, returns a specific audit rule if the path parameter audittrail-id is specified.
     *
     * @param Rule $rule The rule to execute
     * @param Endpoint $endpoint The endpoint on which the rule is executed
     * @param array $data The data from the request.
     * @param string $objectId The object id for which the request was done.
     *
     * @return array|Response The updated data array, or a json response with a not found error.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function processAuditTrailRule(Rule $rule, Endpoint $endpoint, array $data, string $objectId): array|Response
    {

        $pathParameters = $this->getPathParameters(endpointArray: $endpoint->getEndpointArray(), path: $data['path']);

        if(isset($pathParameters['audittrail-id']) === true) {
            $auditrule = $this->objectService->getOpenRegisters()->getLogs($objectId, filters: ['uuid' => $pathParameters['audittrail-id']]);

            if(count($auditrule) === 1) {
                $data['body'] = $auditrule[0];
                return $data;
            }

            return new JSONResponse(data: ['error' => 'Not found', 'reason' => 'The resource you are looking for does not exist'], statusCode: HTTP::STATUS_NOT_FOUND);

        }
        $audittrail = $this->objectService->getOpenRegisters()->getLogs($objectId);

        $data['body'] = $audittrail;


        return $data;
    }

    /**
     * Process a locking rule, either locking or unlocking a resource.
     *
     * @param Rule $rule Rule containing configuration for the execution of the rule.
     * @param array $data The data to update
     * @param string $objectId The object id of the object to lock or unlock
     *
     * @return array The updated data array.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \OCP\Files\NotFoundException
     */
    private function processLockingRule(Rule $rule, array $data, string $objectId): array
    {
        $config = $rule->getConfiguration();

        if($config['locking']['action'] === 'lock') {
            $process = (Uuid::v4())->jsonSerialize();
            $object = $this->objectService->getOpenRegisters()->lockObject(identifier: $objectId, process: $process, duration: $config['locking']['duration'] ?? 3600);
        } else if ($config['locking']['action'] === 'unlock') {
            $object = $this->objectService->getOpenRegisters()->unlockObject(identifier: $objectId);
        }

        $data['body'] = $this->objectService->getOpenRegisters()->renderEntity(entity: $object->jsonSerialize());

        return $data;
    }

    /**
     * Process a custom rule
     *
     * @param Rule $rule The rule to process
     * @param array $data The data to process
     *
     * @return array The updated data array.
     */
    private function processCustomRule(Rule $rule, array $data): array|JSONResponse
    {
        return $this->ruleService->processCustomRule(rule: $rule, data: $data);
    }

    /**
     * Process a rule to write files.
     *
     * @param Rule $rule The rule to process.
     * @param array $data The data to write.
     * @param string $objectId The object to write the data to.
     * @param int $registerId The register the object is in.
     * @param int $schemaId The schema the object is in.
     *
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    private function processWriteFileRule(Rule $rule, array $data, string $objectId): array
    {
        if (isset($rule->getConfiguration()['write_file']) === false) {
            throw new Exception('No configuration found for write_file');
        }

        $config  = $rule->getConfiguration()['write_file'];
        $dataDot = new Dot($data);
        $files = $dataDot[$config['filePath']];
        if (isset($files) === false || empty($files) === true) {
            return $dataDot->jsonSerialize();
        }

        // Check if associative array
        if (is_array($files) === true && isset($files[0]) === true & array_keys($files[0]) !== range(0, count($files[0]) - 1)) {
            $result = [];
            foreach ($files as $key => $value) {

                // Check for tags
                $tags = [];
                if (is_array($value) === true) {
                    $content = $value['content'];
                    if (isset($value['label']) === true && isset($config['tags']) === true &&
                        in_array(needle: $value['label'], haystack: $config['tags']) === true) {
                        $tags = [$value['label']];
                    }
                    if (isset($value['filename']) === true) {
                        $fileName = $value['filename'];
                    }
                } else {
                    $content = $value;
                }

                try {
                    // Write file with OpenRegister ObjectService.
                    $objectService = $this->containerInterface->get('OCA\OpenRegister\Service\ObjectService');
                    $fileService = $this->containerInterface->get('OCA\OpenRegister\Service\FileService');
                    $file = $fileService->addFile(objectEntity: $objectService->find($objectId), fileName: $fileName, content: base64_decode($content));

                    $tags = array_merge($config['tags'] ?? [], ["object:$objectId"]);
                    if ($file instanceof \OCP\Files\File === true) {
//                        $this->attachTagsToFile(fileId: $file->getId(), tags: $tags);
                    }

                    $result[$key] = $file->getPath();
                } catch (Exception $exception) {
                }
            }
            $result[$key] = $file->getPath();
            $dataDot[$config['filePath']] = $result;
        } else {
            $content = $files;
            $fileName = $dataDot[$config['fileNamePath']];

            try {
                // Write file with OpenRegister ObjectService.
                $objectService = $this->containerInterface->get('OCA\OpenRegister\Service\ObjectService');
				$fileService = $this->containerInterface->get('OCA\OpenRegister\Service\FileService');
                $file = $fileService->addFile(objectEntity: $objectService->find($objectId), fileName: $fileName, content: base64_decode($content));

                $tags = array_merge($config['tags'] ?? [], ["object:$objectId"]);
                if ($file instanceof File === true) {
//                    $this->attachTagsToFile(fileId: $file->getId(), tags: $tags);
                }
                $dataDot[$config['filePath']] = $file->getPath();
            } catch (Exception $exception) {
            }
        }


        return $dataDot->jsonSerialize();
    }

    /**
     * Processes a synchronization rule
     *
     * @param Rule $rule The rule object containing synchronization details
     * @param array $data The data to be synchronized
     *
     * @return array The data after synchronization processing
     */
    private function processSyncRule(Rule $rule, array $data): array
    {
        $config = $rule->getConfiguration();

        // Check if base requirement is in config.
        if(isset($config['synchronization']) === false) {
            return $data;
        }

        // Fetch the synchronization.
        if (is_numeric($config['synchronization']) === true) {
            $synchronization = $this->synchronizationService->getSynchronization(id: (int) $config['synchronization']);
        } else {
            $synchronization = $this->synchronizationService->getSynchronization(filters: ['reference' => $config['synchronization']]);
        }

        // Check if the synchronization should be in test mode.
        if(isset($data['body']['isTest']) === true) {
            $test = $data['body']['isTest'];
        } elseif (isset($config['isTest']) === true) {
            $force = $config['isTest'];
        } else {
            $test = false;
        }

        // Check if the synchronization should be forced.
        if(isset($data['body']['force']) === true) {
            $force = $data['body']['force'];
        } elseif (isset($config['force']) === true) {
            $force = $config['force'];
        } else {
            $force = false;
        }

        $object = null;
        if (isset($data['body']) === true) {
            $object = $data['body'];
        }

        // Set $object to a different variable becuase we might update $object with reference and want to keep what we send to synchronize.
        $sendObject = $object;

        // Run synchronization.
        $log = $this->synchronizationService->synchronize(synchronization: $synchronization, isTest: $test, force: $force, object: $object);

        // $object got updated through reference.
        $returnedObject = $object;

        if (isset($config['synchronizationConfig']['mergeResultToKey']) === true) {
            // Merge result to root send object.
            if ($config['synchronizationConfig']['mergeResultToKey'] === '#') {
                $data['body'] = array_merge($sendObject, $returnedObject);
            // Merge result to configured key in send object
            } else {
                $sendObject[$config['synchronizationConfig']['mergeResultToKey']] = $returnedObject;
                $data['body'] = $sendObject;
            }
        // Overwrite body with result
        } else if (isset($config['synchronizationConfig']['overwriteObjectWithResult']) === true && filter_var($config['synchronizationConfig']['overwriteObjectWithResult'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true) {
            $data['body'] = $returnedObject;
        } else {
            $data['body'] = $log;
        }

        return $data;
    }

    /**
     * Processes a file part creation rule.
     *
     * @param Rule $rule The rule to process.
     * @param array $data The created object in array form.
     * @param string|null $objectId The id of the resulting object.
     *
     * @return array The updated object data.
     *
     * @throws ContainerExceptionInterface
     * @throws DoesNotExistException
     * @throws GuzzleException
     * @throws LoaderError
     * @throws MultipleObjectsReturnedException
     * @throws NotFoundExceptionInterface
     * @throws SyntaxError
     * @throws \OCA\OpenRegister\Exception\ValidationException
     * @throws \OCP\Files\InvalidPathException
     * @throws \OCP\Files\NotFoundException
     */
    private function processFilePartRule(Rule $rule, array $data, Endpoint $endpoint, ?string $objectId = null): array|JSONResponse
    {
        if ($objectId === null) {
            throw new Exception('Filepart rules can only be applied after the object has been created');
        }

        if (isset($rule->getConfiguration()['fileparts_create']) === false) {
            throw new Exception('No configuration found for fileparts_create');
        }

        $config = $rule->getConfiguration()['fileparts_create'];

        $targetId = explode('/', $endpoint->getTargetId());

        $registerId = $targetId[0];
        $superSchemaId = $targetId[1];

        $sizeLocation     = $config['sizeLocation'];
        $schemaId         = $config['schemaId'];
        $filenameLocation = $config['filenameLocation'] ?? 'filename';
        $filePartLocation = $config['filePartLocation'] ?? 'fileParts';

        $mapping = null;
        if (isset($config['mappingId']) === true) {
            $mapping = $this->mappingService->getMapping($config['mappingId']);
        }

        $openRegister = $this->objectService->getOpenRegisters();
        $openRegister->setRegister($registerId);
        $openRegister->setSchema($superSchemaId);

        $object   = $openRegister->find(id: $objectId);
//        $location = $object->getFolder();

		$fileService = $this->containerInterface->get('OCA\OpenRegister\Service\FileService');
		$location = $fileService->getObjectFolder($object)->getPath();


        $dataDot = new Dot($data);
        $size = $dataDot[$sizeLocation];
        $filename = $dataDot[$filenameLocation];

        $fileParts = $this->storageService->createUpload($location, $filename, $size, $objectId);

		$fileParts = array_map(function ($filePart) use ($mapping, $registerId, $schemaId) {

			if ($mapping !== null) {
				$formatted = $this->mappingService->executeMapping(mapping: $mapping, input: $filePart);
			} else {
				$formatted = $filePart;
			}

			try {
				return $this->objectService->getOpenRegisters()->saveObject(
					register: $registerId,
					schema: $schemaId,
					object: $formatted,
					uuid: $formatted['id']
				)->jsonSerialize();
			} catch (ValidationException $exception) {
				return $this->objectService->getOpenRegisters()->handleValidationException($exception);
			}


		}, $fileParts);

		$errors = array_filter($fileParts, function($part) {
			if($part instanceof JSONResponse) {
				return true;
			}
		});

		if(count($errors) > 0) {
			return array_shift($errors);
		}



        $dataDot[$filePartLocation] = $fileParts;

        $filepartIds = array_map(function($filePart) {
            return $filePart['id'];
        }, $fileParts);

        $saveObject = clone $dataDot;
        $saveObject[$filePartLocation] = $filepartIds;

        $openRegister->saveObject(register: $registerId, schema: $schemaId, object: $saveObject->jsonSerialize());

        return $dataDot->jsonSerialize();
    }

    /**
     * Processes the upload of a file part.
     *
     * @param Rule $rule The rule to process.
     * @param array $data The data from the object in array form.
     * @param string|null $objectId The id of the object.
     *
     * @return array The updated object data.
     *
     * @throws DoesNotExistException
     * @throws LoaderError
     * @throws MultipleObjectsReturnedException
     * @throws SyntaxError
     * @throws \OCP\Files\InvalidPathException
     * @throws \OCP\Files\NotFoundException
     */
    private function processFilePartUploadRule(Rule $rule, array $data, Request $request, ?string $objectId = null): array
    {
        if (isset($rule->getConfiguration()['filepart_upload']) === false) {
            throw new Exception('No configuration found for filepart_upload');
        }

        $config = $rule->getConfiguration()['filepart_upload'];

        $mappedData = $data;

        if (isset($config['mappingId']) === true) {
            $mapping = $this->mappingService->getMapping($config['mappingId']);
            $mappedData = $this->mappingService->executeMapping(mapping: $mapping, input: $mappedData);
        }

		var_dump($mappedData);

        $mappedData['successful'] = $this->storageService->writePart(partId: $mappedData['order'], partUuid: $mappedData['id'], data: $mappedData['data']);

        unset($data['data']);

        if (isset($config['mappingOutId']) === true) {
            $mappedData = $this->mappingService->executeMapping(mapping: $this->mappingService->getMapping(mappingId: $config['mappingOutId']), input: $mappedData);
        }

		var_dump($mappedData);

        $object = $this->objectService->getOpenRegisters()->getMapper('objectEntity')->find($objectId);
        $object->setObject($mappedData);
        $this->objectService->getOpenRegisters()->getMapper('objectEntity')->update($object);


        $data['body'] = $mappedData;

        return $data;
    }

    /**
     * Processes a JavaScript rule
     *
     * @param Rule $rule The rule object containing JavaScript execution details
     * @param array $data The input data to be processed by the JavaScript rule
     *
     * @return array The processed data after executing the JavaScript rule
     */
    private function processJavaScriptRule(Rule $rule, array $data): array
    {
        $config = $rule->getConfiguration();
        // @todo: Here we need to implement the JavaScript execution logic
        // For now, just return the data unchanged
        return $data;
    }

    /**
     * Downloads a file based upon configuration
     *
     * @param Rule $rule The rule to execute.
     * @param array $data The data to perform the rule on.
     * @param string $objectId The id of the requested object.
     *
     * @return Response A response containing the file requested.
     *
     * @throws ContainerExceptionInterface
     * @throws DoesNotExistException
     * @throws NotFoundExceptionInterface
     * @throws \OCP\Files\NotFoundException
     */
    private function processDownloadRule (Rule $rule, array $data, string $objectId): Response
    {
        $config = $rule->getConfiguration();

        /**
         * @var ObjectEntity $object
         */
        $object = $this->objectService->getOpenRegisters()->getMapper('objectEntity')->find(identifier: $objectId);

        if (isset($data['parameters']['filename']) === true) {
            $filename = $data['parameters']['filename'];
        }

        if (isset($config['filenamePosition']) === true) {
            $dot = new Dot($object->jsonSerialize());
            $filename = $dot->get($config['filenamePosition']);
        }

		$fileService = $this->containerInterface->get('OCA\OpenRegister\Service\FileService');
        $files = $fileService->getFiles(object: $object, sharedFilesOnly: false);

        // Try to get filename from object its files (only works when object has 1 file)
        if (isset($filename) === false && count($object->getFiles()) === 1) {
            $filename = $object->getFiles()[0]['title'];
        }

        // Try to get filename from files found with fileservice (only works when object has 1 file)
        if (isset($filename) === false && count($files) === 1) {
            $filename = $files[0]->getName();
        }
        
        if (isset($filename) === false) {
            throw new Exception('File could not be determined');
        }

        if(isset($data['parameters']['version']) === true) {
            $file = $fileService->getFile(object: $object, filePath: $filename, version: $data['parameters']['version']);
        } else {
            $file = $fileService->getFile(object: $object, filePath: $filename);
        }

        $response = new DataDownloadResponse(data: $file->getContent(), filename: $file->getName(), contentType: $file->getType());

        return $response;
    }

    /**
     * Checks if rule conditions are met
     *
     * @param Rule $rule The rule object containing conditions to be checked
     * @param array $data The input data against which the conditions are evaluated
     *
     * @return bool True if conditions are met, false otherwise
     * @throws Exception
     */
    private function checkRuleConditions(Rule $rule, array $data): bool
    {
        $conditions = $rule->getConditions();
        if (empty($conditions) === true) {
            return true;
        }

        return JsonLogic::apply($conditions, $data) === true;
    }

    /**
     * Updates request object with processed rule data
     *
     * @param IRequest $request The request object to be updated
     * @param array $ruleData The processed rule data to update the request with
     * @param array $incomingData The processed rule data to update the request with
     *
     * @return IRequest The updated request object
     */
    private function updateRequestWithRuleData(IRequest $request, array $ruleData, array $incomingData): IRequest
    {
        $queryParameters = $ruleData['body']['_parameters'] ?? $incomingData['params'];
        $method = $ruleData['body']['_method'] ?? $incomingData['method'];
        $headers = $ruleData['body']['_headers'] ?? $incomingData['headers'];

        // create items array of request
        $items = [
            'get'		 => [],
            'post'		 => $_POST,
            'files'		 => $_FILES,
            'server'	 => $_SERVER,
            'env'		 => $_ENV,
            'cookies'	 => $_COOKIE,
            'urlParams'  => $queryParameters,
            'params' => $queryParameters,
            'method'     => $method,
            'requesttoken' => false,
        ];

        $items['server']['headers'] = $headers;

        // build the new request
        $request = new Request(
            vars: $items,
            requestId: new RequestId($request->getId(), new SecureRandom()),
            config: $this->config
        );

        return $request; // Return the overridden request
    }

    /**
     * Parse raw content into structured data based on content type
     *
     * @param string $content The raw content to parse
     * @param string|null $contentType Optional content type hint
     * @return mixed Parsed data (array for JSON/XML) or original string
     */
    private function parseContent(Request $request): mixed
    {
        $contentType = $request->getHeader('Content-Type');

        if (str_contains($contentType, 'multipart/form-data') === true) {
            [$post, $files] = request_parse_body();

            $parsedFiles = array_map(function ($file) { return file_get_contents($file['tmp_name']); }, $files);

            return array_merge($post, $parsedFiles);
        }

        $content = $this->getRawContent();

        // Try JSON decode first
        $json = json_decode($content, true);
        if ($json !== null) {
            return $json;
        }

        // Try XML decode if content type suggests XML or content looks like XML
        if ($contentType === 'application/xml' || $contentType === 'text/xml' ||
            ($contentType === null && $this->looksLikeXml($content) === true)) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($content);
            libxml_clear_errors();

            if ($xml !== false) {
                return json_decode(json_encode($xml), true);
            }
        }

        // Return original content as fallback
        return $request->getParams();
    }

    /**
     * Check if content appears to be XML
     *
     * @param string $content Content to check
     * @return bool True if content is valid XML
     */
    private function looksLikeXml(string $content): bool
    {
        // Suppress XML errors
        libxml_use_internal_errors(true);

        // Attempt to parse the content as XML
        $result = simplexml_load_string($content) !== false;

        // Clear any XML errors
        libxml_clear_errors();

        return $result;
    }
}
