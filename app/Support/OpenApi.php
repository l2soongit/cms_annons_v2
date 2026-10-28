<?php

namespace App\Support;

/**
 * Специфікація OpenAPI 3.1 публічного API (ТЗ 7.2) для /developers/openapi.json. Описи — мовою сторінки (developers.spec.*).
 */
final class OpenApi
{
    public static function spec(): array
    {
        $t = fn (string $key) => __("developers.spec.{$key}");
        $server = ['$ref' => '#/components/schemas/Server'];
        $id = ['name' => 'id', 'in' => 'path', 'required' => true, 'description' => $t('id_param'), 'schema' => ['type' => 'integer']];
        $json = fn (array $schema, string $description = 'OK') => ['description' => $description, 'content' => ['application/json' => ['schema' => $schema]]];
        $unauthorized = ['401' => ['description' => $t('unauthorized')]];

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'graj.club API',
                'version' => '1.0.0',
                'description' => $t('info'),
            ],
            'servers' => [['url' => url('/api/v1')]],
            'components' => [
                'securitySchemes' => [
                    'serverKey' => ['type' => 'http', 'scheme' => 'bearer', 'description' => $t('security')],
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
                        'uptime' => ['type' => 'number', 'description' => $t('uptime')],
                        'votes_month' => ['type' => 'integer'],
                        'rank' => ['type' => ['integer', 'null'], 'description' => $t('rank')],
                        'checked_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    ]],
                ],
            ],
            'paths' => [
                '/servers/{id}' => ['get' => [
                    'summary' => $t('server_summary'),
                    'parameters' => [$id],
                    'responses' => ['200' => $json(['type' => 'object', 'properties' => ['data' => $server]]), '404' => ['description' => $t('not_published')]],
                ]],
                '/games/{slug}/top' => ['get' => [
                    'summary' => $t('top_summary'),
                    'parameters' => [['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'example' => 'lineage2']]],
                    'responses' => ['200' => $json(['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => $server]]])],
                ]],
                '/servers/{id}/votes' => ['get' => [
                    'summary' => $t('votes_summary'),
                    'security' => [['serverKey' => []]],
                    'parameters' => [$id,
                        ['name' => 'from', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time']],
                        ['name' => 'to', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time']]],
                    'responses' => ['200' => $json(['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'id' => ['type' => 'integer'], 'nickname' => ['type' => ['string', 'null']], 'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ]]]]])] + $unauthorized,
                ]],
                '/servers/{id}/votes/check' => ['get' => [
                    'summary' => $t('check_summary'),
                    'security' => [['serverKey' => []]],
                    'parameters' => [$id,
                        ['name' => 'nickname', 'in' => 'query', 'schema' => ['type' => 'string', 'maxLength' => 32]],
                        ['name' => 'ip', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'ipv4']]],
                    'responses' => ['200' => $json(['type' => 'object', 'properties' => [
                        'voted' => ['type' => 'boolean'], 'vote_id' => ['type' => ['integer', 'null']], 'voted_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    ]])] + $unauthorized,
                ]],
                '/servers/{id}/online' => ['post' => [
                    'summary' => $t('online_summary'),
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
                    'summary' => $t('callback_summary'),
                    'description' => $t('callback_description'),
                    'parameters' => [['name' => 'X-Graj-Signature', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string']]],
                    'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
                        'server_id' => ['type' => 'integer'], 'vote_id' => ['type' => 'integer'], 'nickname' => ['type' => ['string', 'null']],
                        'ip' => ['type' => 'string'], 'timestamp' => ['type' => 'integer', 'description' => $t('timestamp')],
                    ]]]]],
                    'responses' => ['200' => ['description' => $t('reward_ok')]],
                ]],
            ],
        ];
    }
}
