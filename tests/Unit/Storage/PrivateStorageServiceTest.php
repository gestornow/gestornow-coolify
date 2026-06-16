<?php

namespace Tests\Unit\Storage;

use App\Domain\Storage\Services\PrivateStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\TestCase;

class PrivateStorageServiceTest extends TestCase
{
    private PrivateStorageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PrivateStorageService();
    }

    public function test_constroi_diretorio_com_convencao_correta(): void
    {
        $diretorio = $this->service->construirDiretorio(42, 'documentos');

        $this->assertSame('privado/empresa-42/documentos', $diretorio);
    }

    public function test_constroi_diretorio_com_contexto_aninhado(): void
    {
        $diretorio = $this->service->construirDiretorio(1, 'contratos/assinados');

        $this->assertSame('privado/empresa-1/contratos/assinados', $diretorio);
    }

    public function test_valida_contexto_invalido_com_caracteres_especiais(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Contexto inválido/');

        // Força o método privado via reflexão
        $reflexao = new \ReflectionClass($this->service);
        $metodo = $reflexao->getMethod('validarContexto');
        $metodo->setAccessible(true);
        $metodo->invoke($this->service, 'contexto com espaço');
    }

    public function test_valida_contexto_invalido_com_path_traversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/\.\./');

        $reflexao = new \ReflectionClass($this->service);
        $metodo = $reflexao->getMethod('validarContexto');
        $metodo->setAccessible(true);
        $metodo->invoke($this->service, 'docs/../segredos');
    }

    public function test_valida_contexto_com_caracteres_validos(): void
    {
        $reflexao = new \ReflectionClass($this->service);
        $metodo = $reflexao->getMethod('validarContexto');
        $metodo->setAccessible(true);

        // Não deve lançar exceção
        $metodo->invoke($this->service, 'contratos-assinados_2024');

        $this->addToAssertionCount(1);
    }

    public function test_valida_contexto_vazio_invalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $reflexao = new \ReflectionClass($this->service);
        $metodo = $reflexao->getMethod('validarContexto');
        $metodo->setAccessible(true);
        $metodo->invoke($this->service, '');
    }
}
