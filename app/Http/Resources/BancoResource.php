<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BancoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_bancos' => $this->id_bancos,
            'nome_banco' => $this->nome_banco,
            'agencia' => $this->agencia,
            'conta' => $this->conta,
            'saldo_inicial' => $this->saldo_inicial,
            'observacoes' => $this->observacoes,
            'id_empresa' => $this->id_empresa,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
