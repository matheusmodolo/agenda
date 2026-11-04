<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Maquina extends Model
{
    /** @use HasFactory<\Database\Factories\MaquinaFactory> */
    use HasFactory;

    protected $fillable = ['nome', 'tipo', 'ativo'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
