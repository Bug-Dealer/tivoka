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

namespace Tivoka;

use Tivoka\Client\BatchRequest;
use Tivoka\Client\Connection\ConnectionInterface;
use Tivoka\Client\Notification;
use Tivoka\Client\Request;

/**
 * The public interface to all tivoka functions
 * @package Tivoka
 */
abstract class Client
{

    /**
     * Initializes a Connection to a remote server
     * @param mixed $target Remote end-point definition
     */
    public static function connect($target): ConnectionInterface
    {
        return Client\Connection\AbstractConnection::factory($target);
    }

    /**
     * alias of Tivoka\Client::createRequest
     * @see Client::createRequest
     */
    public static function request(string $method, array $params = null): Request
    {
        return self::createRequest($method, $params);
    }

    /**
     * Creates a request
     */
    public static function createRequest(string $method, array $params = null): Request
    {
        return new Client\Request($method, $params);
    }

    /**
     * alias of Tivoka\Client::createNotification
     * @see Client::createNotification
     */
    public static function notification(string $method, array $params = null): Notification
    {
        return self::createNotification($method, $params);
    }

    /**
     * Creates a notification
     */
    public static function createNotification(string $method, array $params = null): Notification
    {
        return new Client\Notification($method, $params);
    }

    /**
     * alias of Tivoka\Client::createBatch
     * @see Client::createBatch
     */
    public static function batch(Request ...$requests): BatchRequest
    {
        return self::createBatch($requests);
    }

    /**
     * Creates a batch request
     */
    public static function createBatch(Request ...$requests): BatchRequest
    {
        return new Client\BatchRequest($requests);
    }
}
