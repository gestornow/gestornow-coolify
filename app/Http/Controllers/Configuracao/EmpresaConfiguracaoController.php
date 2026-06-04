<?php

namespace App\Http\Controllers\Configuracao;

use App\ActivityLog\ActionLogger;
use App\Domain\Auth\Models\Empresa;
use App\Domain\Auth\Services\EmpresaLogoStorageService;
use App\Domain\Locacao\Models\Locacao;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class EmpresaConfiguracaoController extends Controller
{
    public function edit()
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $empresa = Empresa::where('id_empresa', $idEmpresa)->firstOrFail();
        $configuracoes = is_array($empresa->configuracoes) ? $empresa->configuracoes : [];
        $logoUrl = $this->normalizarLogoUrl($configuracoes['logo_url'] ?? null);
        $permitirNumeroManualLocacao = (int) ($empresa->locacao_numero_manual ?? 0) === 1;

        $temOrcamento = Locacao::where('id_empresa', $idEmpresa)
            ->where('status', 'orcamento')
            ->exists();

        $temContrato = Locacao::where('id_empresa', $idEmpresa)
            ->where('status', '!=', 'orcamento')
            ->exists();

        $podeAlterarPreferenciaNumeracao = !($temOrcamento && $temContrato);

        return view('configuracoes.empresa', compact('empresa', 'logoUrl', 'podeAlterarPreferenciaNumeracao', 'permitirNumeroManualLocacao'));
    }

    public function update(Request $request, EmpresaLogoStorageService $empresaLogoStorageService)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $empresa = Empresa::where('id_empresa', $idEmpresa)->firstOrFail();

        $dados = $request->validate([
            'nome_empresa' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'bairro' => ['nullable', 'string', 'max:120'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'complemento' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', 'max:2'],
            'cep' => ['nullable', 'string', 'max:10'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'orcamentos_contratos' => ['nullable', 'boolean'],
            'locacao_numero_manual' => ['nullable', 'boolean'],
        ]);

        $temOrcamento = Locacao::where('id_empresa', $idEmpresa)
            ->where('status', 'orcamento')
            ->exists();

        $temContrato = Locacao::where('id_empresa', $idEmpresa)
            ->where('status', '!=', 'orcamento')
            ->exists();

        $podeAlterarPreferenciaNumeracao = !($temOrcamento && $temContrato);

        $empresa->fill([
            'nome_empresa' => $dados['nome_empresa'] ?? $empresa->nome_empresa,
            'email' => $dados['email'] ?? null,
            'telefone' => $dados['telefone'] ?? null,
            'endereco' => $dados['endereco'] ?? null,
            'numero' => $dados['numero'] ?? null,
            'bairro' => $dados['bairro'] ?? null,
            'cidade' => $dados['cidade'] ?? null,
            'complemento' => $dados['complemento'] ?? null,
            'uf' => $dados['uf'] ?? null,
            'cep' => $dados['cep'] ?? null,
        ]);

        $configuracoes = is_array($empresa->configuracoes) ? $empresa->configuracoes : [];

        if ($request->hasFile('logo')) {
            try {
                $configuracoes['logo_url'] = $empresaLogoStorageService->store($request->file('logo'), (int) $idEmpresa);
                $configuracoes['logo_updated_at'] = now()->toDateTimeString();
            } catch (\Throwable $e) {
                Log::error('Erro ao enviar logo para o disco S3.', [
                    'empresa_id' => $idEmpresa,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors([
                        'logo' => 'Não foi possível enviar a logo para o armazenamento configurado.',
                    ]);
            }
        }

        $empresa->configuracoes = $configuracoes;
        $empresa->locacao_numero_manual = (int) ($request->boolean('locacao_numero_manual') ? 1 : 0);

        if ($podeAlterarPreferenciaNumeracao) {
            $empresa->orcamentos_contratos = (int) ($request->boolean('orcamentos_contratos') ? 1 : 0);
        }

        $empresa->save();
        ActionLogger::log($empresa->fresh(), 'configuracoes_atualizadas');

        return redirect()->route('configuracoes.empresa.edit')->with('success', 'Configurações atualizadas com sucesso.');
    }

    private function normalizarLogoUrl(?string $logoUrl): ?string
    {
        if (empty($logoUrl)) {
            return null;
        }

        $logoMigrada = $this->migrarLogoLegadaParaPublico($logoUrl);
        if ($logoMigrada) {
            return $logoMigrada;
        }

        if (str_starts_with($logoUrl, 'http://') || str_starts_with($logoUrl, 'https://')) {
            return $logoUrl;
        }

        return asset(ltrim($logoUrl, '/'));
    }

    private function migrarLogoLegadaParaPublico(string $logoUrl): ?string
    {
        $isUrlExterna = str_starts_with($logoUrl, 'http://') || str_starts_with($logoUrl, 'https://');
        $logoPath = $isUrlExterna ? parse_url($logoUrl, PHP_URL_PATH) : $logoUrl;
        $nomeArquivo = basename((string) $logoPath);

        if (empty($nomeArquivo) || $nomeArquivo === '.' || $nomeArquivo === '..') {
            return null;
        }

        $diretorioPublico = public_path('assets/logos-empresa');
        $logoPublica = $diretorioPublico . DIRECTORY_SEPARATOR . $nomeArquivo;

        if (File::exists($logoPublica)) {
            return asset('assets/logos-empresa/' . $nomeArquivo);
        }

        $origens = array_filter([
            $logoPath ? public_path(ltrim($logoPath, '/')) : null,
            storage_path('app/public/logos-empresa/' . $nomeArquivo),
        ]);

        foreach ($origens as $origem) {
            if (!File::exists($origem) || !File::isFile($origem)) {
                continue;
            }

            if (!File::exists($diretorioPublico)) {
                File::makeDirectory($diretorioPublico, 0755, true);
            }

            File::copy($origem, $logoPublica);
            return asset('assets/logos-empresa/' . $nomeArquivo);
        }

        return null;
    }
}
