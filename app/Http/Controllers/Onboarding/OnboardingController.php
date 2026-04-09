<?php

namespace App\Http\Controllers\Onboarding;

use App\ActivityLog\ActionLogger;
use App\Domain\Auth\Models\Empresa;
use App\Http\Controllers\Controller;
use App\Models\AssinaturaPlano;
use App\Models\ClientContract;
use App\Models\Plano;
use App\Models\PlanoContratado;
use App\Services\Billing\AssinaturaPlanoService;
use App\Services\Billing\ContractPdfService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    private const VERSAO_CONTRATO_ATUAL = '1.0';
    private const ID_EMPRESA_GESTOR_NOW = 1;

    public function __construct(
        private readonly AssinaturaPlanoService $assinaturaPlanoService,
        private readonly ContractPdfService $pdfService
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->estruturaOnboardingDisponivel()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Estrutura de onboarding não instalada. Execute o SQL de assinaturas primeiro.');
        }

        $empresa = $this->empresaLogada();
        $assinatura = $this->assinaturaPlanoService->obterAssinaturaEmpresa((int) $empresa->id_empresa);

        if (!$assinatura) {
            return redirect()
                ->route('dashboard')
                ->with('warning', 'Nenhuma assinatura encontrada para iniciar o onboarding.');
        }

        $empresaGestorNow = $this->empresaGestorNow();
        if (!$empresaGestorNow) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Empresa Gestor Now (id_empresa=1) nao encontrada para emissao do contrato.');
        }

        $camposPendentes = $this->camposCadastraisPendentes($empresa);
        $dadosCompletos = count($camposPendentes) === 0;

        [$planoContratado, $plano] = $this->resolverPlanoDaAssinatura($assinatura);
        $limites = $this->montarLimitesPlano($planoContratado, $plano);
        $valorAdesao = (float) ($planoContratado?->adesao ?? $plano?->adesao ?? 0);
        $valorMensalidade = (float) ($planoContratado?->valor ?? $plano?->valor ?? 0);

        $versao = $this->versaoContrato();
        $titulo = $this->tituloContrato();
        $corpoContrato = $this->montarContratoPadrao(
            $empresa,
            $empresaGestorNow,
            $planoContratado,
            $plano,
            $limites,
            $valorAdesao,
            $valorMensalidade,
            now()->format('d/m/Y H:i:s'),
            $this->capturarIpReal($request),
            (string) ($request->userAgent() ?: 'Nao informado')
        );

        $contratoAssinado = ClientContract::query()
            ->where('id_empresa', $empresa->id_empresa)
            ->where('status', ClientContract::STATUS_ATIVO)
            ->exists();

        return view('onboarding.index', [
            'empresa' => $empresa,
            'assinatura' => $assinatura,
            'dadosCompletos' => $dadosCompletos,
            'camposPendentes' => $camposPendentes,
            'podeAssinarContrato' => $dadosCompletos,
            'contratoAssinado' => $contratoAssinado,
            'tituloContrato' => $titulo,
            'versaoContrato' => $versao,
            'corpoContrato' => $corpoContrato,
        ]);
    }

    public function salvarDados(Request $request): RedirectResponse
    {
        if (!$this->estruturaOnboardingDisponivel()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Estrutura de onboarding não instalada. Execute o SQL de assinaturas primeiro.');
        }

        $dados = $request->validate([
            'razao_social' => 'required|string|max:255',
            'nome_empresa' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'cpf' => 'nullable|string|max:14',
            'email' => 'required|email|max:255',
            'telefone' => 'required|string|max:20',
            'endereco' => 'required|string|max:255',
            'numero' => 'required|string|max:20',
            'bairro' => 'required|string|max:120',
            'cidade' => 'required|string|max:120',
            'uf' => 'required|string|size:2',
            'cep' => 'required|string|max:10',
            'complemento' => 'nullable|string|max:120',
        ]);

        $empresa = $this->empresaLogada();

        $cnpjLimpo = preg_replace('/\D/', '', (string) ($dados['cnpj'] ?? ''));
        $cpfLimpo = preg_replace('/\D/', '', (string) ($dados['cpf'] ?? ''));

        if ($cnpjLimpo === '' && $cpfLimpo === '') {
            return redirect()
                ->route('onboarding.index')
                ->with('error', 'Informe CPF ou CNPJ para concluir os dados cadastrais.');
        }

        $payloadEmpresa = [
            'razao_social' => $dados['razao_social'] ?? $empresa->razao_social,
            'nome_empresa' => $dados['nome_empresa'],
            'cnpj' => $cnpjLimpo !== '' ? $cnpjLimpo : null,
            'cpf' => $cpfLimpo !== '' ? $cpfLimpo : null,
            'email' => $dados['email'],
            'telefone' => preg_replace('/\D/', '', (string) $dados['telefone']),
            'endereco' => $dados['endereco'],
            'numero' => $dados['numero'],
            'bairro' => $dados['bairro'],
            'cidade' => $dados['cidade'],
            'uf' => strtoupper((string) $dados['uf']),
            'cep' => preg_replace('/\D/', '', (string) $dados['cep']),
            'complemento' => $dados['complemento'] ?? null,
        ];

        $codigoOriginal = $empresa->codigo;
        $filialOriginal = $empresa->filial;
        $idEmpresaMatrizOriginal = $empresa->id_empresa_matriz;

        // Preservar campos de identificacao da filial no mesmo update de onboarding.
        $payloadEmpresa['codigo'] = $codigoOriginal;
        $payloadEmpresa['filial'] = $filialOriginal;
        $payloadEmpresa['id_empresa_matriz'] = $idEmpresaMatrizOriginal;

        $empresaPreview = $empresa->replicate();
        $empresaPreview->forceFill($payloadEmpresa);
        $camposPendentes = $this->camposCadastraisPendentes($empresaPreview);
        $payloadEmpresa['dados_cadastrais'] = empty($camposPendentes) ? 'completo' : 'incompleto';

        $empresa->update($payloadEmpresa);
        $empresaAtualizada = $empresa->fresh();

        $camposSensitivosFilial = [];
        if ((string) $empresaAtualizada->codigo !== (string) $codigoOriginal) {
            $camposSensitivosFilial['codigo'] = $codigoOriginal;
        }
        if ((string) $empresaAtualizada->filial !== (string) $filialOriginal) {
            $camposSensitivosFilial['filial'] = $filialOriginal;
        }
        if ((string) $empresaAtualizada->id_empresa_matriz !== (string) $idEmpresaMatrizOriginal) {
            $camposSensitivosFilial['id_empresa_matriz'] = $idEmpresaMatrizOriginal;
        }

        if (!empty($camposSensitivosFilial)) {
            DB::table('empresa')
                ->where('id_empresa', $empresaAtualizada->id_empresa)
                ->update($camposSensitivosFilial);

            Log::warning('Onboarding detectou alteracao indevida de codigo/filial ao atualizar endereco da empresa e restaurou os valores originais.', [
                'id_empresa' => $empresaAtualizada->id_empresa,
                'alterados' => array_keys($camposSensitivosFilial),
            ]);

            $empresaAtualizada = $empresaAtualizada->fresh();
        }

        $camposPendentes = $this->camposCadastraisPendentes($empresaAtualizada);

        ActionLogger::log($empresaAtualizada, 'onboarding_dados_atualizados');

        if (!empty($camposPendentes)) {
            return redirect()
                ->route('onboarding.index')
                ->with('error', 'Preencha todos os dados cadastrais obrigatorios antes de seguir para o contrato. Campos pendentes: ' . implode(', ', $camposPendentes));
        }

        $assinaturaAtualizada = $this->assinaturaPlanoService->atualizarStatusOnboarding($empresaAtualizada);

        if ($assinaturaAtualizada && $assinaturaAtualizada->status === AssinaturaPlano::STATUS_ATIVA) {
            return redirect()
                ->route('dashboard')
                ->with('success', 'Onboarding concluído com sucesso.');
        }

        return redirect()
            ->route('onboarding.index')
            ->with('success', 'Dados cadastrais atualizados. Agora assine o contrato para liberar o sistema.');
    }

    public function assinarContrato(Request $request): RedirectResponse
    {
        if (!$this->estruturaOnboardingDisponivel()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Estrutura de onboarding não instalada. Execute o SQL de assinaturas primeiro.');
        }

        $empresa = $this->empresaLogada();
        $camposPendentes = $this->camposCadastraisPendentes($empresa);
        if (!empty($camposPendentes)) {
            return redirect()
                ->route('onboarding.index')
                ->with('error', 'Preencha todos os dados cadastrais obrigatorios (exceto complemento) antes de assinar o contrato. Campos pendentes: ' . implode(', ', $camposPendentes));
        }

        $dados = $request->validate([
            'assinado_por_nome' => 'required|string|max:160',
            'assinado_por_documento' => 'required|string|max:30',
            'assinatura_texto' => 'nullable|string|max:255',
            'assinatura_desenhada_base64' => 'nullable|string',
            'aceite_contrato' => 'required|accepted',
        ]);

        $assinaturaTexto = trim((string) ($dados['assinatura_texto'] ?? ''));
        $assinaturaDesenhada = trim((string) ($dados['assinatura_desenhada_base64'] ?? ''));

        if ($assinaturaDesenhada === '' && $assinaturaTexto === '') {
            return redirect()
                ->route('onboarding.index')
                ->with('error', 'Informe uma assinatura desenhada ou escrita para concluir o contrato.')
                ->withInput();
        }

        if ($assinaturaDesenhada !== '' && !str_starts_with($assinaturaDesenhada, 'data:image/')) {
            return redirect()
                ->route('onboarding.index')
                ->with('error', 'Formato de assinatura desenhada invalido. Desenhe novamente e tente salvar.')
                ->withInput();
        }

        $assinaturaPersistida = $assinaturaDesenhada !== ''
            ? $assinaturaDesenhada
            : ('texto:' . $assinaturaTexto);

        $empresaGestorNow = $this->empresaGestorNow();
        if (!$empresaGestorNow) {
            return redirect()
                ->route('onboarding.index')
                ->with('error', 'Empresa Gestor Now (id_empresa=1) nao encontrada para emissao do contrato.');
        }

        $assinatura = $this->assinaturaPlanoService->obterAssinaturaEmpresa((int) $empresa->id_empresa);

        if (!$assinatura) {
            return redirect()
                ->route('dashboard')
                ->with('warning', 'Nenhuma assinatura encontrada para vincular o contrato.');
        }

        [$planoContratado, $plano] = $this->resolverPlanoDaAssinatura($assinatura);
        $limites = $this->montarLimitesPlano($planoContratado, $plano);
        $valorAdesao = (float) ($planoContratado?->adesao ?? $plano?->adesao ?? 0);
        $valorMensalidade = (float) ($planoContratado?->valor ?? $plano?->valor ?? 0);

        $aceitoEm = now();
        $ipAceite = $this->capturarIpReal($request);
        $userAgent = (string) ($request->userAgent() ?: 'Nao informado');

        $versao = $this->versaoContrato();
        $titulo = $this->tituloContrato();
        $corpoContrato = $this->montarContratoPadrao(
            $empresa,
            $empresaGestorNow,
            $planoContratado,
            $plano,
            $limites,
            $valorAdesao,
            $valorMensalidade,
            $aceitoEm->format('d/m/Y H:i:s'),
            $ipAceite,
            $userAgent
        );

        $documentoCliente = (string) preg_replace('/\D/', '', (string) ($empresa->cnpj ?: ($empresa->cpf ?: $dados['assinado_por_documento'])));
        $hashDocumento = ClientContract::calcularHash([
            'id_empresa' => (int) $empresa->id_empresa,
            'cliente_razao_social' => $empresa->razao_social ?: $empresa->nome_empresa,
            'cliente_cnpj_cpf' => $documentoCliente,
            'valor_adesao' => $valorAdesao,
            'valor_mensalidade' => $valorMensalidade,
            'limites_contratados' => $limites,
            'versao_contrato' => $versao,
            'corpo_contrato' => $corpoContrato,
            'aceito_em' => $aceitoEm->toIso8601String(),
        ]);

        $enderecoCompleto = $this->montarEnderecoCompleto($empresa);

        try {
            $contrato = DB::transaction(function () use (
                $assinatura,
                $empresa,
                $dados,
                $assinaturaPersistida,
                $plano,
                $planoContratado,
                $limites,
                $valorAdesao,
                $valorMensalidade,
                $versao,
                $titulo,
                $corpoContrato,
                $hashDocumento,
                $documentoCliente,
                $aceitoEm,
                $enderecoCompleto,
                $ipAceite,
                $userAgent
            ) {
                ClientContract::query()
                    ->where('id_empresa', $empresa->id_empresa)
                    ->where('status', ClientContract::STATUS_ATIVO)
                    ->update([
                        'status' => ClientContract::STATUS_SUBSTITUIDO,
                        'motivo_revogacao' => 'Substituído por novo aceite no onboarding em ' . now()->format('d/m/Y H:i:s'),
                        'revogado_em' => now(),
                    ]);

                return ClientContract::create([
                    'id_empresa' => $empresa->id_empresa,
                    'id_plano' => $plano?->id_plano ?? $assinatura->id_plano,
                    'id_plano_contratado' => $planoContratado?->id ?? $assinatura->id_plano_contratado,

                    'cliente_razao_social' => $empresa->razao_social ?: $empresa->nome_empresa,
                    'cliente_cnpj_cpf' => $documentoCliente,
                    'cliente_email' => $empresa->email,
                    'cliente_endereco' => $enderecoCompleto,

                    'valor_adesao' => $valorAdesao,
                    'valor_mensalidade' => $valorMensalidade,
                    'limites_contratados' => $limites,

                    'versao_contrato' => $versao,
                    'titulo_contrato' => $titulo,
                    'corpo_contrato' => $corpoContrato,
                    'hash_documento' => $hashDocumento,

                    'assinatura_base64' => $assinaturaPersistida,
                    'assinado_por_nome' => (string) $dados['assinado_por_nome'],
                    'assinado_por_documento' => preg_replace('/\D/', '', (string) $dados['assinado_por_documento']),
                    'assinado_por_email' => $empresa->email,

                    'ip_aceite' => $ipAceite,
                    'user_agent' => $userAgent,
                    'aceito_em' => $aceitoEm,

                    'status' => ClientContract::STATUS_ATIVO,
                ]);
            });

            try {
                $reciboPath = $this->pdfService->gerarReciboAdesao($contrato);
                $contrato->registrarReciboGerado($reciboPath);
            } catch (Exception $pdfException) {
                Log::warning('Contrato salvo no onboarding, mas o recibo não foi gerado.', [
                    'id_empresa' => $empresa->id_empresa,
                    'contrato_id' => $contrato->id,
                    'erro' => $pdfException->getMessage(),
                ]);
            }

            $assinaturaAtualizada = $this->assinaturaPlanoService->atualizarStatusOnboarding($empresa->fresh());

            $mensagemSucesso = 'Contrato assinado com sucesso.';
            if ($assinaturaAtualizada && $assinaturaAtualizada->status === AssinaturaPlano::STATUS_ATIVA) {
                $mensagemSucesso = 'Contrato assinado com sucesso. Sistema liberado para uso.';
            }

            return redirect()
                ->route('onboarding.index')
                ->with('success', $mensagemSucesso)
                ->with('recibo_url', route('onboarding.contrato.pdf'));
        } catch (Exception $e) {
            return redirect()
                ->route('onboarding.index')
                ->with('error', 'Não foi possível registrar a assinatura do contrato: ' . $e->getMessage());
        }
    }

    public function contratoPdf(Request $request)
    {
        if (!$this->estruturaOnboardingDisponivel()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Estrutura de onboarding nao instalada. Execute o SQL de assinaturas primeiro.');
        }

        $empresa = $this->empresaLogada();

        $contrato = ClientContract::query()
            ->where('id_empresa', $empresa->id_empresa)
            ->where('status', ClientContract::STATUS_ATIVO)
            ->orderByDesc('aceito_em')
            ->first();

        if (!$contrato) {
            return redirect()
                ->route('onboarding.index')
                ->with('warning', 'Assine o contrato antes de gerar o PDF.');
        }

        try {
            // Regenera sempre para refletir layout/logo mais recentes.
            $reciboPath = $this->pdfService->gerarReciboAdesao($contrato);
            $contrato->registrarReciboGerado($reciboPath);
            $contrato->refresh();
        } catch (\Throwable $e) {
            return redirect()
                ->route('onboarding.index')
                ->with('error', 'Não foi possível gerar o PDF do recibo: ' . $e->getMessage());
        }

        $arquivo = storage_path('app/' . (string) $contrato->recibo_path);
        if (!is_file($arquivo)) {
            return redirect()
                ->route('onboarding.index')
                ->with('error', 'Arquivo do recibo não encontrado no armazenamento.');
        }

        return response()->download($arquivo, 'recibo-adesao-' . (int) $contrato->id . '.pdf');
    }

    private function empresaLogada(): Empresa
    {
        return Empresa::findOrFail((int) (session('id_empresa') ?: Auth::user()->id_empresa));
    }

    private function versaoContrato(): string
    {
        return self::VERSAO_CONTRATO_ATUAL;
    }

    private function tituloContrato(): string
    {
        return 'Termos de Uso e Licenciamento de Software - Gestor Now';
    }

    private function montarContratoPadrao(
        Empresa $empresa,
        Empresa $empresaGestorNow,
        ?PlanoContratado $planoContratado,
        ?Plano $plano,
        array $limites,
        float $valorAdesao,
        float $valorMensalidade,
        string $dataHoraAceite,
        string $ipAceite,
        string $userAgent
    ): string
    {
        $nomePlano = $planoContratado?->nome ?? $plano?->nome ?? 'Plano Contratado';
        $razaoSocial = $empresa->razao_social ?: $empresa->nome_empresa;
        $documento = $this->formatarDocumentoEmpresa($empresa);
        $endereco = $this->montarEnderecoCompleto($empresa);
        $limitesTexto = $this->resumoLimitesPlanoDashboard($nomePlano) ?? $this->formatarLimitesParaContrato($limites);

        $razaoGestorNow = $empresaGestorNow->razao_social ?: $empresaGestorNow->nome_empresa;
        $documentoGestorNow = $this->formatarDocumentoEmpresa($empresaGestorNow);
        $enderecoGestorNow = $this->montarEnderecoCompleto($empresaGestorNow);

        return implode("\n", [
            'TERMOS DE USO E LICENCIAMENTO DE SOFTWARE - GESTOR NOW',
            'Pelo presente instrumento, a empresa CONTRATANTE adere ao plano de licenciamento de uso do software Gestor Now, declarando ciencia e concordancia integral com as clausulas abaixo:',
            '',
            'CLAUSULA PRIMEIRA - DO OBJETO E LIMITES',
            'A CONTRATADA (Gestor Now) outorga a CONTRATANTE uma licenca de uso, em carater nao exclusivo e intransferivel, do sistema de gestao, restrito aos limites de cadastros (clientes, produtos, locacoes e usuarios) estipulados no plano contratado no momento deste aceite.',
            '',
            'CLAUSULA SEGUNDA - DOS VALORES E REAJUSTE',
            'A CONTRATANTE declara ciencia de que a taxa de adesao (setup) e devida no ato da contratacao, e as mensalidades possuem vencimento recorrente.',
            'Paragrafo Unico: O valor da mensalidade sera reajustado a cada 12 (doze) meses, contados da data de aceite deste termo, com base no indice de inflacao acumulado do periodo (IGP-M/FGTS ou IPCA/IBGE). Em caso de deflacao (indice negativo), as partes concordam em manter o valor vigente, nao aplicando reducao a mensalidade.',
            '',
            'CLAUSULA TERCEIRA - DA INADIMPLENCIA E SUSPENSAO',
            'Em caso de atraso no pagamento da mensalidade superior a 5 (cinco) dias, o acesso ao sistema sera suspenso automaticamente.',
            'Paragrafo Unico: Apos a suspensao, a liberacao do acesso ocorrera exclusivamente apos a compensacao e baixa automatica do pagamento pelos provedores financeiros (gateways) integrados a Gestor Now. Nao serao aceitos comprovantes de agendamento para liberacao manual.',
            '',
            'CLAUSULA QUARTA - DA MIGRACAO DE DADOS',
            'A eventual migracao de dados (importacao de planilhas e cadastros) de sistemas anteriores da CONTRATANTE para a Gestor Now sera realizada no prazo de ate 10 (dez) dias uteis apos o envio das informacoes formatadas.',
            'Paragrafo Unico: O prazo de migracao nao impede o faturamento e a utilizacao imediata do sistema pela CONTRATANTE para novas operacoes.',
            '',
            'CLAUSULA QUINTA - DE DESENVOLVIMENTOS CUSTOMIZADOS',
            'O licenciamento nao inclui o desenvolvimento de funcoes exclusivas. A execucao de qualquer nova funcionalidade solicitada pela CONTRATANTE sera analisada pela equipe tecnica da Gestor Now e, se viavel, sera objeto de orcamento e cobranca adicional, mediante aprovacao previa.',
            '',
            'CLAUSULA SEXTA - DA PROTECAO DE DADOS (LGPD)',
            'Em estrito cumprimento a Lei Geral de Protecao de Dados Pessoais (Lei n 13.853/2019), a Gestor Now compromete-se a manter em sigilo absoluto e hospedar com seguranca todos os dados da CONTRATANTE e de seus respectivos clientes. A Gestor Now atua apenas como processadora dos dados, sendo a CONTRATANTE a unica responsavel legal pela coleta e autorizacao dos dados de seus clientes finais inseridos na plataforma.',
            '',
            'CLAUSULA SETIMA - DO CANCELAMENTO',
            'O presente contrato nao possui fidelidade (salvo negociacao especifica). A CONTRATANTE podera solicitar o cancelamento a qualquer momento, mediante aviso previo de 30 (trinta) dias.',
            'Paragrafo Unico: O cancelamento nao isenta a CONTRATANTE de faturas ja emitidas ou do ciclo vigente de 30 dias, nao havendo estorno ou devolucao de valores proporcionais.',
            '',
            'A assinatura digital registrada neste aceite, atrelada ao IP e dados de conexao, possui validade juridica, representando concordancia integral com os termos deste contrato.',
            '',
            'DADOS DO CLIENTE',
            'Nome/Razao Social: ' . $razaoSocial,
            'CNPJ/CPF: ' . $documento,
            'E-mail: ' . (string) ($empresa->email ?: '-'),
            'Endereco: ' . ($endereco !== '' ? $endereco : '-'),
            '',
            'DADOS DA GESTOR NOW',
            'Nome/Razao Social: ' . ($razaoGestorNow ?: 'Gestor Now'),
            'CNPJ/CPF: ' . ($documentoGestorNow !== '' ? $documentoGestorNow : '-'),
            'E-mail: ' . (string) ($empresaGestorNow->email ?: '-'),
            'Endereco: ' . ($enderecoGestorNow !== '' ? $enderecoGestorNow : '-'),
            '',
            'DADOS DO PLANO CONTRATADO',
            'Nome do Plano: ' . $nomePlano,
            'Valor Adesao: R$ ' . $this->formatarValor($valorAdesao),
            'Valor Mensalidade: R$ ' . $this->formatarValor($valorMensalidade),
            'Limites: ' . $limitesTexto,
            '',
            'Data e Hora do Aceite: ' . $dataHoraAceite,
            'IP e Dispositivo: ' . $ipAceite . ' - ' . $userAgent,
        ]);
    }

    private function empresaGestorNow(): ?Empresa
    {
        return Empresa::find(self::ID_EMPRESA_GESTOR_NOW);
    }

    private function camposCadastraisPendentes(Empresa $empresa): array
    {
        $camposObrigatorios = [
            'razao_social' => 'Razao Social',
            'nome_empresa' => 'Nome Fantasia',
            'email' => 'E-mail',
            'telefone' => 'Telefone',
            'endereco' => 'Endereco',
            'numero' => 'Numero',
            'bairro' => 'Bairro',
            'cidade' => 'Cidade',
            'uf' => 'UF',
            'cep' => 'CEP',
        ];

        $pendentes = [];
        foreach ($camposObrigatorios as $campo => $label) {
            $valor = trim((string) ($empresa->{$campo} ?? ''));
            if ($valor === '') {
                $pendentes[] = $label;
            }
        }

        $cnpj = preg_replace('/\D/', '', (string) ($empresa->cnpj ?? ''));
        $cpf = preg_replace('/\D/', '', (string) ($empresa->cpf ?? ''));
        if ($cnpj === '' && $cpf === '') {
            $pendentes[] = 'CPF ou CNPJ';
        }

        return $pendentes;
    }

    private function formatarDocumentoEmpresa(Empresa $empresa): string
    {
        $documento = preg_replace('/\D/', '', (string) ($empresa->cnpj ?: $empresa->cpf));
        return $documento ?: '-';
    }

    private function resumoLimitesPlanoDashboard(string $nomePlano): ?string
    {
        $nomeNormalizado = strtolower(trim($nomePlano));
        $nomeNormalizado = preg_replace('/^plano\s+/i', '', $nomeNormalizado);

        $porPlano = [
            'start' => [
                'Clientes - Limite: 500',
                'Produtos - Limite: 500',
                'Locacoes Completas',
                '1 Modelo de contrato',
                'Financeiro Completo',
                'Sem emissao de Boleto',
                'Usuarios - Limite: 1',
            ],
            'pro' => [
                'Clientes - Limite: 1.500',
                'Produtos - Limite: 1.500',
                'Locacoes Completas',
                'Modelos de contratos ilimitados',
                'Financeiro Completo',
                '1 banco pra boleto',
                'Usuarios - Limite: 3',
            ],
            'plus' => [
                'Clientes - Limite: 3.000',
                'Produtos - Limite: 3.000',
                'Locacoes Completas',
                'Modelos de contratos ilimitados',
                'Financeiro Completo',
                'Bancos pra Boletos Ilimitados',
                'Usuarios - Limite: 10',
            ],
            'premium' => [
                'Clientes - Ilimitado',
                'Produtos - Ilimitado',
                'Locacoes Completas',
                'Modelos de contratos ilimitados',
                'Financeiro Completo',
                'Bancos pra Boletos Ilimitados',
                'Usuarios - Ilimitado',
            ],
        ];

        if (!isset($porPlano[$nomeNormalizado])) {
            return null;
        }

        return implode(' | ', $porPlano[$nomeNormalizado]);
    }

    private function estruturaOnboardingDisponivel(): bool
    {
        return Schema::hasTable('assinaturas_planos')
            && Schema::hasTable('client_contracts');
    }

    private function resolverPlanoDaAssinatura(AssinaturaPlano $assinatura): array
    {
        $planoContratado = null;
        if (!empty($assinatura->id_plano_contratado)) {
            $planoContratado = PlanoContratado::find($assinatura->id_plano_contratado);
        }

        $plano = null;
        if (!empty($assinatura->id_plano)) {
            $plano = Plano::find($assinatura->id_plano);
        }

        if (!$plano && $planoContratado) {
            $plano = Plano::query()
                ->where('nome', 'LIKE', '%' . $planoContratado->nome . '%')
                ->first();
        }

        return [$planoContratado, $plano];
    }

    private function montarLimitesPlano(?PlanoContratado $planoContratado, ?Plano $plano): array
    {
        $limites = [];

        if ($planoContratado) {
            $modulos = $planoContratado->modulos()->with('modulo')->get();

            foreach ($modulos as $moduloContratado) {
                $nomeModulo = $moduloContratado->modulo?->nome
                    ?? $moduloContratado->nome_modulo
                    ?? 'modulo_' . $moduloContratado->id_modulo;

                $chave = $this->normalizarChaveModulo((string) $nomeModulo);
                $limites[$chave] = $moduloContratado->limite ?? 0;
            }
        }

        if ($plano) {
            $limites['relatorios'] = (string) $plano->relatorios === 'S';
            $limites['bancos_boleto'] = (string) $plano->bancos === 'S';
            $limites['assinatura_digital'] = (string) $plano->assinatura_digital === 'S';
            $limites['modelos_contrato'] = (string) $plano->contratos === 'S' ? 'Ilimitado' : '1';
            $limites['faturas'] = (string) $plano->faturas === 'S';
        }

        $limitesDefault = [
            'clientes' => 0,
            'produtos' => 0,
            'usuarios' => 1,
            'locacoes' => 'completo',
            'financeiro' => 'completo',
        ];

        foreach ($limitesDefault as $chave => $valorDefault) {
            if (!array_key_exists($chave, $limites)) {
                $limites[$chave] = $valorDefault;
            }
        }

        return $limites;
    }

    private function formatarLimitesParaContrato(array $limites): string
    {
        $labels = [
            'clientes' => 'Limite de Clientes',
            'produtos' => 'Limite de Produtos',
            'usuarios' => 'Limite de Usuarios',
            'modelos_contrato' => 'Modelos de Contrato',
            'bancos_boleto' => 'Bancos para Boleto',
            'locacoes' => 'Modulo de Locacoes',
            'financeiro' => 'Modulo Financeiro',
            'relatorios' => 'Relatorios',
            'assinatura_digital' => 'Assinatura Digital',
            'faturas' => 'Faturas',
        ];

        $itens = [];
        foreach ($limites as $chave => $valor) {
            $label = $labels[$chave] ?? ucfirst(str_replace('_', ' ', (string) $chave));

            if (is_bool($valor)) {
                $valorFormatado = $valor ? 'Disponivel' : 'Nao disponivel';
            } elseif (is_numeric($valor) && (float) $valor === 0.0) {
                $valorFormatado = 'Ilimitado';
            } elseif (is_numeric($valor)) {
                $valorFormatado = number_format((float) $valor, 0, ',', '.');
            } else {
                $valorFormatado = ucfirst((string) $valor);
            }

            $itens[] = $label . ': ' . $valorFormatado;
        }

        return implode(' | ', $itens);
    }

    private function normalizarChaveModulo(string $nome): string
    {
        $nome = mb_strtolower($nome);
        $nome = preg_replace('/[^a-z0-9]+/', '_', $nome);
        return trim((string) $nome, '_');
    }

    private function montarEnderecoCompleto(Empresa $empresa): string
    {
        $partes = array_filter([
            $empresa->endereco,
            $empresa->numero ? 'N ' . $empresa->numero : null,
            $empresa->complemento,
            $empresa->bairro,
            $empresa->cidade,
            $empresa->uf,
            $empresa->cep ? 'CEP: ' . $empresa->cep : null,
        ]);

        return implode(', ', $partes);
    }

    private function capturarIpReal(Request $request): string
    {
        $headers = [
            'CF-Connecting-IP',
            'True-Client-IP',
            'X-Forwarded-For',
            'X-Real-IP',
        ];

        foreach ($headers as $header) {
            $ip = $request->header($header);
            if (!$ip) {
                continue;
            }

            $ips = explode(',', (string) $ip);
            $ipReal = trim((string) ($ips[0] ?? ''));
            if ($ipReal !== '' && filter_var($ipReal, FILTER_VALIDATE_IP)) {
                return $ipReal;
            }
        }

        return (string) $request->ip();
    }

    private function formatarValor(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }
}
