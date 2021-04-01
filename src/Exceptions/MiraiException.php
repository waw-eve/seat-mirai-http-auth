<?php

/**
 * This file is part of SeAT Mirai Connector.
 *
 * Copyright (C) 2021  Kagurazaka Nyaa <developer@waw-eve.com>
 *
 * SeAT Mirai Connector  is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * SeAT Mirai Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Warlof\Seat\Connector\Drivers\Mirai\Exceptions;

use Exception;
use Throwable;

/**
 * Class MiraiException.
 *
 * @package Warlof\Seat\Connector\Drivers\Mirai\Exceptions
 */
abstract class MiraiException extends Exception
{
    /**
     * MiraiException constructor.
     *
     * @param string $error
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $error, int $code = 0, Throwable $previous = null)
    {
        parent::__construct($error, $code, $previous);
    }
}
