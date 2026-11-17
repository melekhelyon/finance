<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AuthenticateFromCookie;
use App\Http\Middleware\AdminMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            AuthenticateFromCookie::class,
        ]);
        $middleware->statefulApi();
        $middleware->redirectGuestsTo('/login');
        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response) {
            if (request()->is('api/*') && in_array($response->getStatusCode(), [401, 403, 404, 419, 429, 500, 503])) {
                $messages = [401 => 'Войдите в систему.', 403 => 'Недостаточно прав для выполнения действия.', 404 => 'Запись не найдена.', 419 => 'Время сеанса истекло. Войдите снова.', 429 => 'Слишком много запросов. Повторите попытку через минуту.', 500 => 'Не удалось выполнить запрос. Повторите попытку позже.', 503 => 'Сервис временно недоступен.'];
                $response->setContent(json_encode(['message' => $messages[$response->getStatusCode()]], JSON_UNESCAPED_UNICODE));
                $response->headers->set('Content-Type', 'application/json');
            }
            return $response;
        });
    })->create();
