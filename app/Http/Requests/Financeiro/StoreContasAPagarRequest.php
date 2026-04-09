<?php

namespace App\Http\Requests\Financeiro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContasAPagarRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Será controlado pela Policy
    }

    /**
     * Preparar dados para validação
     */
    protected function prepareForValidation()
    {
        // Converter valores monetários de formato BR para decimal
        $data = [];
        
        $camposMonetarios = ['valor_total', 'juros', 'multa', 'desconto', 'valor_pago'];
        
        foreach ($camposMonetarios as $campo) {
            if ($this->has($campo)) {
                $data[$campo] = $this->convertMoneyToDecimal($this->input($campo));
            }
        }
        
        // Sanitizar campos de texto para prevenir XSS
        $camposTexto = ['descricao', 'observacoes', 'documento', 'boleto'];
        foreach ($camposTexto as $campo) {
            if ($this->has($campo)) {
                $data[$campo] = $this->sanitizeHtml($this->input($campo));
            }
        }
        
        // Converter parcelas customizadas
        if ($this->has('parcelas_custom')) {
            $parcelas = $this->input('parcelas_custom');
            foreach ($parcelas as $key => $parcela) {
                if (isset($parcela['valor'])) {
                    $parcelas[$key]['valor'] = $this->convertMoneyToDecimal($parcela['valor']);
                }
                if (isset($parcela['descricao'])) {
                    $parcelas[$key]['descricao'] = $this->sanitizeHtml($parcela['descricao']);
                }
            }
            $data['parcelas_custom'] = $parcelas;
        }
        
        $this->merge($data);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'descricao' => ['required', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:100'],
            'boleto' => ['nullable', 'string', 'max:100'],
            'id_fornecedores' => ['nullable', Rule::exists('fornecedores', 'id_fornecedores')->where(fn ($query) => $query->where('id_empresa', session('id_empresa')))], // Segurança: restringe FK à empresa da sessão para bloquear IDOR.
            'id_usuario' => ['nullable', Rule::exists('usuarios', 'id_usuario')->where(fn ($query) => $query->where('id_empresa', session('id_empresa')))], // Segurança: restringe FK à empresa da sessão para bloquear IDOR.
            'id_categoria_contas' => ['nullable', Rule::exists('categoria_contas', 'id_categoria_contas')->where(fn ($query) => $query->where('id_empresa', session('id_empresa')))], // Segurança: restringe FK à empresa da sessão para bloquear IDOR.
            'id_bancos' => ['nullable', Rule::exists('bancos', 'id_bancos')->where(fn ($query) => $query->where('id_empresa', session('id_empresa')))], // Segurança: restringe FK à empresa da sessão para bloquear IDOR.
            'id_forma_pagamento' => ['nullable', Rule::exists('forma_pagamento', 'id_forma_pagamento')->where(fn ($query) => $query->where('id_empresa', session('id_empresa')))], // Segurança: restringe FK à empresa da sessão para bloquear IDOR.
            'valor_total' => ['required', 'numeric', 'min:0.01'],
            'juros' => ['nullable', 'numeric', 'min:0'],
            'multa' => ['nullable', 'numeric', 'min:0'],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'valor_pago' => ['nullable', 'numeric', 'min:0'],
            'data_emissao' => ['nullable', 'date'],
            'data_vencimento' => ['required_unless:tipo_lancamento,parcelado_customizado', 'nullable', 'date'],
            'data_pagamento' => ['nullable', 'date'],
            'status' => ['required', 'in:pendente,pago,vencido,parcelado,cancelado'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
            'tipo_lancamento' => ['required', 'in:unico,parcelado,parcelado_customizado,recorrente'],
            'total_parcelas' => ['required_if:tipo_lancamento,parcelado', 'nullable', 'integer', 'min:2', 'max:120'],
            'intervalo_parcelas' => ['nullable', 'in:7,15,30,60,90,custom'],
            'intervalo_custom' => ['required_if:intervalo_parcelas,custom', 'nullable', 'integer', 'min:1', 'max:365'],
            'num_parcelas_customizadas' => ['required_if:tipo_lancamento,parcelado_customizado', 'nullable', 'integer', 'min:2', 'max:120'],
            'intervalo_parcelas_custom' => ['nullable', 'in:7,15,30,60,90'],
            'tipo_recorrencia' => ['required_if:tipo_lancamento,recorrente', 'nullable', 'in:diario,semanal,quinzenal,mensal,bimestral,trimestral,semestral,anual'],
            'quantidade_recorrencias' => ['required_if:tipo_lancamento,recorrente', 'nullable', 'integer', 'min:2', 'max:365'],
            'parcelas_custom' => ['required_if:tipo_lancamento,parcelado_customizado', 'nullable', 'array'],
            'parcelas_custom.*.descricao' => ['required', 'string', 'max:255'],
            'parcelas_custom.*.data_vencimento' => ['required', 'date'],
            'parcelas_custom.*.valor' => ['required', 'numeric', 'min:0.01'],
        ];
        
        // Adicionar validação condicional para status pago
        if ($this->input('status') === 'pago') {
            $rules['data_pagamento'] = ['required', 'date'];
        }
        
        return $rules;
    }

    /**
     * Mensagens de erro personalizadas
     */
    public function messages(): array
    {
        return [
            'descricao.required' => 'A descrição é obrigatória',
            'valor_total.required' => 'O valor total é obrigatório',
            'valor_total.numeric' => 'O valor total deve ser um número válido',
            'valor_total.min' => 'O valor total deve ser maior que zero',
            'data_vencimento.required_unless' => 'A data de vencimento é obrigatória',
            'status.required' => 'O status é obrigatório',
            'status.in' => 'Status inválido',
            'tipo_lancamento.required' => 'O tipo de lançamento é obrigatório',
            'total_parcelas.required_if' => 'Informe o número de parcelas',
            'total_parcelas.min' => 'O mínimo é 2 parcelas',
            'total_parcelas.max' => 'O máximo é 120 parcelas',
            'tipo_recorrencia.required_if' => 'Informe o tipo de recorrência',
            'quantidade_recorrencias.required_if' => 'Informe a quantidade de recorrências',
            'parcelas_custom.required_if' => 'Configure as parcelas',
            'parcelas_custom.*.descricao.required' => 'A descrição da parcela é obrigatória',
            'parcelas_custom.*.data_vencimento.required' => 'A data de vencimento da parcela é obrigatória',
            'parcelas_custom.*.valor.required' => 'O valor da parcela é obrigatório',
            'parcelas_custom.*.valor.min' => 'O valor da parcela deve ser maior que zero',
            'data_pagamento.required' => 'A data de pagamento é obrigatória para contas pagas',
            'id_forma_pagamento.required' => 'A forma de pagamento é obrigatória para contas pagas',
        ];
    }

    /**
     * Converter valor monetário do formato BR para decimal
     */
    private function convertMoneyToDecimal($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        
        if (empty($value)) {
            return 0;
        }
        
        // Remove pontos de milhar e substitui vírgula por ponto
        $cleaned = str_replace(['.', ','], ['', '.'], $value);
        return (float) $cleaned;
    }

    /**
     * Sanitizar HTML para prevenir XSS
     */
    private function sanitizeHtml(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        // Remove tags HTML e PHP
        $value = strip_tags($value);
        
        // Remove caracteres especiais perigosos
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        return trim($value);
    }
}
