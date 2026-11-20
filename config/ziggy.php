<?php

/*
| Ziggy puts every named route into a script on each page. Visitors only need the routes of the areas they can
| open, which also stops a logged-out visitor reading the app's admin and superadmin route map.
|
| The group is chosen in resources/views/app.blade.php, and HandleInertiaRequests::version() includes it, so a
| visitor who signs in or out is sent to a full page load that carries the right table.
*/
$hidden = ['!superadmin.*', '!webhooks.*', '!auth.*', '!api.*', '!passkey.*', '!passed_on.*'];

return [
    'groups' => [
        'guest' => ['!admin.*', ...$hidden],
        // A member with no staff role and no office: no club admin routes (most of the table, slow on a weak signal),
        // apart from the security page, which is named admin.* but lives in the member area. Exclusions always win in
        // Ziggy, so admin routes are left out by section; a new section is simply sent until it is listed here.
        'member' => [...$hidden, ...array_map(fn (string $section) => "!admin.{$section}.*", [
            'accounting', 'activity', 'analytics', 'candidates', 'charity', 'club_acc', 'clubs', 'committee', 'email_templates',
            'events', 'media', 'meetings', 'memberships', 'newsletters', 'notices', 'officers', 'pages', 'payment_options',
            'posts', 'settings', 'updates', 'users', 'visitors', 'webhooks',
        ]), '!admin.analytics'],
        // Anyone with a staff role or an office: their member pages link into the club admin.
        'staff' => $hidden,
    ],
];
