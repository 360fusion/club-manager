<?php

namespace App\Http\Controllers;

use App\Models\Club;

abstract class Controller
{
    /**
     * The club named in the URL. The admin middleware has usually loaded it already, so it is read from the request
     * instead of being queried again by every action.
     */
    protected function findClub(string $slug): Club
    {
        $request = request();

        return $request->attributes->get("club:{$slug}")
            ?? tap(Club::where('slug', $slug)->firstOrFail(), fn (Club $club) => $request->attributes->set("club:{$slug}", $club));
    }
}
