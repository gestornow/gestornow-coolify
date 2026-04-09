<?php

namespace App\Domain\Locacao\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domain\Cliente\Models\Cliente;
use App\Domain\Produto\Models\Produto;
use App\Domain\Auth\Models\Empresa;
use App\Domain\Locacao\Models\LocacaoAssinaturaDigital;
use Illuminate\Support\Facades\Schema;

class Locacao extends Model
{
    use SoftDeletes, RegistraAtividade;

    protected $table = 'locacao';
    protected $primaryKey = 'id_locacao';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_cliente',
        'id_usuario',
        'numero_contrato',
        // Período da locação (saída/retorno)
        'data_inicio',      // Data de saída
        'hora_inicio',      // Hora de saída
        'data_fim',         // Data de retorno
        'hora_fim',         // Hora de retorno
        'locacao_por_hora', // Define cobrança por hora quando marcado manualmente
        'quantidade_dias',  // Total de dias calculado
        // Transporte (opcional - usado para cálculo de estoque se preenchido)
        'data_transporte_ida',
        'hora_transporte_ida',
        'data_transporte_volta',
        'hora_transporte_volta',
        // Endereço e contato do evento
        'local_entrega',
        'local_retirada',
        'contato_responsavel',
        'telefone_responsavel',
        'nome_obra',
        'contato_local',
        'telefone_contato',
        'local_evento',
        'endereco_entrega',
        'cidade',
        'estado',
        'cep',
        // Nota fiscal
        'numero_nf',
        'serie_nf',
        'vencimento',
        // Valores
        'valor_total',
        'valor_frete',
        'valor_frete_entrega',
        'valor_frete_retirada',
        'valor_despesas_extras',
        'valor_desconto',
        'valor_acrescimo',
        'valor_imposto',
        'valor_final',
        'valor_limite_medicao',
        // Pagamento
        'forma_pagamento',
        'condicao_pagamento',
        // Status e informações gerais
        'status',           // orcamento, aprovado, em_andamento, retirada, encerrado, cancelado, atrasada, medicao
        'status_logistica', // para_separar, pronto_patio, em_rota, entregue, aguardando_coleta
        'responsavel',
        'observacoes',
        'observacoes_orcamento',
        'observacoes_recibo',
        'observacoes_entrega',
        'observacoes_checklist',
        'aditivo',
        'renovacao_automatica',
        'id_locacao_origem',
        'id_locacao_anterior',
        'numero_orcamento',
        'numero_orcamento_origem',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'data_transporte_ida' => 'datetime',
        'data_transporte_volta' => 'datetime',
        'vencimento' => 'date',
        'valor_total' => 'decimal:2',
        'valor_frete' => 'decimal:2',
        'valor_frete_entrega' => 'decimal:2',
        'valor_frete_retirada' => 'decimal:2',
        'valor_despesas_extras' => 'decimal:2',
        'valor_desconto' => 'decimal:2',
        'valor_acrescimo' => 'decimal:2',
        'valor_imposto' => 'decimal:2',
        'valor_final' => 'decimal:2',
        'valor_limite_medicao' => 'decimal:2',
        'locacao_por_hora' => 'boolean',
        'quantidade_dias' => 'integer',
        'aditivo' => 'integer',
        'renovacao_automatica' => 'boolean',
        'id_locacao_origem' => 'integer',
        'id_locacao_anterior' => 'integer',
        'numero_orcamento' => 'integer',
        'numero_orcamento_origem' => 'integer',
    ];

    /**
     * Status disponíveis para criação (inicial)
     */
    public static function statusInicial()
    {
        return [
            'orcamento' => 'Orçamento',
            'aprovado' => 'Aprovado',
        ];
    }

    /**
     * Status disponíveis (todos os status para atualizações)
     */
    public static function statusList()
    {
        return [
            'orcamento' => 'Orçamento',
            'aprovado' => 'Aprovado',
            'medicao' => 'Medição',
            'medicao_finalizada' => 'Medição Finalizada',
            'retirada' => 'Retirada',
            'em_andamento' => 'Em Andamento',
            'atrasada' => 'Atrasada',
            'encerrado' => 'Encerrado',
            'cancelado' => 'Cancelado',
            'cancelada' => 'Cancelado',
        ];
    }

    public static function statusLogisticaList(): array
    {
        return [
            'para_separar' => 'Para Separar',
            'pronto_patio' => 'Pronto / No Pátio',
            'em_rota' => 'Em Rota',
            'entregue' => 'Entregue',
            'aguardando_coleta' => 'Aguardando Coleta',
        ];
    }

    /**
     * Cores dos status para badges
     */
    public static function statusColors()
    {
        return [
            'orcamento' => 'secondary',
            'aprovado' => 'info',
            'medicao' => 'dark',
            'medicao_finalizada' => 'success',
            'retirada' => 'warning',
            'em_andamento' => 'primary',
            'atrasada' => 'danger',
            'encerrado' => 'success',
            'cancelado' => 'danger',
            'cancelada' => 'danger',
        ];
    }

    /**
     * Accessor para valor formatado
     */
    public function getValorTotalFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->valor_total ?? 0, 2, ',', '.');
    }

    /**
     * Accessor para valor final formatado
     */
    public function getValorFinalFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->valor_final ?? 0, 2, ',', '.');
    }

    /**
     * Accessor para período formatado
     */
    public function getPeriodoFormatadoAttribute()
    {
        $inicio = $this->data_inicio ? $this->data_inicio->format('d/m/Y') : '-';
        $fim = $this->data_fim ? $this->data_fim->format('d/m/Y') : '-';
        $horaInicio = $this->hora_inicio ?? '';
        $horaFim = $this->hora_fim ?? '';
        
        if ($horaInicio && $horaFim) {
            return "{$inicio} {$horaInicio} até {$fim} {$horaFim}";
        }
        return "{$inicio} até {$fim}";
    }

    public function getCodigoDisplayAttribute(): string
    {
        if ($this->status === 'orcamento') {
            if ($this->temColunaLocacao('numero_orcamento') && !empty($this->numero_orcamento)) {
                return str_pad((string) $this->numero_orcamento, 3, '0', STR_PAD_LEFT);
            }

            return $this->normalizarCodigoLegado($this->numero_contrato);
        }

        return $this->normalizarCodigoLegado($this->numero_contrato);
    }

    /**
     * Obter datas efetivas para cálculo de estoque
     * Se data_transporte_ida estiver preenchida, usa ela como base
     * Senão, usa data_inicio
     */
    public function getDatasEfetivasEstoque(): array
    {
        if ($this->data_transporte_ida) {
            return [
                'data_inicio' => $this->data_transporte_ida instanceof \DateTime 
                    ? $this->data_transporte_ida->format('Y-m-d') 
                    : $this->data_transporte_ida,
                'data_fim' => $this->data_transporte_volta instanceof \DateTime 
                    ? $this->data_transporte_volta->format('Y-m-d') 
                    : ($this->data_transporte_volta ?? ($this->data_fim instanceof \DateTime ? $this->data_fim->format('Y-m-d') : $this->data_fim)),
                'hora_inicio' => $this->hora_transporte_ida ?? $this->hora_inicio ?? '00:00',
                'hora_fim' => $this->hora_transporte_volta ?? $this->hora_fim ?? '23:59',
            ];
        }
        
        // Fallback: usar datas do contrato
        return [
            'data_inicio' => $this->data_inicio instanceof \DateTime 
                ? $this->data_inicio->format('Y-m-d') 
                : $this->data_inicio,
            'data_fim' => $this->data_fim instanceof \DateTime 
                ? $this->data_fim->format('Y-m-d') 
                : $this->data_fim,
            'hora_inicio' => $this->hora_inicio ?? '00:00',
            'hora_fim' => $this->hora_fim ?? '23:59',
        ];
    }

    /**
     * Scope para filtrar por empresa
     */
    public function scopeEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    /**
     * Scope para filtrar por status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para buscar
     */
    public function scopeBuscar($query, $termo)
    {
        return $query->where(function ($q) use ($termo) {
            $q->where('numero_contrato', 'like', "%{$termo}%")
              ->orWhere('observacoes', 'like', "%{$termo}%")
              ->orWhereHas('cliente', function($q) use ($termo) {
                  $q->where('nome', 'like', "%{$termo}%")
                    ->orWhere('razao_social', 'like', "%{$termo}%");
              });
        });
    }

    public function assinaturaDigital()
    {
        return $this->hasOne(LocacaoAssinaturaDigital::class, 'id_locacao', 'id_locacao')
            ->latest('id_assinatura');
    }

    public function assinaturasDigitais()
    {
        return $this->hasMany(LocacaoAssinaturaDigital::class, 'id_locacao', 'id_locacao')
            ->latest('id_assinatura');
    }

    /**
     * Relacionamento com cliente
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_clientes');
    }

    /**
     * Relacionamento com usuário
     */
    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Relacionamento com produtos da locação
     */
    public function produtos()
    {
        return $this->hasMany(LocacaoProduto::class, 'id_locacao', 'id_locacao');
    }

    /**
     * Relacionamento com produtos de terceiros da locação
     */
    public function produtosTerceiros()
    {
        return $this->hasMany(ProdutoTerceirosLocacao::class, 'id_locacao', 'id_locacao');
    }

    /**
     * Relacionamento com serviços da locação
     */
    public function servicos()
    {
        return $this->hasMany(LocacaoServico::class, 'id_locacao', 'id_locacao');
    }

    /**
     * Relacionamento com despesas da locação
     */
    public function despesas()
    {
        return $this->hasMany(LocacaoDespesa::class, 'id_locacao', 'id_locacao');
    }

    /**
     * Relacionamento com salas da locação
     */
    public function salas()
    {
        return $this->hasMany(LocacaoSala::class, 'id_locacao', 'id_locacao')->orderBy('ordem');
    }

    /**
     * Relacionamento com retornos de patrimônios
     */
    public function retornosPatrimonios()
    {
        return $this->hasMany(LocacaoRetornoPatrimonio::class, 'id_locacao', 'id_locacao');
    }

    /**
     * Relacionamento com faturamentos da locação
     */
    public function faturamentos()
    {
        return $this->hasMany(\App\Models\FaturamentoLocacao::class, 'id_locacao', 'id_locacao');
    }

    public function trocasProduto()
    {
        return $this->hasMany(LocacaoTrocaProduto::class, 'id_locacao', 'id_locacao');
    }

    public function checklists()
    {
        return $this->hasMany(LocacaoChecklist::class, 'id_locacao', 'id_locacao');
    }

    public function fotosChecklist()
    {
        return $this->hasMany(LocacaoChecklistFoto::class, 'id_locacao', 'id_locacao');
    }

    public function locacaoOrigem()
    {
        return $this->belongsTo(self::class, 'id_locacao_origem', 'id_locacao');
    }

    public function locacaoAnterior()
    {
        return $this->belongsTo(self::class, 'id_locacao_anterior', 'id_locacao');
    }

    public function aditivos()
    {
        return $this->hasMany(self::class, 'id_locacao_origem', 'id_locacao');
    }

    /**
     * Relacionamento com empresa
     */
    public function empresa()
    {
        return $this->belongsTo(\App\Domain\Auth\Models\Empresa::class, 'id_empresa', 'id_empresa');
    }

    /**
     * Verificar se tem patrimônios pendentes de retorno
     */
    public function temPatrimoniosPendentes(): bool
    {
        return $this->produtos()
            ->whereNotNull('id_patrimonio')
                        ->where(function ($q) {
                                $q->whereNull('estoque_status')
                                    ->orWhere('estoque_status', '!=', 2);
                        })
            ->where(function ($q) {
                $q->whereNull('status_retorno')
                  ->orWhere('status_retorno', 'pendente');
            })
            ->exists();
    }

    /**
     * Obter patrimônios pendentes de retorno
     */
    public function getPatrimoniosPendentes()
    {
        return $this->produtos()
            ->whereNotNull('id_patrimonio')
                        ->where(function ($q) {
                                $q->whereNull('estoque_status')
                                    ->orWhere('estoque_status', '!=', 2);
                        })
            ->where(function ($q) {
                $q->whereNull('status_retorno')
                  ->orWhere('status_retorno', 'pendente');
            })
            ->with(['produto', 'patrimonio'])
            ->get();
    }

    /**
     * Gerar número do contrato
     */
    public static function gerarNumeroContrato($idEmpresa)
    {
        $sequencial = self::gerarProximoNumeroContrato($idEmpresa, self::usaNumeracaoUnificada($idEmpresa));

        return str_pad((string) $sequencial, 3, '0', STR_PAD_LEFT);
    }

    public static function usaNumeracaoUnificada(int $idEmpresa): bool
    {
        $empresa = Empresa::query()
            ->select(['id_empresa', 'orcamentos_contratos'])
            ->where('id_empresa', $idEmpresa)
            ->first();

        return (int) ($empresa->orcamentos_contratos ?? 0) === 1;
    }

    public static function gerarProximoNumeroOrcamento(int $idEmpresa, bool $numeracaoUnificada = false): int
    {
        return self::gerarProximoNumero($idEmpresa, 'orcamento', $numeracaoUnificada);
    }

    public static function gerarProximoNumeroContrato(int $idEmpresa, bool $numeracaoUnificada = false): int
    {
        return self::gerarProximoNumero($idEmpresa, 'contrato', $numeracaoUnificada);
    }

    private static function gerarProximoNumero(int $idEmpresa, string $tipo, bool $numeracaoUnificada): int
    {
        $consulta = self::withTrashed()
            ->where('id_empresa', $idEmpresa)
            ->whereNotIn('status', ['cancelado', 'cancelada'])
            ->lockForUpdate();

        if ($numeracaoUnificada) {
            $locacoes = $consulta->get([
                'numero_contrato',
                self::hasColunaLocacao('numero_orcamento') ? 'numero_orcamento' : 'id_locacao',
            ]);

            $maior = 0;
            foreach ($locacoes as $locacao) {
                $maior = max(
                    $maior,
                    self::valorNumericoOrcamento($locacao),
                    self::valorNumericoContrato($locacao),
                    self::normalizarNumeroCodigo($locacao->numero_contrato)
                );
            }

            $proximo = $maior + 1;
            while (self::numeroContratoJaExiste($proximo)) {
                $proximo++;
            }

            return $proximo;
        }

        if ($tipo === 'orcamento') {
            if (self::hasColunaLocacao('numero_orcamento')) {
                $maior = (int) $consulta->where('status', 'orcamento')->max('numero_orcamento');
                return max(0, $maior) + 1;
            }

            $locacoes = $consulta->where('status', 'orcamento')->get(['numero_contrato']);
            $maior = 0;
            foreach ($locacoes as $locacao) {
                $maior = max($maior, self::normalizarNumeroCodigo($locacao->numero_contrato));
            }
            return $maior + 1;
        }

        $locacoes = $consulta->where('status', '!=', 'orcamento')->get(['numero_contrato']);
        $maior = 0;
        foreach ($locacoes as $locacao) {
            $maior = max($maior, self::normalizarNumeroCodigo($locacao->numero_contrato));
        }

        $proximo = $maior + 1;
        while (self::numeroContratoJaExiste($proximo)) {
            $proximo++;
        }

        return $proximo;
    }

    private static function numeroContratoJaExiste(int $numero): bool
    {
        if ($numero <= 0) {
            return false;
        }

        $codigoPadded = str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
        $codigoSemPadding = (string) $numero;

        return self::withTrashed()
            ->whereNotIn('status', ['cancelado', 'cancelada'])
            ->where(function ($query) use ($codigoPadded, $codigoSemPadding) {
                $query->where('numero_contrato', $codigoPadded)
                    ->orWhere('numero_contrato', $codigoSemPadding);
            })
            ->exists();
    }

    private static function numeroOrcamentoJaExiste(int $numero, int $idEmpresa): bool
    {
        if ($numero <= 0 || !self::hasColunaLocacao('numero_orcamento')) {
            return false;
        }

        return self::withTrashed()
            ->where('id_empresa', $idEmpresa)
            ->where('numero_orcamento', $numero)
            ->exists();
    }

    private static function valorNumericoOrcamento(self $locacao): int
    {
        if (self::hasColunaLocacao('numero_orcamento')) {
            return (int) ($locacao->numero_orcamento ?? 0);
        }

        return 0;
    }

    private static function valorNumericoContrato(self $locacao): int
    {
        return self::normalizarNumeroCodigo($locacao->numero_contrato ?? null);
    }

    private static function normalizarNumeroCodigo($codigo): int
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return 0;
        }

        if (is_numeric($codigo)) {
            return (int) $codigo;
        }

        if (preg_match('/(\d{1,})/', $codigo, $matches)) {
            return (int) ltrim($matches[1], '0') ?: (int) $matches[1];
        }

        return 0;
    }

    private static function hasColunaLocacao(string $coluna): bool
    {
        static $colunas = null;

        if ($colunas === null) {
            $colunas = Schema::hasTable('locacao')
                ? Schema::getColumnListing('locacao')
                : [];
        }

        return in_array($coluna, $colunas, true);
    }

    private function temColunaLocacao(string $coluna): bool
    {
        return self::hasColunaLocacao($coluna);
    }

    private function normalizarCodigoLegado($codigo): string
    {
        $numero = self::normalizarNumeroCodigo($codigo);

        if ($numero > 0) {
            return str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
        }

        return trim((string) ($codigo ?: '-'));
    }

    /**
     * Calcular valor total
     */
    public function calcularValorTotal()
    {
        $valorProdutos = $this->produtos()->sum(\DB::raw('quantidade * valor_unitario'));
        $valorServicos = $this->servicos()->sum('valor');
        $valorDespesas = $this->despesas()->sum('valor');

        $this->valor_total = $valorProdutos + $valorServicos + $valorDespesas;
        $this->valor_final = $this->valor_total - ($this->valor_desconto ?? 0) + ($this->valor_acrescimo ?? 0);
        $this->save();

        return $this->valor_final;
    }
    /**
     * Verifica se a locação está atrasada
     */
    public function estaAtrasada()
    {
        // Considera atrasada se a data de previsão de devolução for menor que hoje e status não for 'finalizada' ou 'cancelada'
        if (isset($this->data_previsao_devolucao)) {
            return $this->data_previsao_devolucao < now() && !in_array($this->status, ['finalizada', 'cancelada']);
        }
        return false;
    }
}
