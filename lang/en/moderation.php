<?php

return [
    'mail' => [
        'reason' => 'Reason: :reason',
        'open' => 'Open dashboard',
        'approved' => [
            'subject' => 'Server :server is published',
            'line' => 'A moderator approved :server. It is now in the ranking and players can vote for it.',
        ],
        'rejected' => [
            'subject' => 'Server :server did not pass moderation',
            'line' => 'A moderator rejected :server. Fix the issues and resubmit it from your dashboard.',
        ],
        'changes_approved' => [
            'subject' => 'Changes to :server approved',
            'line' => 'The new details of :server are live.',
        ],
        'changes_rejected' => [
            'subject' => 'Changes to :server rejected',
            'line' => 'A moderator rejected the changes to :server. The previous details stay on the site.',
        ],
        'blocked' => [
            'subject' => 'Server :server is blocked',
            'line' => 'Server :server has been blocked and removed from the ranking.',
        ],
    ],
];
