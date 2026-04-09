<?php

namespace App\Http\Controllers\Configuracao;

use App\ActivityLog\ActionLogger;
use App\Domain\Auth\Models\Empresa;
use App\Domain\Locacao\Models\Locacao;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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

    public function update(Request $request)
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
            $arquivoLogo = $request->file('logo');
            $urlLogo = null;

            $apiBase = rtrim((string) config('services.gestornow_api.base_url', ''), '/');

            if (!empty($apiBase)) {
                try {
                    $response = Http::timeout(30)
                        ->asMultipart()
                        ->attach('file', file_get_contents($arquivoLogo->getRealPath()), $arquivoLogo->getClientOriginalName())
                        ->post($apiBase . '/api/logos', [
                            'idEmpresa' => $idEmpresa,
                            'idLogo' => $idEmpresa,
                            'nomeImagemLogo' => 'Logo Empresa ' . $idEmpresa,
                        ]);

                    if ($response->successful()) {
                        $payload = $response->json();
                        $urlRetornada = $payload['data']['file']['url']
                            ?? $payload['data']['url']
                            ?? $payload['url']
                            ?? null;

                        if ($urlRetornada) {
                            // Se a URL já é completa (começa com http), usa direto
                            if (str_starts_with($urlRetornada, 'http://') || str_starts_with($urlRetornada, 'https://')) {
                                $urlLogo = $urlRetornada;
                            } else {
                                // Senão, concatena com a base da API
                                $urlLogo = $apiBase . '/' . ltrim($urlRetornada, '/');
                            }
                            
                            // Log de sucesso
                            Log::info('Logo enviada com sucesso para API', [
                                'empresa_id' => $idEmpresa,
                                'url' => $urlLogo,
                                'filename' => $payload['data']['file']['filename'] ?? null,
                            ]);
                        }
                    } else {
                        // Log do erro para debug
                        Log::warning('Falha ao enviar logo para API', [
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    // Log da exceção para debug
                    Log::error('Erro ao enviar logo para API', [
                        'empresa_id' => $idEmpresa,
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }

            // Fallback: salvar localmente se a API falhar ou não estiver configurada
            if (!$urlLogo) {
                $nomeArquivo = 'logo_empresa_' . $idEmpresa . '_' . time() . '.' . $arquivoLogo->getClientOriginalExtension();
                $diretorioPublico = public_path('assets/logos-empresa');
                if (!File::exists($diretorioPublico)) {
                    File::makeDirectory($diretorioPublico, 0755, true);
                }

                $arquivoLogo->move($diretorioPublico, $nomeArquivo);
                $urlLogo = asset('assets/logos-empresa/' . $nomeArquivo);
                
                // Log do uso do fallback local
                Log::info('Logo salva localmente (fallback)', [
                    'empresa_id' => $idEmpresa,
                    'filename' => $nomeArquivo,
                    'reason' => empty($apiBase) ? 'API não configurada' : 'Falha no upload para API',
                ]);
            }

            $configuracoes['logo_url'] = $urlLogo;
            $configuracoes['logo_updated_at'] = now()->toDateTimeString();
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
