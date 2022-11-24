<?php

namespace Minvws\Zammad;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface;
use ZammadAPIClient\HTTPClientInterface;

/**
 * @psalm-suppress InvalidExtendClass
 */
class HTTPClient extends Client implements HTTPClientInterface
{
    protected array $authentication_options;

    /**
     * @psalm-suppress MethodSignatureMismatch
     */
    public function __construct(array $options = [])
    {
        $options['base_uri'] = $options['url'] . '/api/' . \ZammadAPIClient\Client::API_VERSION . '/';

        // Authentication
        if (!empty($options['username']) || !empty($options['password'])) {
            $this->authentication_options = [
                'username' => $options['username'],
                'password' => $options['password'],
            ];
        }

        if (!empty($options['http_token'])) {
            $this->authentication_options = [
                'http_token' => $options['http_token'],
            ];
        }

        if (!empty($options['oauth2_token'])) {
            $this->authentication_options = [
                'oauth2_token' => $options['oauth2_token'],
            ];
        }

        parent::__construct($options);
    }

    /**
     * @psalm-suppress MethodSignatureMismatch
     * @throws GuzzleException
     */
    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        if (
            !empty($this->authentication_options['username'])
            && !empty($this->authentication_options['password'])
        ) {
            $options['auth'] = [
                $this->authentication_options['username'],
                $this->authentication_options['password'],
            ];
        } elseif (!empty($this->authentication_options['http_token'])) {
            $options['headers']['Authorization']
                = 'Token token=' . $this->authentication_options['http_token'];
        } elseif (!empty($this->authentication_options['oauth2_token'])) {
            $options['headers']['Authorization']
                = 'Bearer ' . $this->authentication_options['oauth2_token'];
        } else {
            throw new \RuntimeException('No authentication options available');
        }

        try {
            $response = parent::request($method, $uri, $options);
        } catch (TransferException $e) {
            if (
                method_exists($e, 'hasResponse')
                && method_exists($e, 'getResponse')
                && $e->hasResponse()
            ) {
                return $e->getResponse();
            }
            throw $e;
        }

        return $response;
    }
}
