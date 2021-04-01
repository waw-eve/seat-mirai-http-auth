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

namespace Warlof\Seat\Connector\Drivers\Mirai\Driver;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Arr;
use Seat\Services\Exceptions\SettingException;
use Warlof\Seat\Connector\Drivers\IClient;
use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;
use Warlof\Seat\Connector\Drivers\Mirai\Exceptions\CommandException;
use Warlof\Seat\Connector\Drivers\Mirai\Exceptions\ConnexionException;
use Warlof\Seat\Connector\Drivers\Mirai\Exceptions\LoginException;
use Warlof\Seat\Connector\Drivers\Mirai\Exceptions\ServerException;
use Warlof\Seat\Connector\Drivers\Mirai\Exceptions\MiraiException;
use Warlof\Seat\Connector\Exceptions\DriverException;
use Warlof\Seat\Connector\Exceptions\DriverSettingsException;
use Warlof\Seat\Connector\Exceptions\InvalidDriverIdentityException;

/**
 * Class MiraiHttpClient.
 *
 * @package Warlof\Seat\Connector\Drivers\Mirai\Driver
 */
class MiraiHttpClient implements IClient
{
    /**
     * @var \Warlof\Seat\Connector\Drivers\Mirai\Driver\MiraiHttpClient
     */
    private static $instance;

    /**
     * @var \Warlof\Seat\Connector\Drivers\IUser[]
     */
    private $mirai_users;

    /**
     * @var \Warlof\Seat\Connector\Drivers\ISet[]
     */
    private $server_groups;

    /**
     * @var \Warlof\Seat\Connector\Drivers\Mirai\Fetchers\IFetcher
     */
    private $client;

    /**
     * @var string
     */
    private $api_base_uri;

    /**
     * @var string
     */
    private $session_key;

    /**
     * @var string
     */
    private $bot_qq;

    /**
     * MiraiHttpClient constructor.
     *
     * @param array $parameters
     */
    public function __construct(array $parameters)
    {
        $this->api_base_uri   = $parameters['api_base_uri'];
        $this->bot_qq         = $parameters['bot_qq'];

        $this->mirai_users    = collect();
        $this->server_groups  = collect();

        $fetcher = config('mirai.config.fetcher');
        $this->client = new $fetcher($this->api_base_uri);

        $this->session_key = $this->sendCall('POST', '/auth', ['authKey' => $parameters['api_key']])['session'];
    }

    public function __destruct()
    {
        $this->sendCall('POST', '/release', ['sessionKey' => $this->session_key, 'qq' => $this->bot_qq]);
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\Mirai\Driver\MiraiHttpClient
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public static function getInstance(): IClient
    {
        if (!isset(self::$instance)) {
            try {
                $settings = setting('seat-connector.drivers.mirai', true);
            } catch (SettingException $e) {
                logger()->error(sprintf('[seat-connector][mirai] %d : %s', $e->getCode(), $e->getMessage()));
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }

            if (is_null($settings) || !is_object($settings))
                throw new DriverSettingsException('The Driver has not been configured yet.');

            if (!property_exists($settings, 'api_base_uri') || empty($settings->api_base_uri))
                throw new DriverSettingsException('Parameter api_base_uri is missing.');

            if (!property_exists($settings, 'api_key') || empty($settings->api_key))
                throw new DriverSettingsException('Parameter api_key is missing.');

            if (!property_exists($settings, 'bot_qq') || is_null($settings->bot_qq))
                throw new DriverSettingsException('Parameter bot_qq is missing.');

            self::$instance = new MiraiHttpClient([
                'api_base_uri' => $settings->api_base_uri,
                'api_key'      => $settings->api_key,
                'bot_qq'       => $settings->bot_qq,
            ]);
        }

        return self::$instance;
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\IUser[]
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getUsers(): array
    {
        if ($this->mirai_users->isEmpty()) {
            try {
                $this->seedMiraiUsers();
            } catch (MiraiException $e) {
                logger()->error(sprintf('[seat-connector][mirai] %d: %s', $e->getCode(), $e->getMessage()));
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->mirai_users->toArray();
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\ISet[]
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getSets(): array
    {
        if ($this->server_groups->isEmpty()) {
            try {
                $this->seedServerGroups();
            } catch (MiraiException $e) {
                logger()->error(sprintf('[seat-connector][mirai] %d : %s', $e->getCode(), $e->getMessage()));
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->server_groups->toArray();
    }

    /**
     * @param string $id
     * @return \Warlof\Seat\Connector\Drivers\IUser|null
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     * @throws \Warlof\Seat\Connector\Exceptions\InvalidDriverIdentityException
     */
    public function getUser(string $id): ?IUser
    {
        if ($this->mirai_users->isEmpty()) {
            try {
                $this->seedMiraiUsers();
            } catch (MiraiException $e) {
                logger()->error(sprintf('[seat-connector][mirai] %d : %s', $e->getCode(), $e->getMessage()));
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

        $user = $this->mirai_users->get($id);

        if (is_null($user)) {
            try {
                // scope: manage_scope
                $response = $this->sendCall('GET', '/{instance}/clientdbinfo', [
                    'cldbid' => $id,
                    'instance' => $this->instance_id,
                ]);

                $client_info = Arr::first($response);

                // TODO 实现获取用户 https://github.com/project-mirai/mirai-api-http/blob/master/docs/API.md#%E8%8E%B7%E5%8F%96%E7%BE%A4%E6%88%90%E5%91%98%E5%88%97%E8%A1%A8
                $mirai_user = new MiraiUser([
                    'client_database_id' => $client_info->client_database_id,
                    'client_unique_identifier' => $client_info->client_unique_identifier,
                    'client_nickname' => $client_info->client_nickname,
                ]);

                $this->mirai_users->put($mirai_user->getClientId(), $mirai_user);
            } catch (MiraiException $e) {
                logger()->error(sprintf('[seat-connector][mirai] %d : %s', $e->getCode(), $e->getMessage()));

                if ($e->getCode() == 512)
                    throw new InvalidDriverIdentityException(
                        sprintf('User ID %s is not found on Teamspeak Server.', $id),
                        $e->getCode(),
                        $e
                    );

                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $user;
    }

    /**
     * @param string $id
     * @return \Warlof\Seat\Connector\Drivers\ISet|null
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getSet(string $id): ?ISet
    {
        if ($this->server_groups->isEmpty()) {
            try {
                $this->seedServerGroups();
            } catch (MiraiException $e) {
                logger()->error(sprintf('[seat-connector][mirai] %d : %s', $e->getCode(), $e->getMessage()));
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->server_groups->get($id);
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IUser $mirai_user
     * @return bool
     */
    public function setMiraiUserName(IUser $mirai_user)
    {
        $groups = $mirai_user->getSets();
        //TODO 实现修改名称 https://github.com/project-mirai/mirai-api-http/blob/master/docs/API.md#%E4%BF%AE%E6%94%B9%E7%BE%A4%E5%91%98%E8%B5%84%E6%96%99
        return true;
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IUser $mirai_user
     * @param \Warlof\Seat\Connector\Drivers\ISet $server_group
     * @throws \Warlof\Seat\Connector\Drivers\Mirai\Exceptions\MiraiException
     */
    public function removeMiraiUserFromServerGroup(IUser $mirai_user, ISet $server_group)
    {
        // TODO 实现移除成员 https://github.com/project-mirai/mirai-api-http/blob/master/docs/API.md#%E7%A7%BB%E9%99%A4%E7%BE%A4%E6%88%90%E5%91%98
        $this->sendCall('POST', '/{instance}/servergroupdelclient', [
            'sgid'     => $server_group->getId(),
            'cldbid'   => $mirai_user->getClientId(),
            'instance' => $this->instance_id,
        ]);
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\ISet $server_group
     * @return IUser[]
     * @throws \Warlof\Seat\Connector\Drivers\Mirai\Exceptions\MiraiException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getServerGroupMembers(ISet $server_group): array
    {
        // TODO 实现获取成员列表
        $response = $this->sendCall('GET', '/memberList?sessionKey=YourSessionKey&target={123456789}', [
            'sgid'     => $server_group->getId(),
            'instance' => $this->instance_id,
        ]);

        $mirai_users = [];

        foreach ($response as $element) {
            $mirai_users[] = $this->getUser($element->cldbid);
        }

        return $mirai_users;
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IUser $mirai_user
     * @return ISet[]
     * @throws \Warlof\Seat\Connector\Drivers\Mirai\Exceptions\CommandException
     */
    public function getMiraiUserServerGroups(IUser $mirai_user): array
    {
        // TODO 实现根据成员获取组
        $response = $this->sendCall('GET', '/{instance}/serverinfo', [
            'instance' => $this->instance_id,
        ]);

        $server_info = Arr::first($response);

        // scope: manage_scope
        $response = $this->sendCall('GET', '/{instance}/servergroupsbyclientid', [
            'cldbid' => $mirai_user->getClientId(),
            'instance' => $this->instance_id,
        ]);

        $server_group = [];

        foreach ($response as $element) {

            // ignore default server group - since it's automatically assigned
            if ($element->sgid == $server_info->virtualserver_default_server_group)
                continue;

            $server_group[] = new MiraiServerGroup([
                'sgid' => $element->sgid,
                'name' => $element->name,
            ]);
        }

        return $server_group;
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $arguments
     * @return array
     * @throws \Warlof\Seat\Connector\Drivers\Mirai\Exceptions\CommandException
     * @throws \Warlof\Seat\Connector\Drivers\Mirai\Exceptions\LoginException
     */
    private function sendCall(string $method, string $endpoint, array $arguments = []): array
    {
        $uri = ltrim($endpoint, '/');
        $method = strtoupper($method);

        if ($endpoint != '/auth') {
            // TODO 实现自动添加sessionKey
        }

        foreach ($arguments as $uri_parameter => $value) {
            if (strpos($uri, sprintf('{%s}', $uri_parameter)) === false)
                continue;

            $uri = str_replace(sprintf('{%s}', $uri_parameter), $value, $uri);

            Arr::pull($arguments, $uri_parameter);
        }

        try {
            if ($method == 'GET') {
                $response = $this->client->request($method, $uri, [
                    'query' => $arguments,
                ]);
            } else {
                $response = $this->client->request($method, $uri, [
                    'body' => json_encode($arguments),
                ]);
            }

            logger()->debug(
                sprintf(
                    '[seat-connector][mirai] [http %d, %s] %s -> /%s',
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                    $method,
                    $uri
                ),
                $method == 'GET' ? [
                    'response' => [
                        'body' => $response->getBody()->getContents(),
                    ],
                ] : [
                    'request' => [
                        'body' => json_encode($arguments),
                    ],
                    'response' => [
                        'body' => $response->getBody()->getContents(),
                    ],
                ],
            );
        } catch (ConnectException $e) {
            throw new ConnexionException($e->getMessage(), $e->getCode(), $e);
        } catch (RequestException $e) {
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }

        $result = json_decode($response->getBody());

        if ($result->code !== 0) {
            if (in_array($result->code, [1, 2, 3, 4]))
                throw new LoginException($result->status->message, $result->status->code);

            throw new CommandException($result->status->message, $result->status->code);
        }

        return $result ?? [];
    }

    /**
     * @throws \Warlof\Seat\Connector\Drivers\Mirai\Exceptions\CommandException
     * @throws \Warlof\Seat\Connector\Drivers\Mirai\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Drivers\Mirai\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Drivers\Mirai\Exceptions\ServerException
     */
    private function seedMiraiUsers()
    {
        // TODO 实现初始化获取用户
        $from        = 0;

        while (true) {
            try {
                // scope: manage_scope
                $response = $this->sendCall('GET', '/{instance}/clientdblist', [
                    'start' => $from,
                    'instance' => $this->instance_id,
                ]);

                foreach ($response as $identity) {
                    $mirai_user = new MiraiUser([
                        'cldbid' => $identity->cldbid,
                        'client_unique_identifier' => $identity->client_unique_identifier,
                        'client_nickname' => $identity->client_nickname,
                    ]);

                    $this->mirai_users->put($mirai_user->getClientId(), $mirai_user);
                    $from++;
                }
            } catch (MiraiException $e) {
                if ($e->getCode() == 1281)
                    break;

                throw $e;
            }
        }
    }

    /**
     * @throws \Warlof\Seat\Connector\Drivers\Mirai\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Drivers\Mirai\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Drivers\Mirai\Exceptions\ServerException
     * @throws \Warlof\Seat\Connector\Drivers\Mirai\Exceptions\CommandException
     */
    private function seedServerGroups()
    {
        // TODO 实现初始化获取群组列表 https://github.com/project-mirai/mirai-api-http/blob/master/docs/API.md#%E8%8E%B7%E5%8F%96%E7%BE%A4%E5%88%97%E8%A1%A8
        $response = $this->sendCall('GET', '/{instance}/serverinfo', [
            'instance' => $this->instance_id,
        ]);

        $server_info = Arr::first($response);

        // scope: manage_scope
        $response = $this->sendCall('GET', '/{instance}/servergrouplist', [
            'instance' => $this->instance_id,
        ]);

        foreach ($response as $group) {

            // ignore default server group - since it's automatically assigned
            if ($group->sgid == $server_info->virtualserver_default_server_group)
                continue;

            // groupDbType (0 = template, 1 = normal, 2 = query)
            if ($group->type != '1')
                continue;

            $server_group = new MiraiServerGroup([
                'sgid' => $group->sgid,
                'name' => $group->name,
            ]);

            $this->server_groups->put($server_group->getId(), $server_group);
        }
    }
}
