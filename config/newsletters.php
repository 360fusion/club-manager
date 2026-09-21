<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sending pace
    |--------------------------------------------------------------------------
    |
    | Newsletters are sent from the "bulk" queue. If the mail provider limits how
    | fast you may send, set the most emails per second here (0 = no limit).
    |
    */

    'per_second' => (int) env('NEWSLETTER_PER_SECOND', 0),

];
