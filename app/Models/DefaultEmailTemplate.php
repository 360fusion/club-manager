<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefaultEmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_key',
        'name',
        'subject',
        'body_html',
        'available_placeholders',
    ];

    protected $casts = [
        'available_placeholders' => 'array',
    ];
}
