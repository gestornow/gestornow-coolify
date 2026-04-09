<?php

namespace App\Providers;

use App\Facades\Perm;
use App\Services\Cache\SafeFileStore;
use Illuminate\Support\ServiceProvider;
use App\Domain\Auth\Services\RegistroService;
use App\Domain\Auth\Repositories\EmpresaRepository;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Auth\Models\Empresa;
use App\Domain\Auth\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    $this->app->afterResolving('cache', function ($manager) {
      $manager->extend('safe_file', function ($app, array $config) {
        return $this->repository(
          (new SafeFileStore($app['files'], $config['path'], $config['permission'] ?? null))
            ->setLockDirectory($config['lock_path'] ?? null),
          $config
        );
      });
    });

    // Registrar repositórios
    $this->app->bind(EmpresaRepository::class, function ($app) {
        return new EmpresaRepository(new Empresa());
    });

    $this->app->bind(UsuarioRepository::class, function ($app) {
        return new UsuarioRepository(new Usuario());
    });

    // Registrar serviços
    $this->app->bind(RegistroService::class, function ($app) {
        return new RegistroService(
            $app->make(EmpresaRepository::class),
            $app->make(UsuarioRepository::class)
        );
    });
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    $this->ensureRuntimeDirectories();

    Blade::if('pode', function (string $chave) {
      $usuario = Auth::user();

      return $usuario ? Perm::pode($usuario, $chave) : false;
    });

    Blade::if('naopode', function (string $chave) {
      $usuario = Auth::user();

      return $usuario ? !Perm::pode($usuario, $chave) : true;
    });
  }

  /**
   * Garante a existência dos diretórios de runtime usados por sessão/cache/views/logs.
   */
  private function ensureRuntimeDirectories(): void
  {
    $directories = [
      storage_path('framework/cache/data'),
      storage_path('framework/sessions'),
      storage_path('framework/views'),
      storage_path('logs'),
      base_path('bootstrap/cache'),
    ];

    foreach ($directories as $directory) {
      if (is_dir($directory)) {
        continue;
      }

      @mkdir($directory, 0775, true);

      if (!is_dir($directory)) {
        Log::error('Nao foi possivel criar diretorio de runtime', [
          'path' => $directory,
        ]);
      }
    }
  }
}
