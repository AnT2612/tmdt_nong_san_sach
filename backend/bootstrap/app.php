<?php

use App\Exceptions\InsufficientStockException;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\OptionalJwtAuth;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->alias([
            'role'         => EnsureUserHasRole::class,
            'jwt.optional' => OptionalJwtAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof TokenMismatchException) {
                return response()->json([
                    'status'     => 'error',
                    'success'    => false,
                    'error_code' => 'CSRF_EXPIRED',
                    'message'    => 'Phiên làm việc đã hết hạn.',
                ], 419);
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'status'  => 'error',
                    'success' => false,
                    'message' => 'Dữ liệu đầu vào không hợp lệ.',
                    'errors'  => $e->errors(),
                ], 422);
            }

            if ($e instanceof InsufficientStockException) {
                return response()->json([
                    'status'  => 'error',
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            if ($e instanceof AuthenticationException || $e instanceof UnauthorizedHttpException) {
                return response()->json([
                    'status'  => 'error',
                    'success' => false,
                    'message' => 'Bạn cần đăng nhập để thực hiện thao tác này.',
                ], 401);
            }

            if ($e instanceof AccessDeniedHttpException) {
                return response()->json([
                    'status'  => 'error',
                    'success' => false,
                    'message' => 'Bạn không có quyền thực hiện thao tác này.',
                ], 403);
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return response()->json([
                    'status'  => 'error',
                    'success' => false,
                    'message' => 'Không tìm thấy tài nguyên yêu cầu.',
                ], 404);
            }

            return response()->json([
                'status'  => 'error',
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Lỗi hệ thống máy chủ nội bộ.',
            ], 500);
        });
    })->create();

if (PHP_VERSION_ID >= 80600) {
    $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\HandleExceptions::class, function () {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    });
}

return $app;
