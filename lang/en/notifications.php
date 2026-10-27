<?php

return [
    'offline' => [
        'subject' => 'Server :server is offline',
        'line' => 'Monitoring cannot reach :server (:address) since :time. Check that the server is running and the port is open.',
    ],
    'review' => [
        'subject' => 'New review of :server',
        'line' => 'A player left a review rated :rating of 5.',
        'open' => 'Read and reply',
    ],
];
