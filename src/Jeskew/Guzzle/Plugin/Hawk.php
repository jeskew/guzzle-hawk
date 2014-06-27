<?php

namespace Jeskew\Guzzle\Plugin;


use Dflydev\Hawk\Client\ClientBuilder;
use Dflydev\Hawk\Credentials\Credentials;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\EmitterInterface;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Message\Request;

class Hawk implements SubscriberInterface
{
    private $key;
    private $secret;
    private $offset;
    private $parsePayload;
    private $extHeaders;

    public function __construct(
        $key,
        $secret,
        $offset = 0,
        $parsePayload = false,
        $extHeaders = []
    ) {
        $this->key = $key;
        $this->secret = $secret;
        $this->offset = $offset;
        $this->parsePayload = $parsePayload;
        $this->extHeaders = $extHeaders;
    }

    public function getEvents()
    {
        return [
            'before' => ['signRequest', 'last'],
        ];
    }

    public function signRequest(BeforeEvent $event)
    {
        $request = $event->getRequest();

        $hawkRequest = $this->generateHawkRequest(
            $this->key,
            $this->secret,
            $request->getUrl(),
            $request->getMethod(),
            $this->offset,
            [],
            $this->parsePayload ? (string) $request->getBody() : '',
            $this->parsePayload ? $request->getHeader('content-type') : ''
        );

        $request->setHeader(
            $hawkRequest->header()->fieldName(),
            $hawkRequest->header()->fieldValue()
        );
    }

    public function generateHawkRequest(
        $key,
        $secret,
        $url,
        $method = 'GET',
        $offset = 0,
        $ext = [],
        $payload = '',
        $contentType = ''
    ) {
        $client = $this->buildClient($offset);

        $credentials = $this->generateCredentials($key, $secret);

        $requestOptions = $this->generateRequestOptions($ext, $payload, $contentType);

        $request = $client->createRequest(
            $credentials,
            $url,
            $method,
            $requestOptions
        );

        return $request;
    }

    private function gatherExtHeaders(Request $request)
    {
        $headers = $request->getHeaders();
    }

    private function buildClient($offset)
    {
        $builder =  ClientBuilder::create();

        if ($offset) {
            $builder = $builder->setLocaltimeOffset($offset);
        }

        return $builder->build();
    }

    private function generateCredentials($key, $secret, $algorithm = 'sha256')
    {
        return new Credentials($secret, $algorithm, $key);
    }

    private function generateRequestOptions($ext, $payload, $contentType)
    {
        $requestOptions = [];
        if ($payload && $contentType) {
            $requestOptions['payload'] = $payload;
            $requestOptions['content_type'] = $contentType;
        }

        if ($ext) {
            $requestOptions['ext'] = http_build_query($ext);
        }

        return $requestOptions;
    }
} 
