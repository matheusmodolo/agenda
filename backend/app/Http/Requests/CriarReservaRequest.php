<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CriarReservaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */

    public function rules(): array
    {
        return [
            'maquina_id' => ['required', 'integer', 'exists:maquinas,id'],
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date'],
        ];
    }
}
