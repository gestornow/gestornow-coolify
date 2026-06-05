<?php

namespace Tests\Unit\Storage;

use App\Domain\Storage\Services\PrivateFileService;
use PHPUnit\Framework\TestCase;

class PrivateFileServiceTest extends TestCase
{
    private PrivateFileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PrivateFileService();
    }

    public function test_valida_escopo_correto_sem_excecao(): void
    {
        $reflexao = new \ReflectionClass($this->service);
        $metodo = $reflexao->getMethod('validarEscopo');
        $metodo->setAccessible(true);

        // Não deve lançar exceção
        $metodo->invoke($this->service, 'privado/empresa-42/documentos/contrato.pdf', 42);

        $this->addToAssertionCount(1);
    }

    public function test_valida_escopo_empresa_errada_lanca_excecao(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Acesso negado/');

        $reflexao = new \ReflectionClass($this->service);
        $metodo = $reflexao->getMethod('validarEscopo');
        $metodo->setAccessible(true);

        // Arquivo pertence à empresa 99, mas tentativa com empresa 42
        $metodo->invoke($this->service, 'privado/empresa-99/documentos/contrato.pdf', 42);
    }

    public function test_valida_escopo_path_sem_prefixo_privado_lanca_excecao(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Acesso negado/');

        $reflexao = new \ReflectionClass($this->service);
        $metodo = $reflexao->getMethod('validarEscopo');
        $metodo->setAccessible(true);

        // Path sem o prefixo 'privado/'
        $metodo->invoke($this->service, 'empresa-42/documentos/contrato.pdf', 42);
    }

    public function test_valida_escopo_tentativa_traversal_lanca_excecao(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Acesso negado/');

        $reflexao = new \ReflectionClass($this->service);
        $metodo = $reflexao->getMethod('validarEscopo');
        $metodo->setAccessible(true);

        // Tentativa de path traversal
        $metodo->invoke($this->service, 'privado/empresa-42/../empresa-99/doc.pdf', 42);
    }

    public function test_valida_escopo_empresa_numerica_com_prefixo_similar_lanca_excecao(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Acesso negado/');

        $reflexao = new \ReflectionClass($this->service);
        $metodo = $reflexao->getMethod('validarEscopo');
        $metodo->setAccessible(true);

        // 'empresa-421' não deve ser confundido com 'empresa-42'
        $metodo->invoke($this->service, 'privado/empresa-421/documentos/contrato.pdf', 42);
    }
}
