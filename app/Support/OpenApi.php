<?php

namespace App\Support;

/**
 * Специфікація OpenAPI 3.1 публічного API (ТЗ 7.2) для /developers/openapi.json.
 */
final class OpenApi
{
    public static function spec(): array
    {
        $server = ['$ref' => '#/components/schemas/Server'];
        $id = ['name' => 'id', 'in' => 'path', 'required' => true, 'description' => 'ID сервера (кабінет → API-ключ)', 'schema' => ['type' => 'integer']];
        $json = fn (array $schema, string $description = 'OK') => ['description' => $description, 'content' => ['application/json' => ['schema' => $schema]]];
        $unauthorized = ['401' => ['description' => 'Немає або неправильний ключ сервера']];

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'graj.club API',
                'version' => '1.0.0',
                'description' => 'Публічні дані серверів і API власника: перевірка голосу, список голосів, передача онлайну. Ліміт — '.'60 запитів на хвилину.',
            ],
            'servers' => [['url' => url('/api/v1')]],
            'components' => [
                'securitySchemes' => [
                    'serverKey' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Секретний ключ сервера з кабінету: Authorization: Bearer <ключ>'],
                ],
                'schemas' => [
                    'Server' => ['type' => 'object', 'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'url' => ['type' => 'string', 'format' => 'uri'],
                        'address' => ['type' => 'string', 'example' => 'play.example.net:7777'],
                        'online' => ['type' => 'boolean'],
                        'players' => ['type' => 'integer'],
                        'max_players' => ['type' => 'integer'],
                        'uptime' => ['type' => 'number', 'description' => 'Аптайм за 30 днів, %'],
                        'votes_month' => ['type' => 'integer'],
                        'rank' => ['type' => ['integer', 'null'], 'description' => 'Місце в рейтингу гри'],
                        'checked_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    ]],
                ],
            ],
            'paths' => [
                '/servers/{id}' => ['get' => [
                    'summary' => 'Дані й статус сервера, місце в рейтингу',
                    'parameters' => [$id],
                    'responses' => ['200' => $json(['type' => 'object', 'properties' => ['data' => $server]]), '404' => ['description' => 'Сервер не опублікований']],
                ]],
                '/games/{slug}/top' => ['get' => [
                    'summary' => 'Топ-100 серверів гри',
                    'parameters' => [['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'example' => 'lineage2']]],
                    'responses' => ['200' => $json(['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => $server]]])],
                ]],
                '/servers/{id}/votes' => ['get' => [
                    'summary' => 'Голоси за період (для власника, до 1000 останніх)',
                    'security' => [['serverKey' => []]],
                    'parameters' => [$id,
                        ['name' => 'from', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time']],
                        ['name' => 'to', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time']]],
                    'responses' => ['200' => $json(['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'id' => ['type' => 'integer'], 'nickname' => ['type' => ['string', 'null']], 'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ]]]]])] + $unauthorized,
                ]],
                '/servers/{id}/votes/check' => ['get' => [
                    'summary' => 'Чи голосував нік або IP за останній інтервал (pull-режим для збірок без callback)',
                    'security' => [['serverKey' => []]],
                    'parameters' => [$id,
                        ['name' => 'nickname', 'in' => 'query', 'schema' => ['type' => 'string', 'maxLength' => 32]],
                        ['name' => 'ip', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'ipv4']]],
                    'responses' => ['200' => $json(['type' => 'object', 'properties' => [
                        'voted' => ['type' => 'boolean'], 'vote_id' => ['type' => ['integer', 'null']], 'voted_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    ]])] + $unauthorized,
                ]],
                '/servers/{id}/online' => ['post' => [
                    'summary' => 'Передати онлайн із сервера (Lineage 2 та ігри без query-протоколу)',
                    'security' => [['serverKey' => []]],
                    'parameters' => [$id],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['online', 'players'], 'properties' => [
                        'online' => ['type' => 'boolean'], 'players' => ['type' => 'integer', 'minimum' => 0], 'max_players' => ['type' => 'integer', 'minimum' => 0],
                    ]]]]],
                    'responses' => ['200' => $json(['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']]])] + $unauthorized,
                ]],
            ],
            'webhooks' => [
                'voteCallback' => ['post' => [
                    'summary' => 'Vote-callback: портал надсилає на URL власника після кожного зарахованого голосу',
                    'description' => 'Тіло підписане HMAC-SHA256 ключем сервера, підпис — у заголовку X-Graj-Signature. До 5 повторів зі зростаючою паузою, якщо відповідь не 2xx.',
                    'parameters' => [['name' => 'X-Graj-Signature', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string']]],
                    'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
                        'server_id' => ['type' => 'integer'], 'vote_id' => ['type' => 'integer'], 'nickname' => ['type' => ['string', 'null']],
                        'ip' => ['type' => 'string'], 'timestamp' => ['type' => 'integer', 'description' => 'Unix-час голосу'],
                    ]]]]],
                    'responses' => ['200' => ['description' => 'Бонус видано (будь-яка відповідь 2xx)']],
                ]],
            ],
        ];
    }
}
