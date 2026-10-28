<?php

return [
    'mail' => [
        'reason' => 'Powód: :reason',
        'open' => 'Otwórz w panelu',
        'approved' => [
            'subject' => 'Serwer :server został opublikowany',
            'line' => 'Moderator zatwierdził serwer :server. Jest już w rankingu, gracze mogą na niego głosować.',
        ],
        'rejected' => [
            'subject' => 'Serwer :server nie przeszedł moderacji',
            'line' => 'Moderator odrzucił serwer :server. Popraw uwagi i wyślij go ponownie z panelu.',
        ],
        'changes_approved' => [
            'subject' => 'Zmiany serwera :server zatwierdzone',
            'line' => 'Nowe dane serwera :server są już na stronie.',
        ],
        'changes_rejected' => [
            'subject' => 'Zmiany serwera :server odrzucone',
            'line' => 'Moderator odrzucił zmiany serwera :server. Na stronie pozostają poprzednie dane.',
        ],
        'blocked' => [
            'subject' => 'Serwer :server został zablokowany',
            'line' => 'Serwer :server został zablokowany i usunięty z rankingu.',
        ],
        'blocked_temp' => [
            'subject' => 'Serwer :server został tymczasowo zablokowany',
            'line' => 'Serwer :server został usunięty z rankingu do :until. Potem wróci automatycznie.',
        ],
        'unblocked' => [
            'subject' => 'Serwer :server został odblokowany',
            'line' => 'Blokada zdjęta: serwer :server jest znowu w rankingu.',
        ],
        'warned' => [
            'subject' => 'Ostrzeżenie dotyczące serwera :server',
            'line' => 'Moderator udzielił ostrzeżenia serwerowi :server. Ponowne naruszenie może skutkować blokadą.',
        ],
        'user_warned' => [
            'subject' => 'Ostrzeżenie od moderatora graj.club',
            'line' => 'Moderator udzielił Ci ostrzeżenia. Ponowne naruszenie regulaminu może skutkować blokadą konta.',
        ],
    ],
];
