<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Garage extends Model
{
    use HasFactory;

    protected $table = 'garages';

    protected $fillable = [
        'name',
        'mechanic_name',
        'location',
    ];
}
