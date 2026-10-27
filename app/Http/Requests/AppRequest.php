<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A form that is not posted to a club's admin area (member, public, super admin or sign-in forms). Who may post it is
 * decided by the route's middleware before this runs, so authorize() only has to let it through.
 */
abstract class AppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
