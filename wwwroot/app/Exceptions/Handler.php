<?php

namespace App\Exceptions;

use Throwable;
use App\Traits\ResponseTraits;
use hg\apidoc\exception\HttpException;
use hg\apidoc\exception\ErrorException;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\AuthenticationException;
use App\Services\SystemNotice\SystemNoticeService;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    use ResponseTraits;

    protected $levels = [];

    protected $dontReport = [
        ErrorException::class,
        HttpException::class,
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register()
    {
        $this->reportable(function (Throwable $e) {
            if ($this->shouldSkipSystemNotice($e)) {
                return;
            }

            $this->sendSystemNotice($e);
        });

        $this->renderable(function (HttpException $e) {
            return abort($e->getStatusCode(), $e->getMessage());
        });
    }

    protected function shouldSkipSystemNotice(Throwable $e): bool
    {
        return !$this->shouldReport($e) || $e instanceof ErrorException || $e instanceof HttpException;
    }

    protected function buildExceptionContext(Throwable $e): array
    {
        $request = request();

        return [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'ip' => $this->clientIp(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => optional($request->route())->getName(),
            'trace' => $this->formatExceptionTrace($e),
        ];
    }

    protected function formatExceptionTrace(Throwable $e): array
    {
        return collect($e->getTrace())->take(3)->map(function (array $trace) {
            return [
                'file' => $trace['file'] ?? '',
                'line' => $trace['line'] ?? '',
                'class' => $trace['class'] ?? '',
                'function' => $trace['function'] ?? '',
            ];
        })->values()->all();
    }

    protected function sendSystemNotice(Throwable $e): void
    {
        try {
            app(SystemNoticeService::class)->warning('system_manual_notice', $this->buildExceptionContext($e));
        } catch (Throwable $noticeException) {
            Log::error('system_notice_send_failed', [
                'exception_type' => get_class($noticeException),
                'exception_message' => $noticeException->getMessage(),
                'origin_type' => get_class($e),
                'origin_message' => $e->getMessage(),
            ]);
        }
    }

    protected function clientIp(): string
    {
        try {
            return (string)bob_ip();
        } catch (Throwable $e) {
            return (string)request()->ip();
        }
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->is('api/*')) {
            return $this->result(-1, '未登录');
        }

        return redirect()->guest(route('login'));
    }
}
