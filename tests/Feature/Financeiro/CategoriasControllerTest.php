<?php

namespace Tests\Feature\Financeiro;

use App\Models\CategoriaContas;
use App\Models\Banco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CategoriasControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    /**
     * Test creating a categoria with valid data
     */
    public function test_pode_criar_categoria_com_dados_validos()
    {
        $data = [
            'nome' => 'Aluguel',
            'tipo' => 'pagar',
            'descricao' => 'Despesa de aluguel mensal',
        ];

        $response = $this->postJson('/financeiro/categorias', $data);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nome', 'Aluguel')
            ->assertJsonPath('data.tipo', 'pagar');

        $this->assertDatabaseHas('categoria_contas', [
            'nome' => 'Aluguel',
        ]);
    }

    /**
     * Test creating categoria without required nome
     */
    public function test_nao_pode_criar_categoria_sem_nome()
    {
        $data = [
            'nome' => '',
            'tipo' => 'pagar',
        ];

        $response = $this->postJson('/financeiro/categorias', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('nome');
    }

    /**
     * Test creating categoria with duplicate nome
     */
    public function test_nao_pode_criar_categoria_com_nome_duplicado()
    {
        $empresa = session('id_empresa');
        
        CategoriaContas::factory()->create([
            'id_empresa' => $empresa,
            'nome' => 'Aluguel',
        ]);

        $data = [
            'nome' => 'Aluguel',
            'tipo' => 'pagar',
        ];

        $response = $this->postJson('/financeiro/categorias', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('nome');
    }

    /**
     * Test listing categorias
     */
    public function test_pode_listar_categorias()
    {
        $empresa = session('id_empresa');
        
        CategoriaContas::factory()->count(3)->create([
            'id_empresa' => $empresa,
        ]);

        $response = $this->getJson('/financeiro/categorias');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    /**
     * Test get categorias by tipo
     */
    public function test_pode_filtrar_categorias_por_tipo()
    {
        $empresa = session('id_empresa');
        
        CategoriaContas::factory()->count(2)->create([
            'id_empresa' => $empresa,
            'tipo' => 'pagar',
        ]);

        CategoriaContas::factory()->count(1)->create([
            'id_empresa' => $empresa,
            'tipo' => 'receber',
        ]);

        $response = $this->getJson('/financeiro/categorias/pagar');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }
}
