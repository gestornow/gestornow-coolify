<?php

namespace App\Domain\Storage\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PrivateStorageService
{
    /**
     * Disco R2 privado configurado em config/filesystems.php.
     */
    private const DISCO = 'r2-private';

    /**
     * Prefixo raiz para todos os arquivos privados.
     */
    private const PREFIXO = 'privado';

    /**
     * Grava um arquivo no R2 privado e retorna apenas o path/key.
     *
     * O path segue a convenção: privado/empresa-{id}/{contexto}/{arquivo}
     *
     * @throws RuntimeException quando o upload falha.
     */
    public function store(UploadedFile $arquivo, int $idEmpresa, string $contexto): string
    {
        $this->validarContexto($contexto);

        $extensao = $arquivo->getClientOriginalExtension() ?: $arquivo->extension() ?: 'bin';
        $nomeArquivo = Str::uuid() . '.' . $extensao;
        $diretorio = $this->construirDiretorio($idEmpresa, $contexto);

        $caminho = Storage::disk(self::DISCO)->putFileAs(
            $diretorio,
            $arquivo,
            $nomeArquivo,
            [
                'ContentType' => (string) ($arquivo->getMimeType() ?: 'application/octet-stream'),
                'visibility'  => 'private',
            ]
        );

        if ($caminho === false) {
            throw new RuntimeException('Falha ao gravar arquivo privado no R2.');
        }

        Log::info('Arquivo privado gravado no R2.', [
            'empresa_id' => $idEmpresa,
            'contexto'   => $contexto,
            'path'       => $caminho,
        ]);

        return $caminho;
    }

    /**
     * Grava conteúdo bruto (string ou resource) no R2 privado e retorna o path/key.
     *
     * @param  string|resource  $conteudo
     * @throws RuntimeException quando o upload falha.
     */
    public function storeDireto(mixed $conteudo, int $idEmpresa, string $contexto, string $nomeArquivo): string
    {
        $this->validarContexto($contexto);

        $diretorio = $this->construirDiretorio($idEmpresa, $contexto);
        $caminho = $diretorio . '/' . $nomeArquivo;

        $sucesso = Storage::disk(self::DISCO)->put(
            $caminho,
            $conteudo,
            [
                'visibility' => 'private',
            ]
        );

        if (!$sucesso) {
            throw new RuntimeException('Falha ao gravar conteúdo privado no R2.');
        }

        Log::info('Conteúdo privado gravado no R2.', [
            'empresa_id' => $idEmpresa,
            'contexto'   => $contexto,
            'path'       => $caminho,
        ]);

        return $caminho;
    }

    /**
     * Remove um arquivo privado do R2.
     */
    public function delete(string $path): void
    {
        Storage::disk(self::DISCO)->delete($path);

        Log::info('Arquivo privado removido do R2.', ['path' => $path]);
    }

    /**
     * Monta o diretório conforme a convenção de caminhos.
     */
    public function construirDiretorio(int $idEmpresa, string $contexto): string
    {
        return self::PREFIXO . '/empresa-' . $idEmpresa . '/' . $contexto;
    }

    /**
     * Valida que o contexto não contém caracteres inválidos para uso em path.
     *
     * @throws \InvalidArgumentException
     */
    private function validarContexto(string $contexto): void
    {
        if (trim($contexto) === '') {
            throw new \InvalidArgumentException('Contexto de arquivo não pode ser vazio.');
        }

        if (str_contains($contexto, '..')) {
            throw new \InvalidArgumentException(
                'Contexto inválido para path de arquivo: "' . $contexto . '". Sequência ".." não é permitida.'
            );
        }

        if (!preg_match('/^[a-z0-9\-_\/]+$/i', $contexto)) {
            throw new \InvalidArgumentException(
                'Contexto inválido para path de arquivo: "' . $contexto . '". Use apenas letras, números, hífens, underscores e barras.'
            );
        }
    }
}
