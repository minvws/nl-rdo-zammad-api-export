<?php

namespace Minvws\Zammad;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use ZammadAPIClient\HTTPClientInterface;

class HTTPClient extends Client implements HTTPClientInterface
{
    public function __construct( array $options = [] )
    {
        $options['base_uri'] = $options['url'] . '/api/' . \ZammadAPIClient\Client::API_VERSION . '/';

        // Authentication
        if ( !empty( $options['username'] ) || !empty( $options['password'] ) ) {
            $this->authentication_options = [
                'username' => $options['username'],
                'password' => $options['password'],
            ];
        }

        if ( !empty( $options['http_token'] ) ) {
            $this->authentication_options = [
                'http_token' => $options['http_token'],
            ];
        }

        if ( !empty( $options['oauth2_token'] ) ) {
            $this->authentication_options = [
                'oauth2_token' => $options['oauth2_token'],
            ];
        }

        parent::__construct($options);
    }

    public function request(string $method, $uri = '', array $options = [] ): ResponseInterface
    {
        // Username and password
        if (
            !empty( $this->authentication_options['username'] )
            && !empty( $this->authentication_options['password'] )
        ) {
            $options['auth'] = [
                $this->authentication_options['username'],
                $this->authentication_options['password'],
            ];
        }
        // HTTP token
        else if ( !empty( $this->authentication_options['http_token'] ) ) {
            $options['headers']['Authorization']
                = 'Token token=' . $this->authentication_options['http_token'];
        }
        // OAuth2 token
        else if ( !empty( $this->authentication_options['oauth2_token'] ) ) {
            $options['headers']['Authorization']
                = 'Bearer ' . $this->authentication_options['oauth2_token'];
        }
        else {
            throw new \RuntimeException('No authentication options available');
        }

        try {
            $response = parent::request( $method, $uri, $options );
        }
        catch ( \GuzzleHttp\Exception\GuzzleException $e ) {
            $response = new Response();
        }

        return $response;
    }
}
