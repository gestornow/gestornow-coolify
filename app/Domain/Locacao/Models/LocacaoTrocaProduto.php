<?php

namespace App\Domain\Locacao\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use App\Domain\Produto\Models\Produto;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LocacaoTrocaProduto extends Model
{
    use RegistraAtividade;

    protected $table = 'locacao_troca_produto';
    protected $primaryKey = 'id_locacao_troca_produto';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_locacao',
        'id_produto_locacao',
        'id_produto_anterior',
        'id_produto_novo',
        'quantidade',
        'motivo',
        'observacoes',
        'estoque_movimentado',
        'id_usuario',
    ];

    protected $casts = [
        'quantidade' => 'integer',
        'estoque_movimentado' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function locacao()
    {
        return $this->belongsTo(Locacao::class, 'id_locacao', 'id_locacao');
    }

    public function itemLocacao()
    {
        return $this->belongsTo(LocacaoProduto::class, 'id_produto_locacao', 'id_produto_locacao');
    }

    public function produtoAnterior()
    {
        return $this->belongsTo(Produto::class, 'id_produto_anterior', 'id_produto');
    }

    public function produtoNovo()
    {
        return $this->belongsTo(Produto::class, 'id_produto_novo', 'id_produto');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function getPatrimonioAnteriorTrocaAttribute(): ?string
    {
        return $this->extrairCodigoPatrimonioDaObservacao('anterior');
    }

    public function getPatrimonioNovoTrocaAttribute(): ?string
    {
        return $this->extrairCodigoPatrimonioDaObservacao('novo');
    }

    private function extrairCodigoPatrimonioDaObservacao(string $tipo): ?string
    {
        $observacoes = trim((string) ($this->observacoes ?? ''));
        if ($observacoes === '') {
            return null;
        }

        $prefixo = $tipo === 'anterior' ? 'anterior' : 'novo';
        $padrao = '/Patrim[oô]nio\s+' . $prefixo . ':\s*([^\.\n\r]+)/iu';

        if (!preg_match($padrao, $observacoes, $matches)) {
            return null;
        }

        $codigo = trim((string) ($matches[1] ?? ''));
        return $codigo !== '' ? $codigo : null;
    }
}
