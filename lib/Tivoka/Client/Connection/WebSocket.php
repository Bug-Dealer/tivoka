<?php

namespace Tivoka\Client\Connection;

use Tivoka\Client\BatchRequest;
use Tivoka\Exception;
use Tivoka\Client\Request;
use WebSocket\Client;

/**
 * WebSocket connection
 * @package Tivoka
 *
 * @author Fredrik Liljegren <fredrik.liljegren@textalk.se>
 *
 * The WebSocket itself is handled by textalk/websocket package.
 */
class WebSocket extends AbstractConnection
{
    protected $url; ///< Given URL
    protected $ws_client; ///< The WebSocket\Client instance

    /**
     * Constructs connection.
     *
     * @param string $host Server host.
     * @param int $port Server port.
     * @param array $options
     *   Associative array containing:
     *   - headers:  Request headers to add/override.
     *   - timeout:  Socket connect and wait timeout in seconds.
     */
    public function __construct($url, $options = [])
    {
        $this->url = $url;

        if (isset($options['timeout'])) {
            $this->timeout = $options['timeout'];
        } else {
            $options['timeout'] = $this->timeout;
        }

        $this->ws_client = new Client($url, $options);
    }

    /**
     * Changes timeout.
     * @param int $timeout
     * @return WebSocket Self reference.
     */
    public function setTimeout($timeout)
    {
        $this->ws_client->setTimeout($timeout);
        return parent::setTimeout($timeout);
    }

    /**
     * Sends a JSON-RPC request over plain WebSocket.
     *
     * @param Request[] $requests
     * @return Request|BatchRequest If sent as a batch request the BatchRequest object will be returned.
     * @throws Exception\ConnectionException
     * @throws Exception\Exception
     * @throws Exception\SpecException
     * @throws Exception\SyntaxException
     */
    public function send(Request ...$requests): Request
    {
        $request = count($requests) === 1 ? reset($requests) : new BatchRequest($requests);

        if (!($request instanceof Request)) {
            throw new Exception\Exception('Invalid data type to be sent to server');
        }

        // sending request
        $this->ws_client->send($request->getRequest($this->spec), 'text', true);

        // read server respons
        $response = $this->ws_client->receive();

        if (($opcode = $this->ws_client->getLastOpcode()) !== 'text') {
            throw new Exception\ConnectionException(
                "Received non-text frame of type '$opcode' with text: " . $response
            );
        }
        $request->setResponse($response);
        return $request;
    }

    /**
     * @return string The websocket URI used.
     */
    public function getUri(): string
    {
        return $this->url;
    }
}
