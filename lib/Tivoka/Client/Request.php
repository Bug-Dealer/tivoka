<?php

/**
 * Tivoka - JSON-RPC done right!
 * Copyright (c) 2011-2012 by Marcel Klehr <mklehr@gmx.net>
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package  Tivoka
 * @author Marcel Klehr <mklehr@gmx.net>
 * @author Rafał Wrzeszcz <rafal.wrzeszcz@wrzasq.pl>
 * @copyright (c) 2011-2012, Marcel Klehr
 */

namespace Tivoka\Client;

use Tivoka\Exception;
use Tivoka\Tivoka;
use Tivoka\Client\Connection\AbstractConnection;

/**
 * A JSON-RPC request
 * @package Tivoka
 */
class Request
{
    /**
     * @var int|mixed
     */
    public $spec;
    public $id;
    public $method;
    public $params;
    public $request;
    public $response;

    public $result;
    public $error;
    public $errorMessage;
    public $errorData;

    public $responseHeaders;
    public $responseHeadersRaw;

    /**
     * Constructs a new JSON-RPC request object
     * @param string $method The remote procedure to invoke
     * @param mixed $params Additional params for the remote procedure (optional)
     * @see Tivoka_Connection::send()
     */
    public function __construct($method, $params = null)
    {
        $this->id = self::uuid();
        $this->method = $method;
        $this->params = $params;
    }

    /**
     * @return string A v4 uuid
     */
    public static function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff), // time_low
            mt_rand(0, 0xffff), // time_mid
            mt_rand(0, 0x0fff) | 0x4000, // time_hi_and_version
            mt_rand(0, 0x3fff) | 0x8000, // clk_seq_hi_res/clk_seq_low
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff) // node
        );
    }

    /**
     * Get the raw, JSON-encoded request
     * @param int $spec
     */
    public function getRequest($spec)
    {
        $this->spec = $spec;
        return $this->request = json_encode(self::prepareRequest($spec, $this->id, $this->method, $this->params));
    }

    /**
     * Encodes the request properties
     *
     * @param mixed $id The id of the request
     * @param string $method The method to be called
     * @param array $params Additional parameters
     *
     * @return mixed the prepared assotiative array to encode
     * @throws Exception\SpecException
     */
    protected static function prepareRequest($spec, $id, $method, $params = null): array
    {
        switch ($spec) {
            case Tivoka::SPEC_2_0:
                $request = [
                    'jsonrpc' => '2.0',
                    'method' => $method,
                ];
                if ($id !== null) {
                    $request['id'] = $id;
                }
                if ($params !== null) {
                    $request['params'] = $params;
                }
                return $request;
            case Tivoka::SPEC_1_0:
                $request = [
                    'method' => $method,
                    'id' => $id
                ];
                if ($params !== null) {
                    if ((bool)count(array_filter(array_keys($params), 'is_string'))) {
                        throw new Exception\SpecException('JSON-RPC 1.0 doesn\'t allow named parameters');
                    }
                    $request['params'] = $params;
                }
                return $request;
        }
    }

    /**
     * Send this request to a remote server directly
     * @param mixed $target Remote end-point definition
     */
    public function sendTo($target): void
    {
        $connection = AbstractConnection::factory($target);
        $connection->send($this);
    }

    /**
     * Interprets the response
     * @throws Exception\ConnectionException
     * @throws Exception\SyntaxException
     */
    public function setResponse(string $response): void
    {
        $this->response = $response;

        //no response?
        if (trim($response) == '') {
            throw new Exception\ConnectionException('No response received');
        }

        //decode
        $resparr = json_decode($response, true);
        if ($resparr == null) {
            throw new Exception\SyntaxException('Invalid response encoding');
        }

        $this->interpretResponse($resparr);
    }

    /**
     * Interprets the parsed response
     * @throws Exception\SyntaxException
     */
    public function interpretResponse(array $json_struct): void
    {
        //server error?
        if (($error = self::interpretError($this->spec, $json_struct, $this->id)) !== false) {
            $this->error = $error['error']['code'];
            $this->errorMessage = $error['error']['message'];
            $this->errorData = $error['error']['data'] ?? null;
            return;
        }

        //valid result?
        if (($result = self::interpretResult($this->spec, $json_struct, $this->id)) !== false) {
            $this->result = $result['result'];
            return;
        }

        throw new Exception\SyntaxException('Invalid response structure');
    }

    /**
     * Checks whether the given response is valid and an error
     * @param array $assoc The parsed JSON-RPC response as an associative array
     * @param mixed $id The id of the original request
     * @return array parsed JSON object
     */
    protected static function interpretError($spec, array $assoc, $id)
    {
        switch ($spec) {
            case Tivoka::SPEC_2_0:
                if (isset($assoc['jsonrpc'], $assoc['error']) == false) {
                    return false;
                }
                if ($assoc['id'] != $id && $assoc['id'] != null && isset($assoc['id']) or $assoc['jsonrpc'] != '2.0') {
                    return false;
                }
                if (isset($assoc['error']['message'], $assoc['error']['code']) === false) {
                    return false;
                }
                return [
                    'id' => $assoc['id'],
                    'error' => $assoc['error']
                ];
            case Tivoka::SPEC_1_0:
                if (isset($assoc['error'], $assoc['id']) === false) {
                    return false;
                }
                if ($assoc['id'] != $id && $assoc['id'] !== null) {
                    return false;
                }
                if (isset($assoc['error']) === false) {
                    return false;
                }
                return [
                    'id' => $assoc['id'],
                    'error' => [
                        'data' => $assoc['error'],
                        'code' => $assoc['error'],
                        'message' => $assoc['error']
                    ]
                ];
        }
    }

    /**
     * Checks whether the given response is a valid result
     * @param array $assoc The parsed JSON-RPC response as an associative array
     * @param mixed $id The id of the original request
     * @return array the parsed JSON object
     */
    protected static function interpretResult($spec, array $assoc, $id)
    {
        switch ($spec) {
            case Tivoka::SPEC_2_0:
                if (
                    isset($assoc['jsonrpc'], $assoc['id']) === false ||
                    !array_key_exists('result', $assoc)
                ) {
                    return false;
                }
                if ($assoc['id'] !== $id || $assoc['jsonrpc'] != '2.0') {
                    return false;
                }
                return [
                    'id' => $assoc['id'],
                    'result' => $assoc['result']
                ];
            case Tivoka::SPEC_1_0:
                if (isset($assoc['result'], $assoc['id']) === false) {
                    return false;
                }
                if ($assoc['id'] !== $id && $assoc['result'] === null) {
                    return false;
                }
                return [
                    'id' => $assoc['id'],
                    'result' => $assoc['result']
                ];
        }
    }

    /**
     * Save and parse the HTTP headers
     * @param array $raw_headers array of string coming from $http_response_header magic var
     * @return void
     */
    public function setHeaders($raw_headers): void
    {
        $this->responseHeadersRaw = $raw_headers;
        $this->responseHeaders = self::httpParseHeaders($raw_headers);
    }

    /**
     * Parses headers as returned by magic variable $http_response_header
     * @param array $headers array of string coming from $http_response_header
     * @return array associative array linking a header label with its value
     */
    protected static function httpParseHeaders(array $headers): array
    {
        // rfc2616: The first line of a Response message is the Status-Line
        $headers = array_slice($headers, 1); // removing status-line

        $headers_array = [];
        foreach ($headers as $header) {
            preg_match('/(?P<label>[^ :]+):(?P<body>(.|\r?\n(?= +))*)$/', $header, $matches);
            if (isset($matches["label"]) && isset($matches["body"])) {
                $headers_array[$matches["label"]] = trim($matches["body"]);
            }
        }
        return $headers_array;
    }

    /**
     * Determines whether an error occurred
     * @return bool
     */
    public function isError(): bool
    {
        return isset($this->error);
    }
}
