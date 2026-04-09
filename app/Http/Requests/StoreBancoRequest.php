<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBancoRequest extends FormRequest
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
            'nome_banco' => 'required|string|max:255|unique:bancos,nome_banco,NULL,id_bancos,id_empresa,' . session('id_empresa'),
            'agencia' => 'nullable|string|max:50',
            'conta' => 'nullable|string|max:50',
            'saldo_inicial' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nome_banco.required' => 'O nome do banco é obrigatório.',
            'nome_banco.unique' => 'Já existe um banco com este nome.',
            'nome_banco.max' => 'O nome do banco não pode ter mais de 255 caracteres.',
            'agencia.max' => 'A agência não pode ter mais de 50 caracteres.',
            'conta.max' => 'A conta não pode ter mais de 50 caracteres.',
            'saldo_inicial.numeric' => 'O saldo inicial deve ser um número válido.',
            'saldo_inicial.min' => 'O saldo inicial não pode ser negativo.',
            'observacoes.max' => 'As observações não podem ter mais de 500 caracteres.',
        ];
    }

    /**
     * Get the validated data with enterprise id.
     */
    public function validated(): array
    {
        return array_merge(parent::validated(), [
            'id_empresa' => session('id_empresa'),
        ]);
    }
}
