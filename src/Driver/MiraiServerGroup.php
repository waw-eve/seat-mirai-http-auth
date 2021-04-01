<?php

/*
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

namespace Warlof\Seat\Connector\Drivers\Mirai\Driver;

use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;
use Warlof\Seat\Connector\Drivers\Mirai\Exceptions\MiraiException;
use Warlof\Seat\Connector\Exceptions\DriverException;

/**
 * Class MiraiServerGroup.
 *
 * @package Warlof\Seat\Connector\Drivers\Mirai\Driver
 */
class MiraiServerGroup implements ISet
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var \Warlof\Seat\Connector\Drivers\IUser[]
     */
    private $members;

    /**
     * MiraiServerGroup constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->members = collect();
        $this->hydrate($attributes);
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getMembers(): array
    {
        if ($this->members->isEmpty()) {
            try {
                $this->members = collect(MiraiHttpClient::getInstance()->getServerGroupMembers($this));
            } catch (MiraiException $e) {
                logger()->error(sprintf('[seat-connector][mirai] %d : %s', $e->getCode(), $e->getMessage()));
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->members->toArray();
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IUser $user
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function addMember(IUser $user)
    {
        if (in_array($user, $this->getMembers()))
            return;

        $this->members->put($user->getClientId(), $user);
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IUser $user
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function removeMember(IUser $user)
    {
        if (!in_array($user, $this->getMembers()))
            return;

        try {
            MiraiHttpClient::getInstance()->removeMiraiUserFromServerGroup($user, $this);
        } catch (MiraiException $e) {
            logger()->error(sprintf('[seat-connector][mirai] %d : %s', $e->getCode(), $e->getMessage()));
            throw new DriverException($e->getMessage(), $e->getCode(), $e);
        }

        $this->members->pull($user->getClientId());
    }

    /**
     * @param array $attributes
     * @return $this
     */
    public function hydrate(array $attributes)
    {
        $this->id   = $attributes['id'];
        $this->name = $attributes['name'];

        return $this;
    }
}
