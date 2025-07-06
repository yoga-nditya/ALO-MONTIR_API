<?php

namespace App\Http\Controllers;
use App\Models\Garage;

use Illuminate\Http\Request;

class GarageController extends Controller
{
    public function index()
    {
        return response()->json(Garage::all());
    }
}
