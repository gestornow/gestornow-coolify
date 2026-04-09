<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CodigoRedefinicaoSenha extends Mailable
{
    use Queueable, SerializesModels;

    public $nome;
    public $codigo;
    public $resetLink;

    /**
     * Create a new message instance.
     */
    public function __construct(string $nome, string $codigo, string $resetLink = null)
    {
        $this->nome = $nome;
        $this->codigo = $codigo;
        $this->resetLink = $resetLink;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Código de Redefinição de Senha - GestorNow')
                    ->view('emails.codigo-redefinicao-senha')
                    ->with([
                        'nome' => $this->nome,
                        'codigo' => $this->codigo,
                        'resetLink' => $this->resetLink
                    ]);
    }
}