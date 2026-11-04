<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Cliente::orderBy('nome')->get(['nome'])
        ]);
    }
}
