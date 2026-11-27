<?php

namespace App\Providers;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (PHP_VERSION_ID >= 80600) {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (PHP_VERSION_ID >= 80600) {
            $buildCleanHandler = static fn () => new class implements \SessionHandlerInterface, \SessionIdInterface, \SessionUpdateTimestampHandlerInterface {
                private array $store = [];

                public function open(string $path, string $name): bool { return true; }
                public function close(): bool { return true; }
                public function read(string $id): string|false { return $this->store[$id] ?? ''; }
                public function write(string $id, string $data): bool { $this->store[$id] = $data; return true; }
                public function destroy(string $id): bool { unset($this->store[$id]); return true; }
                public function gc(int $max_lifetime): int|false { return 0; }
                public function create_sid(): string { return bin2hex(random_bytes(16)); }
                public function validateId(string $id): bool { return $id !== ''; }
                public function updateTimestamp(string $id, string $data): bool { return true; }
            };

            foreach (['array', 'database', 'file'] as $driver) {
                Session::extend($driver, $buildCleanHandler);
            }
        }
    }
}
