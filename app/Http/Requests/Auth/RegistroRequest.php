<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            // Dados básicos da empresa
            'razao_social' => ['required', 'string', 'max:255'],
            'cnpj' => ['required','string','max:18'],
            'id_plano' => ['nullable', 'integer', 'exists:planos,id_plano'],
            'plano' => ['nullable', 'string', 'max:100'],
            
            // Dados do usuário administrador
            'nome' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('usuarios', 'login')->whereNull('deleted_at'),
            ],
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'razao_social.required' => 'A razão social é obrigatória.',
            'razao_social.max' => 'A razão social não pode ter mais de 255 caracteres.',
            'cnpj.required' => 'O CNPJ é obrigatório.',
            'cnpj.max' => 'O CNPJ não pode ter mais de 18 caracteres.',
            'id_plano.exists' => 'Plano de teste inválido.',
            
            'nome.required' => 'O nome é obrigatório.',
            'nome.max' => 'O nome não pode ter mais de 255 caracteres.',
            
            'email.required' => 'O email é obrigatório.',
            'email.email' => 'Formato de email inválido.',
            'email.unique' => 'Este email já está cadastrado.',
            'email.max' => 'O email não pode ter mais de 255 caracteres.',
        ];
    }

    public function getDadosEmpresa(): array
    {
        return [
            'razao_social' => $this->razao_social,
            'nome_empresa' => $this->razao_social, // usar razão social como nome da empresa
            'cnpj' => $this->cnpj,
        ];
    }

    public function getDadosUsuario(): array
    {
        return [
            'nome' => $this->nome,
            'email' => $this->email,
            'senha' => $this->senha,
            'login' => $this->email, // usar email como login
        ];
    }
}