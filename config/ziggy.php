<?php

/*
| Ziggy puts every named route into a script on each page. Visitors only need the routes of the areas they can
| open, which also stops a logged-out visitor reading the app's admin and superadmin route map.
|
| The group is chosen in resources/views/app.blade.php, and HandleInertiaRequests::version() includes it, so a
| visitor who signs in or out is sent to a full page load that carries the right table.
*/
return [
    'groups' => [
        'guest' => ['!admin.*', '!superadmin.*', '!webhooks.*', '!auth.*', '!api.*', '!passkey.*'],
        'member' => ['!superadmin.*', '!webhooks.*', '!auth.*', '!api.*', '!passkey.*'],
    ],
];
