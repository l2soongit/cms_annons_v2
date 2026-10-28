<?php

return [
    'title' => 'Dla deweloperów',
    'meta' => 'API graj.club dla właścicieli serwerów gier: vote-callback z nagrodami za głos, sprawdzanie głosu, przesyłanie online, widżety na stronę.',
    'lead' => 'Przyznawaj graczom nagrody za głos, pokazuj ranking i online na swojej stronie, przesyłaj online z serwera. Klucz i ID serwera znajdziesz w panelu, na stronie serwera.',
    'nav' => ['callback' => 'Vote-callback', 'api' => 'API', 'check' => 'Sprawdzanie głosu', 'online' => 'Przesyłanie online', 'widgets' => 'Widżety', 'openapi' => 'OpenAPI'],
    'callback' => [
        'title' => 'Vote-callback: nagroda zaraz po głosie',
        'lead' => 'Podaj URL handlera w panelu. Po każdym zaliczonym głosie portal wyśle POST z treścią JSON. Odpowiedz kodem 2xx — w przeciwnym razie nastąpi do 5 ponowień z rosnącą przerwą. Dziennik dostarczeń i przycisk „Żądanie testowe” są w panelu.',
        'signature' => 'Treść jest podpisana HMAC-SHA256 kluczem Twojego serwera; podpis znajduje się w nagłówku X-Graj-Signature. Weryfikuj go przed przyznaniem nagrody i porównuj w sposób o stałym czasie.',
        'interval' => 'Gracz może głosować raz na :n godz., więc nagrodę za głos można przyznawać bez dodatkowych sprawdzeń.',
    ],
    'api' => [
        'title' => 'API',
        'lead' => 'REST, JSON, adres bazowy poniżej. Metody publiczne nie wymagają klucza; metody właściciela — nagłówek Authorization: Bearer <klucz>. Limit — 60 żądań na minutę.',
        'method' => 'Metoda',
        'path' => 'Ścieżka',
        'about' => 'Co zwraca',
        'key' => 'Wymaga klucza',
    ],
    'check' => [
        'title' => 'Sprawdzanie głosu (tryb pull)',
        'lead' => 'Dla wersji, które nie przyjmują żądań przychodzących (starsze serwery Lineage 2): gracz wpisuje komendę w grze, a serwer pyta portal, czy ten nick lub IP głosował w ciągu ostatnich :n godz.',
    ],
    'online' => [
        'title' => 'Przesyłanie online',
        'lead' => 'Lineage 2 i gry bez protokołu query przesyłają online samodzielnie — co kilka minut. Bez tego portal widzi tylko, czy port jest otwarty.',
    ],
    'widgets' => [
        'title' => 'Widżety na stronę',
        'lead' => 'Przycisk „Głosuj” i baner ze statusem (online, gracze, miejsce w rankingu) w kilku rozmiarach. Obrazki odświeżają się co 5 minut. Gotowe kody HTML i BBCode znajdziesz w panelu, na stronie serwera.',
        'vote' => 'Przycisk „Głosuj”',
        'status' => 'Baner ze statusem',
    ],
    'openapi' => [
        'title' => 'OpenAPI',
        'lead' => 'Pełny opis metod i vote-callback w formacie OpenAPI 3.1 — dla Postmana, Insomnii lub generowania klienta.',
        'download' => 'Pobierz openapi.json',
    ],
    'yes' => 'tak',
    'no' => 'nie',
];
