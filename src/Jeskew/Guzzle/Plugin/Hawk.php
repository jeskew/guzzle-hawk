<?php

namespace Jeskew\Guzzle\Plugin;


use Dflydev\Hawk\Client\ClientBuilder;
use Dflydev\Hawk\Credentials\Credentials;
use Guzzle\Common\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Hawk implements EventSubscriberInterface
{
    private $key;
    private $secret;
    private $offset;

    public function __construct($key, $secret, $offset = 0)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->offset = $offset;
    }

    public static function getSubscribedEvents()
    {
        return array('request.before_send' => 'signRequest');
    }

    public function signRequest(Event $event)
    {
        $request = $event['request'];

        $hawkRequest = $this->generateHawkRequest(
            $this->key,
            $this->secret,
            $request->getUrl(),
            $request->getMethod(),
            $this->offset
        );

        $request->setHeader(
            $hawkRequest->header()->fieldName(),
            $hawkRequest->header()->fieldValue()
        );
    }

    private function generateHawkRequest(
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