<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactRequest extends Model
{
    protected $guarded = [];

    protected $casts = ['wedding_date' => 'date'];
}
