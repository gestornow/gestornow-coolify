<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\laravel_example\UserManagement;
use App\Http\Controllers\authentications\LoginCover;
use App\Http\Controllers\auth\RegistroController;
use App\Http\Controllers\usuario\UserController;
use App\Http\Controllers\GrupoPermissaoController;
use App\Http\Controllers\auth\PasswordResetController;
use App\Http\Controllers\auth\LoginController; // Import do LoginController
use App\Http\Controllers\Billing\AssinaturaController as BillingAssinaturaController;
use App\Http\Controllers\Billing\MeuFinanceiroController;
use App\Http\Controllers\Billing\ContractController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\NotificacaoController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

$controller_path = 'App\Http\Controllers';

use App\Http\Controllers\HomeController;

// ==============================================================
// ROTA PRINCIPAL
// ==============================================================
// Rota principal - redireciona para dashboard se autenticado, ou para login se não autenticado
Route::get('/', HomeController::class)->name('home');

// ==============================================================
// ROTAS PÚBLICAS - NÃO EXIGEM AUTENTICAÇÃO  
// ==============================================================

// locale (pode ser acessada sem autenticação)
Route::get('lang/{locale}', $controller_path . '\language\LanguageController@swap');

// Rota para renovar token CSRF (evita erro 419) - PROTEGIDA APENAS POR AUTENTICAÇÃO SIMPLES
Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->name('csrf-token');

// Rotas de autenticação - LOGIN, REGISTRO, VALIDAÇÃO, RESET DE SENHA
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login.form');
Route::post('/login', [LoginController::class, 'login'])->name('login')->middleware(['guest', 'throttle:auth-login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/auth/status', [LoginController::class, 'status'])->name('auth.status')->middleware('simple.auth');
Route::post('/auth/renovar-sessao', [LoginController::class, 'renewSession'])->name('auth.renew'); // Acessível a usuários autenticados ou para recuperação

Route::get('/assinatura-digital/{token}', [\App\Http\Controllers\Locacao\LocacaoController::class, 'formularioAssinaturaDigital'])->name('locacoes.assinatura-digital.form');
Route::post('/assinatura-digital/{token}/assinar', [\App\Http\Controllers\Locacao\LocacaoController::class, 'salvarAssinaturaDigital'])->name('locacoes.assinatura-digital.salvar');
Route::get('/assinatura-digital/{token}/contrato-pdf', [\App\Http\Controllers\Locacao\LocacaoController::class, 'contratoPdfAssinaturaDigital'])->name('locacoes.assinatura-digital.contrato');
Route::get('/assinatura-digital/{token}/contrato-assinado', [\App\Http\Controllers\Locacao\LocacaoController::class, 'visualizarContratoAssinado'])->name('locacoes.assinatura-digital.contrato-assinado');

Route::get('/registro', function () {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('content.authentications.auth-register-cover', ['pageConfigs' => $pageConfigs]);
})->name('registro.form')->middleware('guest');

Route::post('/registro', [RegistroController::class, 'registro'])->name('registro')->middleware(['guest', 'throttle:auth-register']);

// Tela de confirmação de email enviado
Route::get('/email-enviado', function () {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('auth.email-enviado', ['pageConfigs' => $pageConfigs]);
})->name('email.enviado')->middleware('guest');

// Rotas para validação de email e criação de senha
Route::get('/validar-email/{token}', [RegistroController::class, 'validarEmail'])->name('validar.email')->middleware(['guest', 'throttle:auth-register']);
Route::post('/criar-senha', [RegistroController::class, 'criarSenha'])->name('criar.senha')->middleware(['guest', 'throttle:auth-register']);

Route::get('/recuperar-senha', function () {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('content.authentications.auth-forgot-password-cover', ['pageConfigs' => $pageConfigs]);
})->name('recuperar-senha.form')->middleware('guest');

// Rotas para reset de senha com código
Route::get('/esqueci-senha', [PasswordResetController::class, 'showForgotForm'])->name('forgot-password.form')->middleware('guest');
Route::post('/esqueci-senha', [PasswordResetController::class, 'sendResetCode'])->name('forgot-password.send')->middleware(['guest', 'throttle:auth-reset']);
Route::get('/codigo-redefinicao', [PasswordResetController::class, 'showCodeForm'])->name('reset-code.form')->middleware('guest');
Route::post('/codigo-redefinicao', [PasswordResetController::class, 'verifyCode'])->name('reset-code.verify')->middleware(['guest', 'throttle:auth-reset']);
Route::post('/reenviar-codigo', [PasswordResetController::class, 'resendCode'])->name('reset-code.resend')->middleware(['guest', 'throttle:auth-reset']);
Route::get('/nova-senha', [PasswordResetController::class, 'showResetForm'])->name('reset-password.form')->middleware('guest');
Route::post('/nova-senha', [PasswordResetController::class, 'updatePassword'])->name('reset-password.update')->middleware(['guest', 'throttle:auth-reset']);

// Rota para criar senha de usuário criado by admin
Route::get('/usuario-criar-senha/{token}', [UserController::class, 'validarCodigoReset'])->name('usuario.validar-codigo-reset')->middleware('guest');
Route::post('/usuario-criar-senha', [UserController::class, 'criarSenhaUsuario'])->name('usuario.criar-senha')->middleware(['guest', 'throttle:auth-reset']);

// Rotas para criação de conta de teste
Route::get('/teste/criar', function (Request $request) {
    $query = http_build_query($request->query());
    $destino = $query ? '/registro?' . $query : '/registro';

    return redirect($destino);
})->name('teste.criar')->middleware('guest');
Route::post('/teste/criar', [\App\Http\Controllers\TesteController::class, 'store'])->name('teste.store')->middleware(['guest', 'throttle:auth-register']);

// ==============================================================
// ROTAS PROTEGIDAS - EXIGEM AUTENTICAÇÃO
// ==============================================================

Route::middleware(['simple.auth', 'verify.unique.session', 'verificar.empresa', 'verificar.onboarding.assinatura'])->group(function () use ($controller_path) {

    // Dashboard routes
    Route::get('/dashboard', $controller_path . '\dashboard\Analytics@index')->name('dashboard');
    Route::get('/dashboard/analytics', $controller_path . '\dashboard\Analytics@index')->name('dashboard-analytics');
    Route::get('/dashboard/crm', $controller_path . '\dashboard\Crm@index')->name('dashboard-crm');
    Route::get('/dashboard/ecommerce', $controller_path . '\dashboard\Ecommerce@index')->name('dashboard-ecommerce');

    // Notificacoes internas
    Route::get('/notificacoes', [NotificacaoController::class, 'index']);
    Route::get('/notificacoes/count', [NotificacaoController::class, 'count']);
    Route::post('/notificacoes/{id}/lida', [NotificacaoController::class, 'marcarLida'])
        ->whereNumber('id');
    Route::post('/notificacoes/todas-lidas', [NotificacaoController::class, 'marcarTodasLidas']);
    Route::post('/notificacoes/{id}/apagar', [NotificacaoController::class, 'apagar'])
        ->whereNumber('id');
    Route::post('/notificacoes/todas-apagadas', [NotificacaoController::class, 'apagarTodas']);

    // Billing do cliente (self-service)
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/meu-financeiro', [MeuFinanceiroController::class, 'index'])->name('meu-financeiro.index');
        Route::post('/meu-financeiro/metodo-mensal', [MeuFinanceiroController::class, 'atualizarMetodoMensal'])->name('meu-financeiro.metodo-mensal');
        Route::post('/meu-financeiro/metodo-adesao', [MeuFinanceiroController::class, 'atualizarMetodoAdesao'])->name('meu-financeiro.metodo-adesao');
        Route::post('/meu-financeiro/cadastrar-cartao', [MeuFinanceiroController::class, 'cadastrarCartao'])->name('meu-financeiro.cadastrar-cartao');
        Route::post('/meu-financeiro/upgrade/{idPlano}', [MeuFinanceiroController::class, 'upgradePlano'])->name('meu-financeiro.upgrade');
        Route::post('/meu-financeiro/cancelar-assinatura', [MeuFinanceiroController::class, 'cancelarAssinatura'])->name('meu-financeiro.cancelar-assinatura');

        // Contratos de Licenciamento SaaS
        Route::post('/contrato/aceitar', [ContractController::class, 'accept'])->name('contrato.aceitar');
        Route::get('/contrato/{id}', [ContractController::class, 'show'])->name('contrato.show');
        Route::get('/contrato/{id}/recibo', [ContractController::class, 'downloadRecibo'])->name('contrato.recibo');
    });

    // Onboarding obrigatório da assinatura
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding/dados', [OnboardingController::class, 'salvarDados'])->name('onboarding.dados');
    Route::post('/onboarding/contrato', [OnboardingController::class, 'assinarContrato'])->name('onboarding.contrato');
    Route::get('/onboarding/contrato/pdf', [OnboardingController::class, 'contratoPdf'])->name('onboarding.contrato.pdf');

    // Contratação self-service pelo dashboard
    Route::post('/planos/assinar/{idPlano}', [BillingAssinaturaController::class, 'assinarDashboard'])->name('planos.assinar');

    // layout
    Route::get('/layouts/collapsed-menu', $controller_path . '\layouts\CollapsedMenu@index')->name('layouts-collapsed-menu');
    Route::get('/layouts/content-navbar', $controller_path . '\layouts\ContentNavbar@index')->name('layouts-content-navbar');
    Route::get('/layouts/content-nav-sidebar', $controller_path . '\layouts\ContentNavSidebar@index')->name('layouts-content-nav-sidebar');
    Route::get('/layouts/navbar-full', function () {
        abort(404);
    });
    Route::get('/layouts/navbar-full-sidebar', function () {
        abort(404);
    });
    Route::get('/layouts/horizontal', $controller_path . '\layouts\Horizontal@index')->name('layouts-horizontal');
    Route::get('/layouts/vertical', $controller_path . '\layouts\Vertical@index')->name('layouts-vertical');
    Route::get('/layouts/without-menu', $controller_path . '\layouts\WithoutMenu@index')->name('layouts-without-menu');
    Route::get('/layouts/without-navbar', $controller_path . '\layouts\WithoutNavbar@index')->name('layouts-without-navbar');
    Route::get('/layouts/fluid', $controller_path . '\layouts\Fluid@index')->name('layouts-fluid');
    Route::get('/layouts/container', $controller_path . '\layouts\Container@index')->name('layouts-container');
    Route::get('/layouts/blank', $controller_path . '\layouts\Blank@index')->name('layouts-blank');

    // Troca de filial
    Route::post('/trocar-filial', function (Request $request) {
        $usuario = auth()->user();
        $idEmpresa = (int) $request->input('id_empresa');

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario nao autenticado.',
            ], 401);
        }

        $isSuporte = (int) ($usuario->is_suporte ?? $usuario->isSuporte ?? 0) === 1;
        if (!$isSuporte) {
            return response()->json([
                'success' => false,
                'message' => 'Acao nao permitida.',
            ], 403);
        }

        if ($idEmpresa <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Filial invalida.',
            ], 422);
        }

        $empresaExiste = \App\Domain\Auth\Models\Empresa::where('id_empresa', $idEmpresa)->exists();
        if (!$empresaExiste) {
            return response()->json([
                'success' => false,
                'message' => 'Filial nao encontrada.',
            ], 404);
        }

        session(['id_empresa' => $idEmpresa]);
        session(['id_empresa_selecionada' => $idEmpresa]);
        session()->save();

        return response()->json([
            'success' => true,
            'id_empresa' => $idEmpresa,
        ])->cookie(
            'id_empresa_suporte',
            (string) $idEmpresa,
            60 * 24 * 30,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        );
    })->name('trocar-filial');

    // Salvar tema do usuário
    Route::post('/salvar-tema', function (Request $request) {

        if (Auth::check()) {
            $user = Auth::user();

            \DB::table('usuarios')
                ->where('id_usuario', $user->id_usuario)
                ->update(['tema' => $request->tema]);

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
    })->name('salvar-tema');

    // apps
    Route::get('/app/email', $controller_path . '\apps\Email@index')->name('app-email');
    Route::get('/app/chat', $controller_path . '\apps\Chat@index')->name('app-chat');
    Route::get('/app/calendar', $controller_path . '\apps\Calendar@index')->name('app-calendar');
    Route::get('/app/kanban', $controller_path . '\apps\Kanban@index')->name('app-kanban');
    Route::get('/app/invoice/list', $controller_path . '\apps\InvoiceList@index')->name('app-invoice-list');
    Route::get('/app/invoice/preview', $controller_path . '\apps\InvoicePreview@index')->name('app-invoice-preview');
    Route::get('/app/invoice/print', $controller_path . '\apps\InvoicePrint@index')->name('app-invoice-print');
    Route::get('/app/invoice/edit', $controller_path . '\apps\InvoiceEdit@index')->name('app-invoice-edit');
    Route::get('/app/invoice/add', $controller_path . '\apps\InvoiceAdd@index')->name('app-invoice-add');
    Route::get('/usuarios/lista', $controller_path . '\usuario\UserController@index')->name('app-user-list');
    Route::get('/app/user/view/account', $controller_path . '\apps\UserViewAccount@index')->name('app-user-view-account');
    Route::get('/app/user/view/security', $controller_path . '\apps\UserViewSecurity@index')->name('app-user-view-security');
    Route::get('/app/user/view/billing', $controller_path . '\apps\UserViewBilling@index')->name('app-user-view-billing');
    Route::get('/app/user/view/notifications', $controller_path . '\apps\UserViewNotifications@index')->name('app-user-view-notifications');
    Route::get('/app/user/view/connections', $controller_path . '\apps\UserViewConnections@index')->name('app-user-view-connections');
    Route::get('/app/access-roles', $controller_path . '\apps\AccessRoles@index')->name('app-access-roles');
    Route::get('/app/access-permission', $controller_path . '\apps\AccessPermission@index')->name('app-access-permission');

    // pages
    Route::get('/pages/profile-user', $controller_path . '\pages\UserProfile@index')->name('pages-profile-user');
    Route::get('/pages/profile-teams', $controller_path . '\pages\UserTeams@index')->name('pages-profile-teams');
    Route::get('/pages/profile-projects', $controller_path . '\pages\UserProjects@index')->name('pages-profile-projects');
    Route::get('/pages/profile-connections', $controller_path . '\pages\UserConnections@index')->name('pages-profile-connections');
    Route::get('/pages/account-settings-account', $controller_path . '\pages\AccountSettingsAccount@index')->name('pages-account-settings-account');
    Route::get('/pages/account-settings-security', $controller_path . '\pages\AccountSettingsSecurity@index')->name('pages-account-settings-security');
    Route::get('/pages/account-settings-billing', $controller_path . '\pages\AccountSettingsBilling@index')->name('pages-account-settings-billing');
    Route::get('/pages/account-settings-notifications', $controller_path . '\pages\AccountSettingsNotifications@index')->name('pages-account-settings-notifications');
    Route::get('/pages/account-settings-connections', $controller_path . '\pages\AccountSettingsConnections@index')->name('pages-account-settings-connections');
    Route::get('/pages/faq', $controller_path . '\pages\Faq@index')->name('pages-faq');
    Route::get('/pages/help-center-landing', $controller_path . '\pages\HelpCenterLanding@index')->name('pages-help-center-landing');
    Route::get('/pages/help-center-categories', $controller_path . '\pages\HelpCenterCategories@index')->name('pages-help-center-categories');
    Route::get('/pages/help-center-article', $controller_path . '\pages\HelpCenterArticle@index')->name('pages-help-center-article');
    Route::get('/pages/pricing', $controller_path . '\pages\Pricing@index')->name('pages-pricing');
    Route::get('/pages/misc-error', $controller_path . '\pages\MiscError@index')->name('pages-misc-error');
    Route::get('/pages/misc-under-maintenance', $controller_path . '\pages\MiscUnderMaintenance@index')->name('pages-misc-under-maintenance');
    Route::get('/pages/misc-comingsoon', $controller_path . '\pages\MiscComingSoon@index')->name('pages-misc-comingsoon');
    Route::get('/pages/misc-not-authorized', $controller_path . '\pages\MiscNotAuthorized@index')->name('pages-misc-not-authorized');

    // authentication demos (protegidas, são apenas demos)
    Route::get('/auth/login-basic', $controller_path . '\authentications\LoginBasic@index')->name('auth-login-basic');
    Route::get('/auth/login-cover', $controller_path . '\authentications\LoginCover@index')->name('auth-login-cover');
    Route::get('/auth/register-basic', $controller_path . '\authentications\RegisterBasic@index')->name('auth-register-basic');
    Route::get('/auth/register-cover', $controller_path . '\authentications\RegisterCover@index')->name('auth-register-cover');
    Route::get('/auth/register-multisteps', $controller_path . '\authentications\RegisterMultiSteps@index')->name('auth-register-multisteps');
    Route::get('/auth/verify-email-basic', $controller_path . '\authentications\VerifyEmailBasic@index')->name('auth-verify-email-basic');
    Route::get('/auth/verify-email-cover', $controller_path . '\authentications\VerifyEmailCover@index')->name('auth-verify-email-cover');
    Route::get('/auth/reset-password-basic', $controller_path . '\authentications\ResetPasswordBasic@index')->name('auth-reset-password-basic');
    Route::get('/auth/reset-password-cover', $controller_path . '\authentications\ResetPasswordCover@index')->name('auth-reset-password-cover');
    Route::get('/auth/forgot-password-basic', $controller_path . '\authentications\ForgotPasswordBasic@index')->name('auth-forgot-password-basic');
    Route::get('/auth/forgot-password-cover', $controller_path . '\authentications\ForgotPasswordCover@index')->name('auth-forgot-password-cover');
    Route::get('/auth/two-steps-basic', $controller_path . '\authentications\TwoStepsBasic@index')->name('auth-two-steps-basic');
    Route::get('/auth/two-steps-cover', $controller_path . '\authentications\TwoStepsCover@index')->name('auth-two-steps-cover');

    // wizard example
    Route::get('/wizard/ex-checkout', $controller_path . '\wizard_example\Checkout@index')->name('wizard-ex-checkout');
    Route::get('/wizard/ex-property-listing', $controller_path . '\wizard_example\PropertyListing@index')->name('wizard-ex-property-listing');
    Route::get('/wizard/ex-create-deal', $controller_path . '\wizard_example\CreateDeal@index')->name('wizard-ex-create-deal');

    // modal
    Route::get('/modal-examples', $controller_path . '\modal\ModalExample@index')->name('modal-examples');

    // cards
    Route::get('/cards/basic', $controller_path . '\cards\CardBasic@index')->name('cards-basic');
    Route::get('/cards/advance', $controller_path . '\cards\CardAdvance@index')->name('cards-advance');
    Route::get('/cards/statistics', $controller_path . '\cards\CardStatistics@index')->name('cards-statistics');
    Route::get('/cards/analytics', $controller_path . '\cards\CardAnalytics@index')->name('cards-analytics');
    Route::get('/cards/actions', $controller_path . '\cards\CardActions@index')->name('cards-actions');

    // User Interface
    Route::get('/ui/accordion', $controller_path . '\user_interface\Accordion@index')->name('ui-accordion');
    Route::get('/ui/alerts', $controller_path . '\user_interface\Alerts@index')->name('ui-alerts');
    Route::get('/ui/badges', $controller_path . '\user_interface\Badges@index')->name('ui-badges');
    Route::get('/ui/buttons', $controller_path . '\user_interface\Buttons@index')->name('ui-buttons');
    Route::get('/ui/carousel', $controller_path . '\user_interface\Carousel@index')->name('ui-carousel');
    Route::get('/ui/collapse', $controller_path . '\user_interface\Collapse@index')->name('ui-collapse');
    Route::get('/ui/dropdowns', $controller_path . '\user_interface\Dropdowns@index')->name('ui-dropdowns');
    Route::get('/ui/footer', $controller_path . '\user_interface\Footer@index')->name('ui-footer');
    Route::get('/ui/list-groups', $controller_path . '\user_interface\ListGroups@index')->name('ui-list-groups');
    Route::get('/ui/modals', $controller_path . '\user_interface\Modals@index')->name('ui-modals');
    Route::get('/ui/navbar', $controller_path . '\user_interface\Navbar@index')->name('ui-navbar');
    Route::get('/ui/offcanvas', $controller_path . '\user_interface\Offcanvas@index')->name('ui-offcanvas');
    Route::get('/ui/pagination-breadcrumbs', $controller_path . '\user_interface\PaginationBreadcrumbs@index')->name('ui-pagination-breadcrumbs');
    Route::get('/ui/progress', $controller_path . '\user_interface\Progress@index')->name('ui-progress');
    Route::get('/ui/spinners', $controller_path . '\user_interface\Spinners@index')->name('ui-spinners');
    Route::get('/ui/tabs-pills', $controller_path . '\user_interface\TabsPills@index')->name('ui-tabs-pills');
    Route::get('/ui/toasts', $controller_path . '\user_interface\Toasts@index')->name('ui-toasts');
    Route::get('/ui/tooltips-popovers', $controller_path . '\user_interface\TooltipsPopovers@index')->name('ui-tooltips-popovers');
    Route::get('/ui/typography', $controller_path . '\user_interface\Typography@index')->name('ui-typography');

    // extended ui
    Route::get('/extended/ui-avatar', $controller_path . '\extended_ui\Avatar@index')->name('extended-ui-avatar');
    Route::get('/extended/ui-blockui', $controller_path . '\extended_ui\BlockUI@index')->name('extended-ui-blockui');
    Route::get('/extended/ui-drag-and-drop', $controller_path . '\extended_ui\DragAndDrop@index')->name('extended-ui-drag-and-drop');
    Route::get('/extended/ui-media-player', $controller_path . '\extended_ui\MediaPlayer@index')->name('extended-ui-media-player');
    Route::get('/extended/ui-perfect-scrollbar', $controller_path . '\extended_ui\PerfectScrollbar@index')->name('extended-ui-perfect-scrollbar');
    Route::get('/extended/ui-star-ratings', $controller_path . '\extended_ui\StarRatings@index')->name('extended-ui-star-ratings');
    Route::get('/extended/ui-sweetalert2', $controller_path . '\extended_ui\SweetAlert@index')->name('extended-ui-sweetalert2');
    Route::get('/extended/ui-text-divider', $controller_path . '\extended_ui\TextDivider@index')->name('extended-ui-text-divider');
    Route::get('/extended/ui-timeline-basic', $controller_path . '\extended_ui\TimelineBasic@index')->name('extended-ui-timeline-basic');
    Route::get('/extended/ui-timeline-fullscreen', $controller_path . '\extended_ui\TimelineFullscreen@index')->name('extended-ui-timeline-fullscreen');
    Route::get('/extended/ui-tour', $controller_path . '\extended_ui\Tour@index')->name('extended-ui-tour');
    Route::get('/extended/ui-treeview', $controller_path . '\extended_ui\Treeview@index')->name('extended-ui-treeview');
    Route::get('/extended/ui-misc', $controller_path . '\extended_ui\Misc@index')->name('extended-ui-misc');

    // icons
    Route::get('/icons/tabler', $controller_path . '\icons\Tabler@index')->name('icons-tabler');
    Route::get('/icons/font-awesome', $controller_path . '\icons\FontAwesome@index')->name('icons-font-awesome');

    // form elements
    Route::get('/forms/basic-inputs', $controller_path . '\form_elements\BasicInput@index')->name('forms-basic-inputs');
    Route::get('/forms/input-groups', $controller_path . '\form_elements\InputGroups@index')->name('forms-input-groups');
    Route::get('/forms/custom-options', $controller_path . '\form_elements\CustomOptions@index')->name('forms-custom-options');
    Route::get('/forms/editors', $controller_path . '\form_elements\Editors@index')->name('forms-editors');
    Route::get('/forms/file-upload', $controller_path . '\form_elements\FileUpload@index')->name('forms-file-upload');
    Route::get('/forms/pickers', $controller_path . '\form_elements\Picker@index')->name('forms-pickers');
    Route::get('/forms/selects', $controller_path . '\form_elements\Selects@index')->name('forms-selects');
    Route::get('/forms/sliders', $controller_path . '\form_elements\Sliders@index')->name('forms-sliders');
    Route::get('/forms/switches', $controller_path . '\form_elements\Switches@index')->name('forms-switches');
    Route::get('/forms/extras', $controller_path . '\form_elements\Extras@index')->name('forms-extras');

    // form layouts
    Route::get('/form/layouts-vertical', $controller_path . '\form_layouts\VerticalForm@index')->name('form-layouts-vertical');
    Route::get('/form/layouts-horizontal', $controller_path . '\form_layouts\HorizontalForm@index')->name('form-layouts-horizontal');
    Route::get('/form/layouts-sticky', $controller_path . '\form_layouts\StickyActions@index')->name('form-layouts-sticky');

    // form wizards
    Route::get('/form/wizard-numbered', $controller_path . '\form_wizard\Numbered@index')->name('form-wizard-numbered');
    Route::get('/form/wizard-icons', $controller_path . '\form_wizard\Icons@index')->name('form-wizard-icons');
    Route::get('/form/validation', $controller_path . '\form_validation\Validation@index')->name('form-validation');

    // tables
    Route::get('/tables/basic', $controller_path . '\tables\Basic@index')->name('tables-basic');
    Route::get('/tables/datatables-basic', $controller_path . '\tables\DatatableBasic@index')->name('tables-datatables-basic');
    Route::get('/tables/datatables-advanced', $controller_path . '\tables\DatatableAdvanced@index')->name('tables-datatables-advanced');
    Route::get('/tables/datatables-extensions', $controller_path . '\tables\DatatableExtensions@index')->name('tables-datatables-extensions');

    // charts
    Route::get('/charts/apex', $controller_path . '\charts\ApexCharts@index')->name('charts-apex');
    Route::get('/charts/chartjs', $controller_path . '\charts\ChartJs@index')->name('charts-chartjs');

    // maps
    Route::get('/maps/leaflet', $controller_path . '\maps\Leaflet@index')->name('maps-leaflet');

    // laravel example
    Route::get('/laravel/user-management', [UserManagement::class, 'UserManagement'])->name('laravel-example-user-management');
    Route::resource('/user-list', UserManagement::class);

    // ==============================================================
    // ROTAS PARA USUÁRIOS
    // ==============================================================

    Route::prefix('usuarios')->group(function () {
        Route::get('/', [\App\Http\Controllers\usuario\UserController::class, 'index'])->name('usuarios.index')->middleware('perm:admin.visualizar')->middleware('perm:usuarios.visualizar');
        // criação via POST (form do modal)
        Route::post('/', [\App\Http\Controllers\usuario\UserController::class, 'create'])->name('usuarios.store')->middleware('perm:admin.visualizar')->middleware('perm:usuarios.criar');
        Route::get('/create', [\App\Http\Controllers\usuario\UserController::class, 'create'])->name('usuarios.criar')->middleware('perm:admin.visualizar')->middleware('perm:usuarios.criar');
        Route::post('/excluir-multiplos', [\App\Http\Controllers\usuario\UserController::class, 'excluirMultiplos'])->name('usuarios.excluir-multiplos')->middleware('perm:admin.visualizar')->middleware('perm:usuarios.excluir');

        // Invalidar cache de fotos dos usuários
        Route::post('/invalidar-cache-fotos', [\App\Http\Controllers\usuario\UserController::class, 'invalidarCacheFotos'])->name('usuarios.invalidar-cache-fotos')->middleware('perm:admin.visualizar')->middleware('perm:usuarios.editar');

        Route::get('/modulos-disponiveis', [\App\Http\Controllers\usuario\PermissaoController::class, 'obterModulosDisponiveis'])->name('usuarios.modulos-disponiveis')->middleware('perm:admin.visualizar')->middleware('perm:usuarios.permissoes');
        Route::post('/salvar-permissoes', [\App\Http\Controllers\usuario\PermissaoController::class, 'salvarPermissoes'])->name('usuarios.salvar-permissoes')->middleware('perm:admin.visualizar')->middleware('perm:usuarios.permissoes');

        Route::get('/{usuario}/edit', [\App\Http\Controllers\usuario\UserController::class, 'edit'])->name('usuarios.editar')->middleware('perm:admin.visualizar')->middleware('perm:usuarios.editar');
        Route::get('/{usuario}/permissoes', [\App\Http\Controllers\usuario\PermissaoController::class, 'obterPermissoes'])->name('usuarios.obter-permissoes')->middleware('perm:admin.visualizar')->middleware('perm:usuarios.permissoes');
        Route::get('/{usuario}', [\App\Http\Controllers\usuario\UserController::class, 'show'])->name('usuarios.show')->middleware('perm:admin.visualizar')->middleware('perm:usuarios.visualizar');
        Route::put('/{usuario}', [\App\Http\Controllers\usuario\UserController::class, 'update'])->name('usuarios.atualizar')->middleware('perm:admin.visualizar')->middleware('perm:usuarios.editar');
        Route::put('/{usuario}/alterar-senha', [\App\Http\Controllers\usuario\UserController::class, 'alterarSenha'])->name('usuarios.alterar-senha')->middleware('perm:admin.visualizar')->middleware('perm:usuarios.editar');
        Route::delete('/{usuario}', [\App\Http\Controllers\usuario\UserController::class, 'destroy'])->name('usuarios.deletar')->middleware('perm:admin.visualizar')->middleware('perm:usuarios.excluir');
        // Route::post('/', [\App\Http\Controllers\usuario\UserController::class, 'statsUsers'])->name('usuarios.estatisticas');
    });

    // ==============================================================
    // ROTAS PARA CLIENTES
    // ==============================================================

    Route::prefix('clientes')->group(function () {
        Route::get('/', [\App\Http\Controllers\cliente\ClienteController::class, 'index'])->name('clientes.index')->middleware('perm:clientes.visualizar');
        Route::get('/create', [\App\Http\Controllers\cliente\ClienteController::class, 'create'])->name('clientes.criar')->middleware('perm:clientes.criar');
        Route::post('/', [\App\Http\Controllers\cliente\ClienteController::class, 'create'])->name('clientes.store')->middleware('perm:clientes.criar');
        Route::post('/excluir-multiplos', [\App\Http\Controllers\cliente\ClienteController::class, 'excluirMultiplos'])->name('clientes.excluir-multiplos')->middleware('perm:clientes.excluir');

        // Invalidar cache de fotos dos clientes
        Route::post('/invalidar-cache-fotos', [\App\Http\Controllers\cliente\ClienteController::class, 'invalidarCacheFotos'])->name('clientes.invalidar-cache-fotos')->middleware('perm:clientes.editar');
        Route::get('/localidades/cidades', [\App\Http\Controllers\cliente\ClienteController::class, 'cidadesPorUf'])->name('clientes.localidades.cidades')->middleware('perm:clientes.visualizar');

        // Retornar cliente em JSON para uso interno (Ajax)
        Route::get('/{cliente}/json', [\App\Http\Controllers\cliente\ClienteController::class, 'getJson'])->name('clientes.json')->middleware('perm:clientes.visualizar');
        Route::get('/{cliente}/logs-atividades', [\App\Http\Controllers\cliente\ClienteController::class, 'logsAtividades'])->name('clientes.logs-atividades')->middleware('perm:clientes.visualizar');

        Route::get('/{cliente}/edit', [\App\Http\Controllers\cliente\ClienteController::class, 'edit'])->name('clientes.editar')->middleware('perm:clientes.editar');
        Route::get('/{cliente}', [\App\Http\Controllers\cliente\ClienteController::class, 'show'])->name('clientes.show')->middleware('perm:clientes.visualizar');
        Route::put('/{cliente}', [\App\Http\Controllers\cliente\ClienteController::class, 'update'])->name('clientes.atualizar')->middleware('perm:clientes.editar');
        Route::delete('/{cliente}', [\App\Http\Controllers\cliente\ClienteController::class, 'destroy'])->name('clientes.deletar')->middleware('perm:clientes.excluir');
    });

    // ==============================================================
    // ROTAS PARA FORNECEDORES
    // ==============================================================

    Route::prefix('fornecedores')->group(function () {
        Route::get('/', [\App\Http\Controllers\fornecedor\FornecedorController::class, 'index'])->name('fornecedores.index')->middleware('perm:fornecedores.visualizar');
        Route::get('/create', [\App\Http\Controllers\fornecedor\FornecedorController::class, 'create'])->name('fornecedores.criar')->middleware('perm:fornecedores.criar');
        Route::post('/', [\App\Http\Controllers\fornecedor\FornecedorController::class, 'store'])->name('fornecedores.salvar')->middleware('perm:fornecedores.criar');
        Route::post('/excluir-multiplos', [\App\Http\Controllers\fornecedor\FornecedorController::class, 'excluirMultiplos'])->name('fornecedores.excluir-multiplos')->middleware('perm:fornecedores.excluir');
        Route::get('/{fornecedor}/edit', [\App\Http\Controllers\fornecedor\FornecedorController::class, 'edit'])->name('fornecedores.editar')->middleware('perm:fornecedores.editar');
        Route::put('/{fornecedor}', [\App\Http\Controllers\fornecedor\FornecedorController::class, 'update'])->name('fornecedores.atualizar')->middleware('perm:fornecedores.editar');
        Route::delete('/{fornecedor}', [\App\Http\Controllers\fornecedor\FornecedorController::class, 'destroy'])->name('fornecedores.deletar')->middleware('perm:fornecedores.excluir');
    });

    // ==============================================================
    // ROTAS PARA PRODUTOS
    // ==============================================================

    Route::prefix('produtos')->group(function () {
        Route::get('/', [\App\Http\Controllers\Produto\ProdutoController::class, 'index'])->name('produtos.index')->middleware('perm:produtos.visualizar');
        Route::get('/create', [\App\Http\Controllers\Produto\ProdutoController::class, 'create'])->name('produtos.criar')->middleware('perm:produtos.criar');
        Route::post('/', [\App\Http\Controllers\Produto\ProdutoController::class, 'store'])->name('produtos.store')->middleware('perm:produtos.criar');
        Route::post('/excluir-multiplos', [\App\Http\Controllers\Produto\ProdutoController::class, 'excluirMultiplos'])->name('produtos.excluir-multiplos')->middleware('perm:produtos.excluir');
        Route::get('/{produto}/informacoes', [\App\Http\Controllers\Produto\ProdutoController::class, 'informacoesProduto'])->name('produtos.informacoes')->middleware('perm:produtos.visualizar');
        Route::get('/{produto}/informacoes/pdf', [\App\Http\Controllers\Produto\ProdutoController::class, 'informacoesProdutoPdf'])->name('produtos.informacoes.pdf')->middleware('perm:produtos.visualizar');
        Route::get('/{produto}/informacoes/excel', [\App\Http\Controllers\Produto\ProdutoController::class, 'informacoesProdutoExcel'])->name('produtos.informacoes.excel')->middleware('perm:produtos.visualizar');
        Route::get('/{produto}/logs-atividades', [\App\Http\Controllers\Produto\ProdutoController::class, 'logsAtividades'])->name('produtos.logs-atividades')->middleware('perm:produtos.visualizar');
        Route::get('/{produto}/edit', [\App\Http\Controllers\Produto\ProdutoController::class, 'edit'])->name('produtos.edit')->middleware('perm:produtos.editar');
        Route::get('/{produto}', [\App\Http\Controllers\Produto\ProdutoController::class, 'show'])->name('produtos.show')->middleware('perm:produtos.visualizar');
        Route::put('/{produto}', [\App\Http\Controllers\Produto\ProdutoController::class, 'update'])->name('produtos.update')->middleware('perm:produtos.editar');
        Route::delete('/{produto}', [\App\Http\Controllers\Produto\ProdutoController::class, 'destroy'])->name('produtos.destroy')->middleware('perm:produtos.excluir');
        
        // Rotas de movimentação de estoque
        Route::get('/{produto}/movimentacoes-estoque', [\App\Http\Controllers\Produto\ProdutoController::class, 'movimentacoesEstoque'])->name('produtos.movimentacoes-estoque')->middleware('perm:produtos.movimentacao');
        Route::post('/{produto}/movimentacao-estoque', [\App\Http\Controllers\Produto\ProdutoController::class, 'registrarMovimentacao'])->name('produtos.registrar-movimentacao')->middleware('perm:produtos.movimentacao');
        
        // Rota de histórico de locações do produto
        Route::get('/{produto}/historico-locacoes', [\App\Http\Controllers\Produto\ProdutoController::class, 'historicoLocacoes'])->name('produtos.historico-locacoes')->middleware('perm:produtos.visualizar');
    });

    // ==============================================================
    // ROTAS PARA FINANCEIRO
    // ==============================================================

    Route::prefix('financeiro')->name('financeiro.')->group(function () {
        Route::get('contas-a-pagar', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'index'])->name('index');
        Route::get('contas-a-pagar/create', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'create'])->name('create');
        Route::post('contas-a-pagar', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'store'])->name('store');
        Route::post('contas-a-pagar/excluir-multiplos', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'excluirMultiplos'])->name('excluir-multiplos');
        Route::post('contas-a-pagar/{id}/dar-baixa', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'darBaixa'])->name('contas-a-pagar.dar-baixa');
        Route::post('contas-a-pagar/{id}/remover-baixa', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'removerBaixa'])->name('contas-a-pagar.remover-baixa');
        Route::get('contas-a-pagar/{id}/historico-pagamentos', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'historicoPagamentos'])->name('contas-a-pagar.historico-pagamentos');
        Route::get('contas-a-pagar/{id}/logs-atividades', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'logsAtividades'])->name('contas-a-pagar.logs-atividades');
        Route::get('contas-a-pagar/{id}/recibo', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'recibo'])->name('contas-a-pagar.recibo');
        Route::delete('contas-a-pagar/{id}/pagamentos/{idPagamento}', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'excluirPagamento'])->name('contas-a-pagar.excluir-pagamento');
        Route::get('contas-a-pagar/parcelas-data/{idParcelamento}', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'parcelasData'])->name('contas-a-pagar.parcelas-data');
        Route::get('contas-a-pagar/recorrencias-data/{idRecorrencia}', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'recorrenciasData'])->name('contas-a-pagar.recorrencias-data');
        Route::get('contas-a-pagar/{id}', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'show'])->name('show');
        Route::get('contas-a-pagar/{id}/edit', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'edit'])->name('edit');
        Route::put('contas-a-pagar/{id}', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'update'])->name('update');
        Route::delete('contas-a-pagar/{id}', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'destroy'])->name('destroy');
        Route::get('contas-a-pagar/{id}/parcelas', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'parcelas'])->name('contas-a-pagar.parcelas');
        Route::get('contas-a-pagar/{id}/recorrencias', [\App\Http\Controllers\Financeiro\ContasAPagarController::class, 'recorrencias'])->name('contas-a-pagar.recorrencias');

        // ==============================================================
        // ROTAS PARA CONTAS A RECEBER
        // ==============================================================
        Route::get('contas-a-receber', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'index'])->name('contas-a-receber.index');
        Route::get('contas-a-receber/create', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'create'])->name('contas-a-receber.create');
        Route::post('contas-a-receber', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'store'])->name('contas-a-receber.store');
        Route::post('contas-a-receber/excluir-multiplos', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'excluirMultiplos'])->name('contas-a-receber.excluir-multiplos');
        Route::post('contas-a-receber/{id}/dar-baixa', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'darBaixa'])->name('contas-a-receber.dar-baixa');
        Route::post('contas-a-receber/{id}/remover-baixa', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'removerBaixa'])->name('contas-a-receber.remover-baixa');
        Route::get('contas-a-receber/{id}/historico-recebimentos', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'historicoRecebimentos'])->name('contas-a-receber.historico-recebimentos');
        Route::get('contas-a-receber/{id}/logs-atividades', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'logsAtividades'])->name('contas-a-receber.logs-atividades');
        Route::get('contas-a-receber/{id}/recibo', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'recibo'])->name('contas-a-receber.recibo');
        Route::delete('contas-a-receber/{id}/recebimentos/{idRecebimento}', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'excluirRecebimento'])->name('contas-a-receber.excluir-recebimento');
        Route::get('contas-a-receber/parcelas-data/{idParcelamento}', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'parcelasData'])->name('contas-a-receber.parcelas-data');
        Route::get('contas-a-receber/recorrencias-data/{idRecorrencia}', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'recorrenciasData'])->name('contas-a-receber.recorrencias-data');
        Route::get('contas-a-receber/{id}', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'show'])->name('contas-a-receber.show');
        Route::get('contas-a-receber/{id}/edit', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'edit'])->name('contas-a-receber.edit');
        Route::put('contas-a-receber/{id}', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'update'])->name('contas-a-receber.update');
        Route::delete('contas-a-receber/{id}', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'destroy'])->name('contas-a-receber.destroy');
        Route::get('contas-a-receber/{id}/parcelas', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'parcelas'])->name('contas-a-receber.parcelas');
        Route::get('contas-a-receber/{id}/recorrencias', [\App\Http\Controllers\Financeiro\ContasAReceberController::class, 'recorrencias'])->name('contas-a-receber.recorrencias');

        // ==============================================================
        // ROTAS PARA RELATÓRIOS
        // ==============================================================
        // Relatórios de Contas a Receber
        Route::get('relatorios/contas-receber', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'contasReceberIndex'])->name('relatorios.contas-receber')->middleware('perm:financeiro.relatorios');
        Route::post('relatorios/contas-receber/gerar', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'contasReceberGerar'])->name('relatorios.contas-receber.gerar')->middleware('perm:financeiro.relatorios');
        Route::post('relatorios/contas-receber/pdf', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'contasReceberPDF'])->name('relatorios.contas-receber.pdf')->middleware('perm:financeiro.relatorios');
        Route::post('relatorios/contas-receber/excel', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'contasReceberExcel'])->name('relatorios.contas-receber.excel')->middleware('perm:financeiro.relatorios');
        
        // Relatórios de Contas a Pagar
        Route::get('relatorios/contas-pagar', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'contasPagarIndex'])->name('relatorios.contas-pagar')->middleware('perm:financeiro.relatorios');
        Route::post('relatorios/contas-pagar/gerar', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'contasPagarGerar'])->name('relatorios.contas-pagar.gerar')->middleware('perm:financeiro.relatorios');
        Route::post('relatorios/contas-pagar/pdf', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'contasPagarPDF'])->name('relatorios.contas-pagar.pdf')->middleware('perm:financeiro.relatorios');
        Route::post('relatorios/contas-pagar/excel', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'contasPagarExcel'])->name('relatorios.contas-pagar.excel')->middleware('perm:financeiro.relatorios');

        // Fluxo de Caixa
        Route::get('fluxo-caixa', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'fluxoCaixaIndex'])->name('fluxo-caixa.index');
        Route::post('fluxo-caixa/dados', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'fluxoCaixaDados'])->name('fluxo-caixa.dados');
        Route::get('fluxo-caixa/{id}/logs-atividades', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'fluxoCaixaLogsAtividades'])->name('fluxo-caixa.logs-atividades');
        Route::match(['get', 'post'], 'fluxo-caixa/pdf', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'fluxoCaixaPDF'])->name('fluxo-caixa.pdf');
        Route::match(['get', 'post'], 'fluxo-caixa/excel', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'fluxoCaixaExcel'])->name('fluxo-caixa.excel');
        
        // Relatórios Adicionais (Views em desenvolvimento)
        // Route::get('relatorios/dashboard-kpis', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'dashboardKPIsIndex'])->name('relatorios.dashboard-kpis');
        // Route::post('relatorios/dashboard-kpis/dados', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'dashboardKPIsDados'])->name('relatorios.dashboard-kpis.dados');
        // Route::get('relatorios/recebimentos-status', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'recebimentosStatusIndex'])->name('relatorios.recebimentos-status');
        // Route::get('relatorios/analise-propriedade', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'analisePropriedadeIndex'])->name('relatorios.analise-propriedade');
        // Route::get('relatorios/projecao-fluxo', [\App\Http\Controllers\Financeiro\RelatoriosController::class, 'projecaoFluxoIndex'])->name('relatorios.projecao-fluxo');

        // Rotas para bancos
        Route::get('bancos', [\App\Http\Controllers\Financeiro\BancosController::class, 'index'])->name('bancos.index')->middleware('perm:financeiro.bancos');
        Route::get('bancos/list', [\App\Http\Controllers\Financeiro\BancosController::class, 'list'])->name('bancos.list')->middleware('perm:financeiro.bancos');
        Route::post('bancos', [\App\Http\Controllers\Financeiro\BancosController::class, 'store'])->name('bancos.store')->middleware('perm:financeiro.bancos');
        Route::get('bancos/{id}', [\App\Http\Controllers\Financeiro\BancosController::class, 'show'])->name('bancos.show')->middleware('perm:financeiro.bancos');
        Route::put('bancos/{id}', [\App\Http\Controllers\Financeiro\BancosController::class, 'update'])->name('bancos.update')->middleware('perm:financeiro.bancos');
        Route::delete('bancos/{id}', [\App\Http\Controllers\Financeiro\BancosController::class, 'destroy'])->name('bancos.destroy')->middleware('perm:financeiro.bancos');
        Route::get('boletos/bancos-boleto', [\App\Http\Controllers\Financeiro\BoletosController::class, 'listarBancosBoleto'])->name('boletos.bancos-boleto')->middleware('perm:financeiro.bancos');

        // ==============================================================
        // ROTAS PARA BOLETOS
        // ==============================================================
        Route::prefix('boletos')->name('boletos.')->middleware('perm:financeiro.boletos')->group(function () {
            Route::get('/', [\App\Http\Controllers\Financeiro\BoletosController::class, 'index'])->name('index');
            Route::get('/bancos-disponiveis', [\App\Http\Controllers\Financeiro\BoletosController::class, 'bancosDisponiveis'])->name('bancos-disponiveis');
            Route::get('/cora/authorize/{idBancos}', [\App\Http\Controllers\Financeiro\BoletosController::class, 'coraAutorizar'])->name('cora.authorize');
            Route::get('/cora/callback', [\App\Http\Controllers\Financeiro\BoletosController::class, 'coraCallback'])->name('cora.callback');
            Route::post('/gerar', [\App\Http\Controllers\Financeiro\BoletosController::class, 'gerar'])->name('gerar');
            Route::get('/{id}/pdf', [\App\Http\Controllers\Financeiro\BoletosController::class, 'pdf'])->name('pdf');
            Route::get('/{id}/consultar', [\App\Http\Controllers\Financeiro\BoletosController::class, 'consultar'])->name('consultar');
            Route::get('/{id}/historico', [\App\Http\Controllers\Financeiro\BoletosController::class, 'historico'])->name('historico');
            Route::post('/{id}/alterar-vencimento', [\App\Http\Controllers\Financeiro\BoletosController::class, 'alterarVencimento'])->name('alterar-vencimento');
            Route::get('/conta/{idContaReceber}', [\App\Http\Controllers\Financeiro\BoletosController::class, 'porContaReceber'])->name('por-conta');
            
            // Configuração de boleto por banco
            Route::get('/configuracao/{idBancos}', [\App\Http\Controllers\Financeiro\BoletosController::class, 'getConfiguracao'])->name('configuracao');
            Route::post('/configuracao', [\App\Http\Controllers\Financeiro\BoletosController::class, 'salvarConfiguracao'])->name('salvar-configuracao');
            Route::post('/upload-arquivo', [\App\Http\Controllers\Financeiro\BoletosController::class, 'uploadArquivo'])->name('upload-arquivo');
            Route::post('/desativar/{idBancos}', [\App\Http\Controllers\Financeiro\BoletosController::class, 'desativar'])->name('desativar');
        });

        // Rotas para categorias
        Route::get('categorias', [\App\Http\Controllers\Financeiro\CategoriasController::class, 'index'])->name('categorias.index')->middleware('perm:financeiro.categorias');
        Route::get('categorias/list', [\App\Http\Controllers\Financeiro\CategoriasController::class, 'list'])->name('categorias.list')->middleware('perm:financeiro.categorias');
        Route::post('categorias', [\App\Http\Controllers\Financeiro\CategoriasController::class, 'store'])->name('categorias.store')->middleware('perm:financeiro.categorias');
        Route::get('categorias/{tipo}', [\App\Http\Controllers\Financeiro\CategoriasController::class, 'getByTipo'])->name('categorias.by-tipo')->middleware('perm:financeiro.categorias');
        Route::get('categoria/{id}', [\App\Http\Controllers\Financeiro\CategoriasController::class, 'show'])->name('categoria.show')->middleware('perm:financeiro.categorias');
        Route::put('categoria/{id}', [\App\Http\Controllers\Financeiro\CategoriasController::class, 'update'])->name('categoria.update')->middleware('perm:financeiro.categorias');
        Route::delete('categoria/{id}', [\App\Http\Controllers\Financeiro\CategoriasController::class, 'destroy'])->name('categoria.destroy')->middleware('perm:financeiro.categorias');

        
        // Faturamento de locações
        Route::get('faturamento', [\App\Http\Controllers\Financeiro\FaturamentoController::class, 'index'])->name('faturamento.index')->middleware('perm:financeiro.faturamento');
        Route::get('faturamento/pendentes', [\App\Http\Controllers\Financeiro\FaturamentoController::class, 'listarPendentes'])->name('faturamento.pendentes')->middleware('perm:financeiro.faturamento');
        Route::post('faturamento/preview/{idLocacao}', [\App\Http\Controllers\Financeiro\FaturamentoController::class, 'previewMedicao'])->name('faturamento.preview')->middleware('perm:financeiro.faturamento');
        Route::post('faturamento/faturar/{idLocacao}', [\App\Http\Controllers\Financeiro\FaturamentoController::class, 'faturar'])->name('faturamento.faturar')->middleware('perm:financeiro.faturamento');
        Route::post('faturamento/faturar-lote', [\App\Http\Controllers\Financeiro\FaturamentoController::class, 'faturarLote'])->name('faturamento.faturar-lote')->middleware('perm:financeiro.faturamento');
        Route::delete('faturamento/cancelar/{idFaturamento}', [\App\Http\Controllers\Financeiro\FaturamentoController::class, 'cancelar'])->name('faturamento.cancelar')->middleware('perm:financeiro.faturamento');
        Route::delete('faturamento/cancelar-lote/{idGrupo}', [\App\Http\Controllers\Financeiro\FaturamentoController::class, 'cancelarLote'])->name('faturamento.cancelar-lote')->middleware('perm:financeiro.faturamento');
        Route::get('faturamento/visualizar/{idFaturamento}', [\App\Http\Controllers\Financeiro\FaturamentoController::class, 'visualizar'])->name('faturamento.visualizar')->middleware('perm:financeiro.faturamento');
        Route::get('faturamento/pdf/{idFaturamento}', [\App\Http\Controllers\Financeiro\FaturamentoController::class, 'gerarPdf'])->name('faturamento.pdf')->middleware('perm:financeiro.faturamento');

    });

    // ==============================================================
    // ROTAS DE ADMINISTRAÇÃO DE PLANOS E MÓDULOS
    // ==============================================================
    // Rotas para administração de planos e módulos (verificação de suporte feita na navbar)
    Route::prefix('admin')->name('admin.')->group(function () {
        // Rotas de planos
        Route::resource('planos', \App\Http\Controllers\Admin\PlanosController::class)->except(['show']);
        Route::get('planos/{plano}', [\App\Http\Controllers\Admin\PlanosController::class, 'show'])->name('planos.show');
        Route::post('planos/confirm-update', [\App\Http\Controllers\Admin\PlanosController::class, 'confirmUpdate'])->name('planos.confirm-update');
        Route::patch('planos/{plano}/toggle-status', [\App\Http\Controllers\Admin\PlanosController::class, 'toggleStatus'])->name('planos.toggle-status');
        Route::post('planos/{plano}/promocoes', [\App\Http\Controllers\Admin\PlanosController::class, 'storePromocao'])->name('planos.promocoes.store');
        Route::delete('planos/{plano}/promocoes/{idPromocao}', [\App\Http\Controllers\Admin\PlanosController::class, 'destroyPromocao'])->name('planos.promocoes.destroy');

        // Rotas para planos contratados
        Route::get('planos-contratados', [\App\Http\Controllers\Admin\PlanosController::class, 'planosContratados'])->name('planos.contratados');
        Route::get('planos-contratados/{planoContratado}', [\App\Http\Controllers\Admin\PlanosController::class, 'showPlanoContratado'])->name('planos.contratados.show');
        Route::get('planos-contratados/{planoContratado}/edit', [\App\Http\Controllers\Admin\PlanosController::class, 'editPlanoContratado'])->name('planos.contratados.edit');
        Route::put('planos-contratados/{planoContratado}', [\App\Http\Controllers\Admin\PlanosController::class, 'updatePlanoContratado'])->name('planos.contratados.update');

        // API para contratar plano
        Route::post('contratar-plano', [\App\Http\Controllers\Admin\PlanosController::class, 'contratarPlano'])->name('planos.contratar');
        Route::post('assinaturas/contratar-plano', [BillingAssinaturaController::class, 'assinarComercial'])->name('assinaturas.contratar');

        // Rotas de filiais
        Route::get('filiais', [\App\Http\Controllers\Admin\FiliaisController::class, 'index'])->name('filiais.index');
        Route::get('filiais/create', [\App\Http\Controllers\Admin\FiliaisController::class, 'create'])->name('filiais.create');
        Route::post('filiais', [\App\Http\Controllers\Admin\FiliaisController::class, 'store'])->name('filiais.store');
        Route::get('filiais/{empresa}', [\App\Http\Controllers\Admin\FiliaisController::class, 'show'])->name('filiais.show');
        Route::get('filiais/{empresa}/edit', [\App\Http\Controllers\Admin\FiliaisController::class, 'edit'])->name('filiais.edit');
        Route::put('filiais/{empresa}', [\App\Http\Controllers\Admin\FiliaisController::class, 'update'])->name('filiais.update');
        Route::put('filiais/{empresa}/update-status', [\App\Http\Controllers\Admin\FiliaisController::class, 'updateStatus'])->name('filiais.update-status');
        Route::delete('filiais/delete-multiple', [\App\Http\Controllers\Admin\FiliaisController::class, 'deleteMultiple'])->name('filiais.delete-multiple');

        // Rota para buscar módulos de um plano contratado
        Route::get('planos-contratados/{idPlanoContratado}/modulos', [\App\Http\Controllers\Admin\PlanosController::class, 'getModulos'])->name('planos-contratados.modulos');

        // Rotas de módulos
        Route::get('modulos', [\App\Http\Controllers\Admin\ModulosController::class, 'index'])->name('modulos.index');
        Route::resource('modulos', \App\Http\Controllers\Admin\ModulosController::class);
        // Rota AJAX para listar módulos
        Route::get('modulos-list', [\App\Http\Controllers\Admin\ModulosController::class, 'list'])->name('modulos.list');

        // Rotas de categorias de menu
        Route::get('categorias', [\App\Http\Controllers\Admin\CategoriasMenuController::class, 'index'])->name('categorias.index');
        Route::get('categorias-menu', [\App\Http\Controllers\Admin\CategoriasMenuController::class, 'list'])->name('categorias.list');
        Route::post('categorias-menu', [\App\Http\Controllers\Admin\CategoriasMenuController::class, 'store'])->name('categorias.store');
        Route::get('categorias-menu/{id}', [\App\Http\Controllers\Admin\CategoriasMenuController::class, 'show'])->name('categorias.show');
        Route::put('categorias-menu/{id}', [\App\Http\Controllers\Admin\CategoriasMenuController::class, 'update'])->name('categorias.update');
        Route::delete('categorias-menu/{id}', [\App\Http\Controllers\Admin\CategoriasMenuController::class, 'destroy'])->name('categorias.destroy');

        // Rotas de logs administrativos (acesso interno: admin/suporte)
        Route::get('logs', [\App\Http\Controllers\Admin\AdminLogController::class, 'index'])
            ->name('logs.index')
            ->middleware('perm:admin.visualizar')
            ->middleware('perm:admin.logs.visualizar');

        Route::get('logs/{id}', [\App\Http\Controllers\Admin\AdminLogController::class, 'show'])
            ->name('logs.show')
            ->middleware('perm:admin.visualizar')
            ->middleware('perm:admin.logs.visualizar');
    });

    // Rota de debug para verificar session id_empresa
    Route::get('/debug/session-filial', function () {
        $idEmpresa = session('id_empresa');
        $nomeFilial = null;
        if ($idEmpresa) {
            $empresa = \App\Domain\Auth\Models\Empresa::find($idEmpresa);
            $nomeFilial = $empresa ? $empresa->nome_empresa : null;
        }
        return response()->json([
            'id_empresa' => $idEmpresa,
            'nome_filial' => $nomeFilial
        ]);
    })->middleware('simple.auth');

    // ==============================================================
    // ROTAS PARA ACESSÓRIOS
    // ==============================================================

    Route::prefix('acessorios')->group(function () {
        Route::get('/', [\App\Http\Controllers\Produto\AcessorioController::class, 'index'])->name('acessorios.index')->middleware('perm:produtos.acessorios');
        Route::get('/create', [\App\Http\Controllers\Produto\AcessorioController::class, 'create'])->name('acessorios.create')->middleware('perm:produtos.acessorios');
        Route::post('/', [\App\Http\Controllers\Produto\AcessorioController::class, 'store'])->name('acessorios.store')->middleware('perm:produtos.acessorios');
        Route::post('/excluir-multiplos', [\App\Http\Controllers\Produto\AcessorioController::class, 'excluirMultiplos'])->name('acessorios.excluir-multiplos')->middleware('perm:produtos.acessorios');
        Route::get('/buscar', [\App\Http\Controllers\Produto\AcessorioController::class, 'buscar'])->name('acessorios.buscar')->middleware('perm:produtos.acessorios');
        Route::get('/{acessorio}/edit', [\App\Http\Controllers\Produto\AcessorioController::class, 'edit'])->name('acessorios.edit')->middleware('perm:produtos.acessorios');
        Route::get('/{acessorio}', [\App\Http\Controllers\Produto\AcessorioController::class, 'show'])->name('acessorios.show')->middleware('perm:produtos.acessorios');
        Route::put('/{acessorio}', [\App\Http\Controllers\Produto\AcessorioController::class, 'update'])->name('acessorios.update')->middleware('perm:produtos.acessorios');
        Route::delete('/{acessorio}', [\App\Http\Controllers\Produto\AcessorioController::class, 'destroy'])->name('acessorios.destroy')->middleware('perm:produtos.acessorios');
    });

    // ==============================================================
    // ROTAS PARA MANUTENÇÕES
    // ==============================================================

    Route::prefix('manutencoes')->group(function () {
        Route::get('/', [\App\Http\Controllers\Produto\ManutencaoController::class, 'index'])->name('manutencoes.index')->middleware('perm:produtos.manutencao');
        Route::get('/create', [\App\Http\Controllers\Produto\ManutencaoController::class, 'create'])->name('manutencoes.create')->middleware('perm:produtos.manutencao');
        Route::post('/', [\App\Http\Controllers\Produto\ManutencaoController::class, 'store'])->name('manutencoes.store')->middleware('perm:produtos.manutencao');
        Route::get('/produto/{idProduto}', [\App\Http\Controllers\Produto\ManutencaoController::class, 'porProduto'])->name('manutencoes.por-produto')->middleware('perm:produtos.manutencao');
        Route::get('/{manutencao}/edit', [\App\Http\Controllers\Produto\ManutencaoController::class, 'edit'])->name('manutencoes.edit')->middleware('perm:produtos.manutencao');
        Route::get('/{manutencao}', [\App\Http\Controllers\Produto\ManutencaoController::class, 'show'])->name('manutencoes.show')->middleware('perm:produtos.manutencao');
        Route::put('/{manutencao}', [\App\Http\Controllers\Produto\ManutencaoController::class, 'update'])->name('manutencoes.update')->middleware('perm:produtos.manutencao');
        Route::delete('/{manutencao}', [\App\Http\Controllers\Produto\ManutencaoController::class, 'destroy'])->name('manutencoes.destroy')->middleware('perm:produtos.manutencao');
    });

    // ==============================================================
    // ROTAS PARA PATRIMÔNIOS
    // ==============================================================

    Route::prefix('patrimonios')->group(function () {
        Route::get('/', [\App\Http\Controllers\Produto\PatrimonioController::class, 'index'])->name('patrimonios.index')->middleware('perm:produtos.patrimonio');
        Route::get('/create', [\App\Http\Controllers\Produto\PatrimonioController::class, 'create'])->name('patrimonios.create')->middleware('perm:produtos.patrimonio');
        Route::post('/', [\App\Http\Controllers\Produto\PatrimonioController::class, 'store'])->name('patrimonios.store')->middleware('perm:produtos.patrimonio');
        Route::post('/massa', [\App\Http\Controllers\Produto\PatrimonioController::class, 'storeMassa'])->name('patrimonios.storeMassa')->middleware('perm:produtos.patrimonio');
        Route::post('/destroy-massa', [\App\Http\Controllers\Produto\PatrimonioController::class, 'destroyMassa'])->name('patrimonios.destroyMassa')->middleware('perm:produtos.patrimonio');
        Route::get('/produto/{idProduto}', [\App\Http\Controllers\Produto\PatrimonioController::class, 'porProduto'])->name('patrimonios.por-produto')->middleware('perm:produtos.patrimonio');
        Route::get('/disponiveis/{idProduto}', [\App\Http\Controllers\Produto\PatrimonioController::class, 'disponiveis'])->name('patrimonios.disponiveis')->middleware('perm:produtos.patrimonio');
        Route::get('/{patrimonio}/edit', [\App\Http\Controllers\Produto\PatrimonioController::class, 'edit'])->name('patrimonios.edit')->middleware('perm:produtos.patrimonio');
        Route::get('/{patrimonio}', [\App\Http\Controllers\Produto\PatrimonioController::class, 'show'])->name('patrimonios.show')->middleware('perm:produtos.patrimonio');
        Route::put('/{patrimonio}', [\App\Http\Controllers\Produto\PatrimonioController::class, 'update'])->name('patrimonios.update')->middleware('perm:produtos.patrimonio');
        Route::delete('/{patrimonio}', [\App\Http\Controllers\Produto\PatrimonioController::class, 'destroy'])->name('patrimonios.destroy')->middleware('perm:produtos.patrimonio');
    });

    // ==============================================================
    // ROTAS PARA TABELA DE PREÇOS
    // ==============================================================

    Route::prefix('tabela-precos')->group(function () {
        Route::get('/', [\App\Http\Controllers\Produto\TabelaPrecoController::class, 'index'])->name('tabela-precos.index')->middleware('perm:produtos.tabela-precos');
        Route::get('/create', [\App\Http\Controllers\Produto\TabelaPrecoController::class, 'create'])->name('tabela-precos.criar')->middleware('perm:produtos.tabela-precos');
        Route::post('/', [\App\Http\Controllers\Produto\TabelaPrecoController::class, 'store'])->name('tabela-precos.store')->middleware('perm:produtos.tabela-precos');
        Route::get('/produto/{idProduto}', [\App\Http\Controllers\Produto\TabelaPrecoController::class, 'porProduto'])->name('tabela-precos.por-produto')->middleware('perm:produtos.tabela-precos');
        Route::post('/calcular-preco', [\App\Http\Controllers\Produto\TabelaPrecoController::class, 'calcularPreco'])->name('tabela-precos.calcular')->middleware('perm:produtos.tabela-precos');
        Route::get('/{tabela}/edit', [\App\Http\Controllers\Produto\TabelaPrecoController::class, 'edit'])->name('tabela-precos.edit')->middleware('perm:produtos.tabela-precos');
        Route::get('/{tabela}', [\App\Http\Controllers\Produto\TabelaPrecoController::class, 'show'])->name('tabela-precos.show')->middleware('perm:produtos.tabela-precos');
        Route::put('/{tabela}', [\App\Http\Controllers\Produto\TabelaPrecoController::class, 'update'])->name('tabela-precos.update')->middleware('perm:produtos.tabela-precos');
        Route::delete('/{tabela}', [\App\Http\Controllers\Produto\TabelaPrecoController::class, 'destroy'])->name('tabela-precos.destroy')->middleware('perm:produtos.tabela-precos');
    });

    // ==============================================================
    // ROTAS PARA VINCULAR ACESSÓRIOS AOS PRODUTOS
    // ==============================================================

    Route::prefix('produtos/{produto}/acessorios')->middleware('perm:produtos.acessorios')->group(function () {
        Route::get('/', [\App\Http\Controllers\Produto\ProdutoAcessorioController::class, 'index'])->name('produtos.acessorios.index');
        Route::post('/', [\App\Http\Controllers\Produto\ProdutoAcessorioController::class, 'store'])->name('produtos.acessorios.store');
        Route::put('/{acessorio}', [\App\Http\Controllers\Produto\ProdutoAcessorioController::class, 'update'])->name('produtos.acessorios.update');
        Route::delete('/{acessorio}', [\App\Http\Controllers\Produto\ProdutoAcessorioController::class, 'destroy'])->name('produtos.acessorios.destroy');
        Route::post('/sync', [\App\Http\Controllers\Produto\ProdutoAcessorioController::class, 'sync'])->name('produtos.acessorios.sync');
    });

    // ==============================================================
    // ROTAS PARA LOCAÇÕES
    // ==============================================================

    Route::prefix('locacoes')->group(function () {
        Route::get('/', [\App\Http\Controllers\Locacao\LocacaoController::class, 'index'])->name('locacoes.index')->middleware('perm:locacoes.visualizar');
        Route::get('/pedidos', [\App\Http\Controllers\Locacao\LocacaoController::class, 'pedidos'])->name('locacoes.pedidos')->middleware('perm:locacoes.visualizar');
        Route::get('/expedicao', [\App\Http\Controllers\Locacao\ExpedicaoController::class, 'index'])->name('locacoes.expedicao')->middleware('perm:expedicao.logistica.visualizar');
        Route::patch('/expedicao/{locacao}/mover', [\App\Http\Controllers\Locacao\ExpedicaoController::class, 'moverCard'])->name('locacoes.expedicao.mover')->middleware('perm:expedicao.logistica.mover-card');
        Route::get('/expedicao/{locacao}/checklist', [\App\Http\Controllers\Locacao\ExpedicaoController::class, 'checklistDados'])->name('locacoes.expedicao.checklist')->middleware('perm:expedicao.logistica.checklist');
        Route::get('/expedicao/{locacao}/checklist/imprimir', [\App\Http\Controllers\Locacao\ExpedicaoController::class, 'imprimirChecklist'])->name('locacoes.expedicao.checklist.imprimir')->middleware('perm:expedicao.logistica.checklist');
        Route::post('/expedicao/{locacao}/checklist/foto', [\App\Http\Controllers\Locacao\ExpedicaoController::class, 'uploadFotoChecklist'])->name('locacoes.expedicao.checklist.foto')->middleware('perm:expedicao.logistica.checklist.foto');
        Route::delete('/expedicao/{locacao}/checklist/foto/{foto}', [\App\Http\Controllers\Locacao\ExpedicaoController::class, 'removerFotoChecklist'])->name('locacoes.expedicao.checklist.foto.remover')->middleware('perm:expedicao.logistica.checklist.foto');
        Route::post('/expedicao/{locacao}/checklist/confirmar', [\App\Http\Controllers\Locacao\ExpedicaoController::class, 'confirmarChecklist'])->name('locacoes.expedicao.checklist.confirmar')->middleware('perm:expedicao.logistica.checklist.confirmar');
        Route::get('/contratos', [\App\Http\Controllers\Locacao\LocacaoController::class, 'contratos'])->name('locacoes.contratos')->middleware('perm:locacoes.visualizar');
        Route::get('/trocas-produto', [\App\Http\Controllers\Locacao\LocacaoController::class, 'trocasProduto'])->name('locacoes.trocas-produto')->middleware('perm:expedicao.troca.visualizar');
        Route::get('/contratos/relatorio-pdf', [\App\Http\Controllers\Locacao\LocacaoController::class, 'relatorioGerencialContratosPdf'])->name('locacoes.contratos.relatorio-pdf')->middleware('perm:locacoes.contrato-pdf');
        Route::get('/medicoes', [\App\Http\Controllers\Locacao\LocacaoController::class, 'medicoes'])->name('locacoes.medicoes')->middleware('perm:locacoes.medicao');
        Route::get('/medicoes/{locacao}/relatorio-movimentacoes', [\App\Http\Controllers\Locacao\LocacaoController::class, 'relatorioMovimentacoesMedicao'])->name('locacoes.medicoes.relatorio-movimentacoes')->middleware('perm:locacoes.medicao');
        Route::get('/medicoes/{locacao}/relatorio-movimentacoes-pdf', [\App\Http\Controllers\Locacao\LocacaoController::class, 'relatorioMovimentacoesMedicaoPdf'])->name('locacoes.medicoes.relatorio-movimentacoes-pdf')->middleware('perm:locacoes.medicao');
        Route::get('/medicoes/{locacao}/itens', [\App\Http\Controllers\Locacao\LocacaoController::class, 'listarItensMovimentacaoMedicao'])->name('locacoes.medicoes.itens')->middleware('perm:locacoes.medicao');
        Route::get('/medicoes/{locacao}/produtos-disponiveis', [\App\Http\Controllers\Locacao\LocacaoController::class, 'listarProdutosDisponiveisMedicao'])->name('locacoes.medicoes.produtos-disponiveis')->middleware('perm:locacoes.medicao');
        Route::post('/medicoes/{locacao}/enviar-produto', [\App\Http\Controllers\Locacao\LocacaoController::class, 'enviarProdutoMedicao'])->name('locacoes.medicoes.enviar-produto')->middleware('perm:locacoes.medicao');
        Route::post('/medicoes/{locacao}/itens/{produtoLocacao}/retornar', [\App\Http\Controllers\Locacao\LocacaoController::class, 'retornarItemMedicao'])->name('locacoes.medicoes.retornar-item')->middleware('perm:locacoes.medicao');
        Route::post('/medicoes/{locacao}/itens/{produtoLocacao}/editar-datas', [\App\Http\Controllers\Locacao\LocacaoController::class, 'atualizarDatasItemMedicao'])->name('locacoes.medicoes.editar-datas-item')->middleware('perm:locacoes.medicao');
        Route::post('/medicoes/{locacao}/finalizar', [\App\Http\Controllers\Locacao\LocacaoController::class, 'finalizarContratoMedicao'])->name('locacoes.medicoes.finalizar')->middleware('perm:locacoes.medicao');
        Route::get('/orcamentos', [\App\Http\Controllers\Locacao\LocacaoController::class, 'orcamentos'])->name('locacoes.orcamentos')->middleware('perm:locacoes.visualizar');
        Route::get('/create', [\App\Http\Controllers\Locacao\LocacaoController::class, 'create'])->name('locacoes.create')->middleware('perm:locacoes.criar');
        Route::post('/', [\App\Http\Controllers\Locacao\LocacaoController::class, 'store'])->name('locacoes.store')->middleware('perm:locacoes.criar');
        Route::get('/produtos-disponiveis', [\App\Http\Controllers\Locacao\LocacaoController::class, 'produtosDisponiveis'])->name('locacoes.produtos-disponiveis')->middleware('perm:locacoes.visualizar');
        Route::get('/produtos-disponiveis-periodo', [\App\Http\Controllers\Locacao\LocacaoController::class, 'produtosDisponiveisPeriodo'])->name('locacoes.produtos-disponiveis-periodo')->middleware('perm:locacoes.visualizar');
        Route::get('/verificar-disponibilidade', [\App\Http\Controllers\Locacao\LocacaoController::class, 'verificarDisponibilidade'])->name('locacoes.verificar-disponibilidade')->middleware('perm:locacoes.visualizar');
        Route::get('/buscar-clientes', [\App\Http\Controllers\Locacao\LocacaoController::class, 'buscarClientes'])->name('locacoes.buscar-clientes')->middleware('perm:locacoes.visualizar');
        Route::get('/buscar-fornecedores', [\App\Http\Controllers\Locacao\LocacaoController::class, 'buscarFornecedores'])->name('locacoes.buscar-fornecedores')->middleware('perm:locacoes.visualizar');
        Route::get('/buscar-produtos-terceiros', [\App\Http\Controllers\Locacao\LocacaoController::class, 'buscarProdutosTerceiros'])->name('locacoes.buscar-produtos-terceiros')->middleware('perm:locacoes.visualizar');
        Route::get('/documentos', [\App\Http\Controllers\Locacao\LocacaoController::class, 'modelosContrato'])->name('locacoes.documentos')->middleware('perm:locacoes.visualizar');
        Route::get('/modelos-contrato', [\App\Http\Controllers\Locacao\LocacaoController::class, 'modelosContrato'])->name('locacoes.modelos-contrato')->middleware('perm:locacoes.visualizar');
        Route::get('/{locacao}/logs-atividades', [\App\Http\Controllers\Locacao\LocacaoController::class, 'logsAtividades'])->name('locacoes.logs-atividades')->middleware('perm:locacoes.visualizar');
        Route::get('/{locacao}/edit', [\App\Http\Controllers\Locacao\LocacaoController::class, 'edit'])->name('locacoes.edit')->middleware('perm:locacoes.editar');
        Route::delete('/{locacao}/itens/{produtoLocacao}', [\App\Http\Controllers\Locacao\LocacaoController::class, 'removerItemProduto'])->name('locacoes.remover-item-produto')->middleware('perm:locacoes.editar');
        Route::get('/{locacao}', [\App\Http\Controllers\Locacao\LocacaoController::class, 'show'])->name('locacoes.show')->middleware('perm:locacoes.visualizar');
        Route::put('/{locacao}', [\App\Http\Controllers\Locacao\LocacaoController::class, 'update'])->name('locacoes.update')->middleware('perm:locacoes.editar');
        Route::patch('/{locacao}/status', [\App\Http\Controllers\Locacao\LocacaoController::class, 'alterarStatus'])->name('locacoes.alterar-status')->middleware('perm:locacoes.alterar-status');
        Route::delete('/{locacao}', [\App\Http\Controllers\Locacao\LocacaoController::class, 'destroy'])->name('locacoes.destroy')->middleware('perm:locacoes.excluir');
        Route::post('/{locacao}/retornar', [\App\Http\Controllers\Locacao\LocacaoController::class, 'retornarLocacao'])->name('locacoes.retornar')->middleware('perm:locacoes.retornar');
        Route::post('/{locacao}/retorno-parcial', [\App\Http\Controllers\Locacao\LocacaoController::class, 'retornoParcial'])->name('locacoes.retorno-parcial')->middleware('perm:locacoes.retornar');
        Route::get('/{locacao}/disponibilidade-troca-produto', [\App\Http\Controllers\Locacao\LocacaoController::class, 'disponibilidadeTrocaProdutoContrato'])->name('locacoes.disponibilidade-troca-produto')->middleware('perm:expedicao.troca.executar');
        Route::post('/{locacao}/trocar-produto', [\App\Http\Controllers\Locacao\LocacaoController::class, 'trocarProdutoContrato'])->name('locacoes.trocar-produto')->middleware('perm:expedicao.troca.executar');
        Route::get('/{locacao}/itens-troca-produto', [\App\Http\Controllers\Locacao\LocacaoController::class, 'itensTrocaProdutoContrato'])->name('locacoes.itens-troca-produto')->middleware('perm:expedicao.troca.executar');
        Route::get('/{locacao}/trocas', [\App\Http\Controllers\Locacao\LocacaoController::class, 'listarTrocasContrato'])->name('locacoes.trocas')->middleware('perm:expedicao.troca.visualizar');
        Route::get('/trocas/{troca}/pdf', [\App\Http\Controllers\Locacao\LocacaoController::class, 'comprovanteTrocaProdutoPdf'])->name('locacoes.trocas.pdf')->middleware('perm:expedicao.troca.visualizar');
        Route::get('/{locacao}/itens-retorno-parcial', [\App\Http\Controllers\Locacao\LocacaoController::class, 'itensRetornoParcial'])->name('locacoes.itens-retorno-parcial')->middleware('perm:locacoes.retornar');
        Route::get('/{locacao}/itens-renovacao', [\App\Http\Controllers\Locacao\LocacaoController::class, 'itensRenovacao'])->name('locacoes.itens-renovacao')->middleware('perm:locacoes.renovar');
        Route::post('/{locacao}/renovar-aditivo', [\App\Http\Controllers\Locacao\LocacaoController::class, 'renovarAditivo'])->name('locacoes.renovar-aditivo')->middleware('perm:locacoes.renovar');
        Route::get('/{locacao}/patrimonios-pendentes', [\App\Http\Controllers\Locacao\LocacaoController::class, 'patrimoniosPendentes'])->name('locacoes.patrimonios-pendentes')->middleware('perm:locacoes.retornar');
        Route::post('/{locacao}/registrar-retorno-patrimonios', [\App\Http\Controllers\Locacao\LocacaoController::class, 'registrarRetornoPatrimonios'])->name('locacoes.registrar-retorno-patrimonios')->middleware('perm:locacoes.retornar');
        Route::get('/{locacao}/contrato-pdf', [\App\Http\Controllers\Locacao\LocacaoController::class, 'gerarContratoPdf'])->name('locacoes.contrato-pdf')->middleware('perm:locacoes.contrato-pdf');
        Route::get('/{locacao}/enviar-assinatura-digital', [\App\Http\Controllers\Locacao\LocacaoController::class, 'enviarAssinaturaDigital'])->name('locacoes.enviar-assinatura-digital')->middleware('perm:locacoes.assinatura-digital');
        Route::get('/patrimonio/{idPatrimonio}/historico', [\App\Http\Controllers\Locacao\LocacaoController::class, 'historicoPatrimonio'])->name('locacoes.historico-patrimonio')->middleware('perm:locacoes.visualizar');
    });

    Route::prefix('calendario')->name('calendario.')->group(function () {
        Route::get('/', [App\Http\Controllers\CalendarioController::class, 'index'])->name('index');
        Route::get('/eventos', [App\Http\Controllers\CalendarioController::class, 'eventos'])->name('eventos');
    });

    // ==============================================================
    // ROTAS PARA MODELOS DE CONTRATO
    // ==============================================================
    Route::prefix('documentos')->group(function () {
        Route::get('/', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'index'])->name('documentos.index')->middleware('perm:configuracoes.documentos.visualizar');
        Route::get('/create', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'create'])->name('documentos.create')->middleware('perm:configuracoes.documentos.gerenciar');
        Route::post('/', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'store'])->name('documentos.store')->middleware('perm:configuracoes.documentos.gerenciar');
        Route::get('/{id}/edit', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'edit'])->name('documentos.edit')->middleware('perm:configuracoes.documentos.gerenciar');
        Route::put('/{id}', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'update'])->name('documentos.update')->middleware('perm:configuracoes.documentos.gerenciar');
        Route::delete('/{id}', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'destroy'])->name('documentos.destroy')->middleware('perm:configuracoes.documentos.gerenciar');
        Route::get('/{id}/preview', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'preview'])->name('documentos.preview')->middleware('perm:configuracoes.documentos.visualizar');
        Route::post('/{id}/definir-padrao', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'definirPadrao'])->name('documentos.definir-padrao')->middleware('perm:configuracoes.documentos.gerenciar');
        Route::post('/{id}/duplicar', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'duplicar'])->name('documentos.duplicar')->middleware('perm:configuracoes.documentos.gerenciar');
    });

    Route::prefix('modelos-contrato')->group(function () {
        Route::get('/', function () {
            return redirect()->route('documentos.index');
        })->name('modelos-contrato.index')->middleware('perm:configuracoes.documentos.visualizar');
        Route::get('/create', function () {
            return redirect()->route('documentos.create', request()->query());
        })->name('modelos-contrato.create')->middleware('perm:configuracoes.documentos.gerenciar');
        Route::post('/', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'store'])->name('modelos-contrato.store')->middleware('perm:configuracoes.documentos.gerenciar');
        Route::get('/{id}/edit', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'edit'])->name('modelos-contrato.edit')->middleware('perm:configuracoes.documentos.gerenciar');
        Route::put('/{id}', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'update'])->name('modelos-contrato.update')->middleware('perm:configuracoes.documentos.gerenciar');
        Route::delete('/{id}', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'destroy'])->name('modelos-contrato.destroy')->middleware('perm:configuracoes.documentos.gerenciar');
        Route::get('/{id}/preview', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'preview'])->name('modelos-contrato.preview')->middleware('perm:configuracoes.documentos.visualizar');
        Route::post('/{id}/definir-padrao', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'definirPadrao'])->name('modelos-contrato.definir-padrao')->middleware('perm:configuracoes.documentos.gerenciar');
        Route::post('/{id}/duplicar', [\App\Http\Controllers\Locacao\ModeloContratoController::class, 'duplicar'])->name('modelos-contrato.duplicar')->middleware('perm:configuracoes.documentos.gerenciar');
    });

    // ==============================================================
    // ROTAS PARA CONFIGURAÇÕES DA EMPRESA (CLIENTE)
    // ==============================================================
    Route::prefix('configuracoes')->group(function () {
        Route::get('/', [\App\Http\Controllers\Configuracao\EmpresaConfiguracaoController::class, 'edit'])->name('configuracoes.empresa.edit')->middleware('perm:configuracoes.empresa.visualizar');
        Route::put('/', [\App\Http\Controllers\Configuracao\EmpresaConfiguracaoController::class, 'update'])->name('configuracoes.empresa.update')->middleware('perm:configuracoes.empresa.editar');

        Route::get('/grupos-permissoes', [GrupoPermissaoController::class, 'index'])->name('configuracoes.grupos-permissoes.index')->middleware('perm:configuracoes.permissoes.visualizar');
        Route::get('/grupos-permissoes/create', [GrupoPermissaoController::class, 'create'])->name('configuracoes.grupos-permissoes.create')->middleware('perm:configuracoes.permissoes.criar');
        Route::post('/grupos-permissoes', [GrupoPermissaoController::class, 'store'])->name('configuracoes.grupos-permissoes.store')->middleware('perm:configuracoes.permissoes.criar');
        Route::get('/grupos-permissoes/{id}/edit', [GrupoPermissaoController::class, 'edit'])->name('configuracoes.grupos-permissoes.edit')->middleware('perm:configuracoes.permissoes.editar');
        Route::put('/grupos-permissoes/{id}', [GrupoPermissaoController::class, 'update'])->name('configuracoes.grupos-permissoes.update')->middleware('perm:configuracoes.permissoes.editar');
        Route::delete('/grupos-permissoes/{id}', [GrupoPermissaoController::class, 'destroy'])->name('configuracoes.grupos-permissoes.destroy')->middleware('perm:configuracoes.permissoes.excluir');
    });

    // ==============================================================
    // ROTAS PARA PRODUTOS DE VENDA (PDV)
    // ==============================================================
    Route::prefix('produtos-venda')->group(function () {
        Route::get('/', [\App\Http\Controllers\Produto\ProdutoVendaController::class, 'index'])->name('produtos-venda.index')->middleware('perm:produtos-venda.gerenciar');
        Route::get('/create', [\App\Http\Controllers\Produto\ProdutoVendaController::class, 'create'])->name('produtos-venda.create')->middleware('perm:produtos-venda.gerenciar');
        Route::post('/', [\App\Http\Controllers\Produto\ProdutoVendaController::class, 'store'])->name('produtos-venda.store')->middleware('perm:produtos-venda.gerenciar');
        Route::post('/excluir-multiplos', [\App\Http\Controllers\Produto\ProdutoVendaController::class, 'excluirMultiplos'])->name('produtos-venda.excluir-multiplos')->middleware('perm:produtos-venda.gerenciar');
        Route::get('/buscar', [\App\Http\Controllers\Produto\ProdutoVendaController::class, 'buscarProduto'])->name('produtos-venda.buscar')->middleware('perm:produtos-venda.gerenciar');
        Route::get('/buscar-codigo', [\App\Http\Controllers\Produto\ProdutoVendaController::class, 'buscarPorCodigo'])->name('produtos-venda.buscar-codigo')->middleware('perm:produtos-venda.gerenciar');
        Route::get('/{id}', [\App\Http\Controllers\Produto\ProdutoVendaController::class, 'show'])->name('produtos-venda.show')->middleware('perm:produtos-venda.gerenciar');
        Route::get('/{id}/edit', [\App\Http\Controllers\Produto\ProdutoVendaController::class, 'edit'])->name('produtos-venda.edit')->middleware('perm:produtos-venda.gerenciar');
        Route::put('/{id}', [\App\Http\Controllers\Produto\ProdutoVendaController::class, 'update'])->name('produtos-venda.update')->middleware('perm:produtos-venda.gerenciar');
        Route::delete('/{id}', [\App\Http\Controllers\Produto\ProdutoVendaController::class, 'destroy'])->name('produtos-venda.destroy')->middleware('perm:produtos-venda.gerenciar');
        Route::post('/{id}/ajustar-estoque', [\App\Http\Controllers\Produto\ProdutoVendaController::class, 'ajustarEstoque'])->name('produtos-venda.ajustar-estoque')->middleware('perm:produtos-venda.gerenciar');
    });

    // ==============================================================
    // ROTAS PARA PDV (PONTO DE VENDA)
    // ==============================================================
    Route::prefix('pdv')->group(function () {
        Route::get('/', [\App\Http\Controllers\Venda\PDVController::class, 'index'])->name('pdv.index')->middleware('perm:pdv.acessar');
        Route::get('/buscar-produto', [\App\Http\Controllers\Venda\PDVController::class, 'buscarProduto'])->name('pdv.buscar-produto')->middleware('perm:pdv.acessar');
        Route::get('/buscar-codigo', [\App\Http\Controllers\Venda\PDVController::class, 'buscarPorCodigo'])->name('pdv.buscar-codigo')->middleware('perm:pdv.acessar');
        Route::get('/verificar-estoque', [\App\Http\Controllers\Venda\PDVController::class, 'verificarEstoque'])->name('pdv.verificar-estoque')->middleware('perm:pdv.acessar');
        Route::post('/finalizar', [\App\Http\Controllers\Venda\PDVController::class, 'finalizarVenda'])->name('pdv.finalizar')->middleware('perm:pdv.acessar');
        Route::get('/cupom/{id}', [\App\Http\Controllers\Venda\PDVController::class, 'cupom'])->name('pdv.cupom')->middleware('perm:pdv.acessar');
        Route::get('/cupom-dados/{id}', [\App\Http\Controllers\Venda\PDVController::class, 'dadosCupom'])->name('pdv.cupom-dados')->middleware('perm:pdv.acessar');
        Route::get('/historico', [\App\Http\Controllers\Venda\PDVController::class, 'historico'])->name('pdv.historico')->middleware('perm:pdv.acessar');
        Route::get('/relatorio-vendas', [\App\Http\Controllers\Venda\PDVController::class, 'relatorioVendas'])->name('pdv.relatorio-vendas')->middleware('perm:pdv.relatorio');
        Route::get('/relatorio-vendas-pdf', [\App\Http\Controllers\Venda\PDVController::class, 'relatorioVendasPdf'])->name('pdv.relatorio-vendas-pdf')->middleware('perm:pdv.relatorio');
        Route::post('/cancelar/{id}', [\App\Http\Controllers\Venda\PDVController::class, 'cancelarVenda'])->name('pdv.cancelar')->middleware('perm:pdv.cancelar-venda');
    });

    // ==============================================================
    // ROTAS PARA FORMAS DE PAGAMENTO
    // ==============================================================
    Route::prefix('formas-pagamento')->middleware('perm:financeiro.formas-pagamento')->group(function () {
        Route::get('/', [\App\Http\Controllers\Financeiro\FormaPagamentoController::class, 'index'])->name('formas-pagamento.index');
        Route::get('/create', [\App\Http\Controllers\Financeiro\FormaPagamentoController::class, 'create'])->name('formas-pagamento.create');
        Route::post('/', [\App\Http\Controllers\Financeiro\FormaPagamentoController::class, 'store'])->name('formas-pagamento.store');
        Route::get('/{id}/edit', [\App\Http\Controllers\Financeiro\FormaPagamentoController::class, 'edit'])->name('formas-pagamento.edit');
        Route::put('/{id}', [\App\Http\Controllers\Financeiro\FormaPagamentoController::class, 'update'])->name('formas-pagamento.update');
        Route::delete('/{id}', [\App\Http\Controllers\Financeiro\FormaPagamentoController::class, 'destroy'])->name('formas-pagamento.destroy');
    });

}); // Fim do grupo de rotas protegidas
