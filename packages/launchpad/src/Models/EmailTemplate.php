<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;

/** The edited wording of one mail; see Launchpad\Mail\Templates */
class EmailTemplate extends Model
{
    protected $guarded = ['id'];
}
