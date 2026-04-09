<?php

namespace App\Models;

use App\Domain\Auth\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContract extends Model
{
    use HasFactory;

    protected $table = 'client_contracts';

    protected $fillable = [
        'id_empresa',
        'id_plano',
        'id_plano_contratado',
        
        // Dados do Cliente
        'cliente_razao_social',
        'cliente_cnpj_cpf',
        'cliente_email',
        'cliente_endereco',
        
        // Valores
        'valor_adesao',
        'valor_mensalidade',
        
        // Limites (JSON)
        'limites_contratados',
        
        // Contrato
        'versao_contrato',
        'titulo_contrato',
        'corpo_contrato',
        'hash_documento',
        
        // Assinatura
        'assinatura_base64',
        'assinado_por_nome',
        'assinado_por_documento',
        'assinado_por_email',
        
        // Rastreabilidade
        'ip_aceite',
        'user_agent',
        'aceito_em',
        
        // Recibo
        'recibo_gerado',
        'recibo_path',
        'recibo_gerado_em',
        
        // Controle
        'status',
        'motivo_revogacao',
        'revogado_em',
    ];

    protected $casts = [
        'limites_contratados' => 'array',
        'valor_adesao' => 'decimal:2',
        'valor_mensalidade' => 'decimal:2',
        'aceito_em' => 'datetime',
        'recibo_gerado' => 'boolean',
        'recibo_gerado_em' => 'datetime',
        'revogado_em' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Status
    public const STATUS_ATIVO = 'ativo';
    public const STATUS_REVOGADO = 'revogado';
    public const STATUS_SUBSTITUIDO = 'substituido';

    // =========================================================================
    // RELACIONAMENTOS
    // =========================================================================

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id_empresa');
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'id_plano', 'id_plano');
    }

    public function planoContratado(): BelongsTo
    {
        return $this->belongsTo(PlanoContratado::class, 'id_plano_contratado', 'id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeAtivos($query)
    {
        return $query->where('status', self::STATUS_ATIVO);
    }

    public function scopePorEmpresa($query, int $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    public function scopeComRecibo($query)
    {
        return $query->where('recibo_gerado', true);
    }

    // =========================================================================
    // ACESSORES
    // =========================================================================

    public function getValorAdesaoFormatadoAttribute(): string
    {
        return 'R$ ' . number_format((float) $this->valor_adesao, 2, ',', '.');
    }

    public function getValorMensalidadeFormatadoAttribute(): string
    {
        return 'R$ ' . number_format((float) $this->valor_mensalidade, 2, ',', '.');
    }

    public function getAceitoEmFormatadoAttribute(): string
    {
        return $this->aceito_em ? $this->aceito_em->format('d/m/Y H:i:s') : '-';
    }

    public function getCnpjCpfFormatadoAttribute(): string
    {
        $doc = preg_replace('/\D/', '', $this->cliente_cnpj_cpf);
        
        if (strlen($doc) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
        }
        
        if (strlen($doc) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
        }
        
        return $this->cliente_cnpj_cpf;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Verifica se o contrato está ativo
     */
    public function isAtivo(): bool
    {
        return $this->status === self::STATUS_ATIVO;
    }

    /**
     * Verifica integridade do documento comparando hash
     */
    public function verificarIntegridade(string $hashCalculado): bool
    {
        return hash_equals($this->hash_documento, $hashCalculado);
    }

    /**
     * Retorna os limites formatados para exibição
     */
    public function getLimitesFormatados(): array
    {
        $limites = $this->limites_contratados ?? [];
        $formatados = [];

        $labels = [
            'clientes' => 'Clientes',
            'produtos' => 'Produtos',
            'usuarios' => 'Usuários',
            'modelos_contrato' => 'Modelos de Contrato',
            'bancos_boleto' => 'Bancos para Boleto',
            'locacoes' => 'Locações',
            'financeiro' => 'Financeiro',
            'relatorios' => 'Relatórios',
            'assinatura_digital' => 'Assinatura Digital',
        ];

        foreach ($limites as $key => $valor) {
            $label = $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
            
            if (is_numeric($valor)) {
                $formatados[$label] = $valor == 0 ? 'Ilimitado' : number_format($valor, 0, ',', '.');
            } elseif (is_bool($valor) || in_array($valor, ['S', 'N', 'sim', 'nao', true, false], true)) {
                $formatados[$label] = in_array($valor, ['S', 'sim', true, 1], true) ? 'Sim' : 'Não';
            } else {
                $formatados[$label] = (string) $valor;
            }
        }

        return $formatados;
    }

    /**
     * Marca contrato como substituído (quando há upgrade/downgrade)
     */
    public function marcarComoSubstituido(string $motivo = 'Substituído por novo contrato'): bool
    {
        return $this->update([
            'status' => self::STATUS_SUBSTITUIDO,
            'motivo_revogacao' => $motivo,
            'revogado_em' => now(),
        ]);
    }

    /**
     * Revoga o contrato
     */
    public function revogar(string $motivo): bool
    {
        return $this->update([
            'status' => self::STATUS_REVOGADO,
            'motivo_revogacao' => $motivo,
            'revogado_em' => now(),
        ]);
    }

    /**
     * Registra que o recibo foi gerado
     */
    public function registrarReciboGerado(string $path): bool
    {
        return $this->update([
            'recibo_gerado' => true,
            'recibo_path' => $path,
            'recibo_gerado_em' => now(),
        ]);
    }

    /**
     * Buscar contrato ativo da empresa
     */
    public static function contratoAtivoDaEmpresa(int $idEmpresa): ?self
    {
        return static::where('id_empresa', $idEmpresa)
            ->where('status', self::STATUS_ATIVO)
            ->orderBy('aceito_em', 'desc')
            ->first();
    }

    /**
     * Gera a string canônica para cálculo do hash
     */
    public static function gerarStringParaHash(array $dados): string
    {
        $partes = [
            'empresa_id' => $dados['id_empresa'] ?? '',
            'cliente_razao_social' => $dados['cliente_razao_social'] ?? '',
            'cliente_cnpj_cpf' => preg_replace('/\D/', '', $dados['cliente_cnpj_cpf'] ?? ''),
            'valor_adesao' => number_format((float) ($dados['valor_adesao'] ?? 0), 2, '.', ''),
            'valor_mensalidade' => number_format((float) ($dados['valor_mensalidade'] ?? 0), 2, '.', ''),
            'limites' => is_array($dados['limites_contratados'] ?? null) 
                ? json_encode($dados['limites_contratados'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) 
                : ($dados['limites_contratados'] ?? ''),
            'versao_contrato' => $dados['versao_contrato'] ?? '1.0',
            'corpo_contrato' => $dados['corpo_contrato'] ?? '',
            'aceito_em' => $dados['aceito_em'] ?? '',
        ];

        return implode('|', $partes);
    }

    /**
     * Calcula o hash SHA-256 do documento
     */
    public static function calcularHash(array $dados): string
    {
        $string = self::gerarStringParaHash($dados);
        return hash('sha256', $string);
    }
}
