<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoriaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:255',
            'tipo' => 'required|string|in:despesa,receita',
            'descricao' => 'nullable|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'O nome da categoria é obrigatório.',
            'nome.unique' => 'Já existe uma categoria com este nome.',
            'nome.max' => 'O nome não pode ter mais de 255 caracteres.',
            'tipo.required' => 'O tipo da categoria é obrigatório.',
            'tipo.in' => 'O tipo de categoria deve ser "despesa" ou "receita".',
        ];
    }

    public function validated($key = null, $default = null): array
    {
        return array_merge(parent::validated(), [
            'id_empresa' => session('id_empresa'), // Segurança: força id_empresa da sessão para bloquear IDOR/mass assignment.
        ]);
    }
}
