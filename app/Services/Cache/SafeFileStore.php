<?php

namespace App\Services\Cache;

use Illuminate\Cache\FileStore;
use Throwable;

class SafeFileStore extends FileStore
{
    /**
     * Recria o diretório e tenta novamente quando o arquivo some durante a escrita.
     */
    public function put($key, $value, $seconds)
    {
        $path = $this->path($key);

        try {
            $this->ensureCacheDirectoryExists($path);

            $result = $this->files->put(
                $path, $this->expiration($seconds).serialize($value), true
            );
        } catch (Throwable $exception) {
            clearstatcache(true, $path);
            $this->ensureCacheDirectoryExists($path);

            try {
                $result = $this->files->put(
                    $path, $this->expiration($seconds).serialize($value), true
                );
            } catch (Throwable $retryException) {
                return false;
            }
        }

        if (($result === false || $result === 0) && ! is_file($path)) {
            clearstatcache(true, $path);
            $this->ensureCacheDirectoryExists($path);

            try {
                $result = $this->files->put(
                    $path, $this->expiration($seconds).serialize($value), true
                );
            } catch (Throwable $retryException) {
                return false;
            }
        }

        if ($result !== false && $result > 0) {
            $this->ensurePermissionsAreCorrect($path);

            return true;
        }

        return false;
    }

    /**
     * Evita LockableFile para que o throttle nao derrube a requisicao.
     */
    public function add($key, $value, $seconds)
    {
        $path = $this->path($key);

        try {
            $this->ensureCacheDirectoryExists($path);

            $payload = $this->getPayload($key);

            if (! is_null($payload['time'])) {
                return false;
            }

            return $this->put($key, $value, $seconds);
        } catch (Throwable $exception) {
            clearstatcache(true, $path);

            return false;
        }
    }

    /**
     * Trata sumico transitório do arquivo de cache como cache miss.
     */
    protected function getPayload($key)
    {
        $path = $this->path($key);

        try {
            if (! is_file($path)) {
                return $this->emptyPayload();
            }

            $contents = $this->files->get($path, true);

            if (is_null($contents) || $contents === '') {
                return $this->emptyPayload();
            }

            $expire = substr($contents, 0, 10);
        } catch (Throwable $exception) {
            clearstatcache(true, $path);

            if (! is_file($path)) {
                return $this->emptyPayload();
            }

            throw $exception;
        }

        if ($this->currentTime() >= (int) $expire) {
            $this->forget($key);

            return $this->emptyPayload();
        }

        try {
            $data = unserialize(substr($contents, 10));
        } catch (Throwable $exception) {
            $this->forget($key);

            return $this->emptyPayload();
        }

        $time = (int) $expire - $this->currentTime();

        return compact('data', 'time');
    }
}