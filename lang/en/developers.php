<?php

return [
    'title' => 'For developers',
    'meta' => 'graj.club API for game server owners: vote-callback with vote rewards, vote checks, online push, website widgets.',
    'lead' => 'Reward players for voting, show your ranking and online on your website, push online from the server. The key and server ID are in your account, on the server page.',
    'nav' => ['callback' => 'Vote-callback', 'api' => 'API', 'check' => 'Vote check', 'online' => 'Online push', 'widgets' => 'Widgets', 'openapi' => 'OpenAPI'],
    'callback' => [
        'title' => 'Vote-callback: a reward right after the vote',
        'lead' => 'Set your handler URL in your account. After every counted vote the portal sends a POST with a JSON body. Reply with 2xx — otherwise there are up to 5 retries with growing delays. Delivery log and a “Test request” button are in your account.',
        'signature' => 'The body is signed with HMAC-SHA256 using your server key; the signature is in the X-Graj-Signature header. Verify it before giving a reward and compare in constant time.',
        'interval' => 'A player can vote once every :n h, so the vote reward needs no extra checks.',
    ],
    'api' => [
        'title' => 'API',
        'lead' => 'REST, JSON, base URL below. Public methods need no key; owner methods need Authorization: Bearer <key>. Rate limit — 60 requests per minute.',
        'method' => 'Method',
        'path' => 'Path',
        'about' => 'Returns',
        'key' => 'Key required',
    ],
    'check' => [
        'title' => 'Vote check (pull mode)',
        'lead' => 'For builds that do not accept incoming requests (old Lineage 2 servers): a player types an in-game command and the server asks the portal whether this nickname or IP voted in the last :n h.',
    ],
    'online' => [
        'title' => 'Online push',
        'lead' => 'Lineage 2 and games without a query protocol push their online themselves — every few minutes. Without it the portal only sees whether the port is open.',
    ],
    'widgets' => [
        'title' => 'Website widgets',
        'lead' => 'A “Vote” button and a status banner (online, players, ranking) in several sizes. Images refresh every 5 minutes. Ready-made HTML and BBCode snippets are in your account, on the server page.',
        'vote' => '“Vote” button',
        'status' => 'Status banner',
    ],
    'openapi' => [
        'title' => 'OpenAPI',
        'lead' => 'The full description of methods and the vote-callback in OpenAPI 3.1 — for Postman, Insomnia or client generation.',
        'download' => 'Download openapi.json',
    ],
    'yes' => 'yes',
    'no' => 'no',
];
