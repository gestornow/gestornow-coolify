<?php

namespace App\Http\Requests\Usuario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'login' => [
                'required',
                'string',
                'max:255',
                Rule::unique('usuarios', 'login')->whereNull('deleted_at'),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:50'],
            'cpf' => ['nullable', 'string', 'max:20'],
            'id_perfil_global' => ['nullable', 'integer'],
            'is_suporte' => ['nullable', 'boolean'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'cep' => ['nullable', 'string', 'max:10'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'comissao' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'observacoes' => ['nullable', 'string'],
            'id_empresa' => ['prohibited'], // Segurança: impede id_empresa vindo do payload para evitar mass assignment.
            'metodo_senha' => ['required', 'in:email,direto'],
        ];

        // Se o método é criar senha diretamente, validar os campos de senha
        if ($this->input('metodo_senha') === 'direto') {
            $rules['senha'] = ['required', 'confirmed', Password::min(8)->letters()->numbers()];
            $rules['senha_confirmation'] = ['required'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'login.required' => 'O login é obrigatório.',
            'login.unique' => 'Este login já está sendo utilizado.',
            'login.max' => 'O login não pode ter mais de 255 caracteres.',
            
            'nome.required' => 'O nome é obrigatório.',
            'nome.max' => 'O nome não pode ter mais de 255 caracteres.',
            
            'telefone.max' => 'O telefone não pode ter mais de 50 caracteres.',
            'cpf.max' => 'O CPF não pode ter mais de 20 caracteres.',
            
            'endereco.max' => 'O endereço não pode ter mais de 255 caracteres.',
            'cep.max' => 'O CEP não pode ter mais de 10 caracteres.',
            'bairro.max' => 'O bairro não pode ter mais de 100 caracteres.',
            
            'comissao.numeric' => 'A comissão deve ser um número.',
            'comissao.min' => 'A comissão não pode ser menor que 0.',
            'comissao.max' => 'A comissão não pode ser maior que 100.',
            
            'id_empresa.prohibited' => 'O id_empresa não pode ser enviado no payload.',
            
            'metodo_senha.required' => 'Escolha como definir a senha.',
            'metodo_senha.in' => 'Método de senha inválido.',
            
            'senha.required' => 'A senha é obrigatória.',
            'senha.confirmed' => 'A confirmação da senha não confere.',
            'senha.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'senha_confirmation.required' => 'A confirmação da senha é obrigatória.',
        ];
    }
}
