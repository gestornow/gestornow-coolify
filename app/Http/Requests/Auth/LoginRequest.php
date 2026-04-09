<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'login' => [
                'required',
                'string',
                'min:3',
                'max:255'
            ],
            'senha' => [
                'required',
                'string',
                'min:6'
            ],
            // Checkbox envia "on" quando marcado. Usar accepted evita erro de boolean.
            'lembrar' => [
                'sometimes',
                'accepted'
            ]
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'login.required' => 'O campo login é obrigatório.',
            'login.min' => 'O login deve ter pelo menos 3 caracteres.',
            'login.max' => 'O login não pode ter mais de 255 caracteres.',
            'senha.required' => 'O campo senha é obrigatório.',
            'senha.min' => 'A senha deve ter pelo menos 6 caracteres.',
            'lembrar.accepted' => 'Marque a opção Lembrar-me apenas se estiver em um dispositivo seguro.'
        ];
    }

    /**
     * Get custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'login' => 'login',
            'senha' => 'senha',
            'lembrar' => 'lembrar-me'
        ];
    }
}