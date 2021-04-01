<?php

/**
 * This file is part of SeAT Discord Connector.
 *
 * Copyright (C) 2021  Kagurazaka Nyaa <developer@waw-eve.com>
 *
 * SeAT Discord Connector  is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * SeAT Discord Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Warlof\Seat\Connector\Drivers\Mirai\Http\Middleware\Throttlers;

use Illuminate\Support\Facades\Redis;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class RateLimiterMiddleware.
 *
 * @package Warlof\Seat\Connector\Drivers\Mirai\Http\Middleware\Throttlers
 */
class RateLimiterMiddleware
{
    const REDIS_CACHE_PREFIX = 'seat:seat-connector.drivers.mirai';

    /**
     * @param callable $handler
     * @return \Closure
     */
    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            // determine request timestamp
            $now = time();

            // retrieve throttler metadata for requested endpoint
            $key = $this->getCacheKey($request->getUri());
            $metadata = Redis::get($key) ?: null;

            if (!is_null($metadata)) {
                $metadata = unserialize($metadata);

                // compute delay between reset time and current time,
                // the limit is removed after the exact time
                $delay = $metadata->reset - $now;

                // in case limit is near to be reached, we pause the request for computed duration
                if ($metadata->remaining < 2 && $delay > 0)
                    sleep($delay);
            }

            // send the request and retrieve response
            $promise = $handler($request, $options);

            return $promise->then(function (ResponseInterface $response) use ($key) {

                // update cache entry for the endpoint using new RateLimit / RateReset values
                $metadata = $this->getEndpointMetadata($response);
                Redis::setex($key, 60 * 60 * 24 * 7, serialize($metadata));

                // forward response to the stack
                return $response;
            });
        };
    }

    /**
     * @param \Psr\Http\Message\UriInterface $uri
     * @param string $type
     * @return string
     */
    private function getCacheKey(UriInterface $uri)
    {
        // generate a hash based on the endpoint
        $hash = sha1(sprintf('%s:%d', $uri->getHost(), $uri->getPort()));

        // return a cache key built using prefix, hash and requested type
        return sprintf('%s.%s.metadata', self::REDIS_CACHE_PREFIX, $hash);
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return object
     */
    private function getEndpointMetadata(ResponseInterface $response)
    {
        return (object) [
            'reset'     => now()->addMilliseconds(1000)->getTimestamp(),
            'remaining' => 0,
        ];
    }
}
