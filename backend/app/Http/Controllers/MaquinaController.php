<?php

namespace App\Http\Controllers;

use App\Models\Maquina;
use Illuminate\Http\Request;

class MaquinaController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Maquina::orderBy('nome')->get(['id', 'nome', 'tipo', 'ativo'])
        ]);
    }
}
