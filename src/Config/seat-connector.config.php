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

return [
    'name'     => 'mirai',
    'icon'     => 'fab fa-qq',
    'client'   => \Warlof\Seat\Connector\Drivers\Mirai\Driver\MiraiHttpClient::class,
    'settings' => [
        [
            'name'  => 'api_base_uri',
            'label' => 'seat-connector-mirai::seat.api_base_uri',
            'type'  => 'url',
        ],
        [
            'name'  => 'api_key',
            'label' => 'seat-connector-mirai::seat.api_key',
            'type'  => 'text',
        ],
        [
            'name'  => 'bot_qq',
            'label' => 'seat-connector-mirai::seat.bot_qq',
            'type'  => 'text',
        ],
    ],
];
