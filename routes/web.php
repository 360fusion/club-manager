<?php

// Loaded in this order: the first route that matches wins, so the order of these files matters.
require __DIR__.'/public.php';
require __DIR__.'/club.php';
require __DIR__.'/superadmin.php';
require __DIR__.'/legacy.php';
