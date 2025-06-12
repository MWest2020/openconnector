<?php

namespace OCA\OpenConnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use OCA\OpenConnector\Db\Source;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Soap\Engine\Engine;
use Soap\Engine\SimpleEngine;
use Soap\ExtSoapEngine\AbusedClient;
use Soap\ExtSoapEngine\ExtSoapOptions;
use Soap\ExtSoapEngine\ExtSoapDriver;
use Soap\ExtSoapEngine\Transport\ExtSoapClientTransport;
use Soap\ExtSoapEngine\Transport\TraceableTransport;
use Soap\ExtSoapEngine\Wsdl\InMemoryWsdlProvider;
use Soap\ExtSoapEngine\Wsdl\TemporaryWsdlLoaderProvider;
use Soap\Psr18Transport\Wsdl\Psr18Loader;
use Soap\Wsdl\Loader\StreamWrapperLoader;
use Symfony\Component\Config\Definition\Exception\Exception;

class SOAPService
{
    private Client $client;
    private ResponseInterface $response;

    public function setupEngine(Source $source, array $passedConfig): Engine {

        $config = $source->getConfiguration();

        if (isset($config['wsdl']) === false) {
            throw new Exception('No wsdl provided');
        }

        $this->client = new Client($passedConfig);
        $wsdl = $config['wsdl'];
        unset($passedConfig['wsdl']);
        try {
            $engine = new SimpleEngine(
                $this->driver = ExtSoapDriver::createFromClient(
                    $this->soap = $client = AbusedClient::createFromOptions(
                        ExtSoapOptions::defaults($wsdl, [
                            'cache_wsdl' => WSDL_CACHE_NONE,
                            'trace' => true,
                            'location' => $source->getLocation(),
                        ])
                            ->withWsdlProvider(new TemporaryWsdlLoaderProvider(new Psr18Loader($this->client, new HttpFactory())))
                            ->disableWsdlCache()
                    )
                ),
                $transport = new TraceableTransport(
                    $client,
                    new ExtSoapClientTransport($client)
                )
            );
        } catch (\SoapFault $fault) {
            throw $fault;
        }

        return $engine;
    }

    public function createMessage(Source $source, string $endpoint, array $config): Response
    {

        $body = json_decode(json: $config['body'], associative: true);
        unset($config['body']);

        libxml_set_external_entity_loader(static function ($public, $system) {
            return $system;
        });
        /**
         * @var $engine Engine
         * @var $transport TraceableTransport
         */
        $engine = $this->setupEngine(source: $source, passedConfig: $config);

        // In SOAP the endpoint is decided by the WSDL, however, the SOAP method can be derived from the endpoint property of the call.

        $result = $engine->request($endpoint, $body);


        libxml_set_external_entity_loader(static function () {
            return null;
        });

        return new Response(status: 200, body: json_encode($result));


        //return json_encode($result);
    }
}
