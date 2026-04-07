<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('login') && $request->isMethod('POST') && ! $request->expectsJson()) {
                return redirect()
                    ->route('login')
                    ->withErrors(['account' => '登录请求过于频繁，请稍后再试。'])
                    ->withInput($request->only('account'));
            }

            return null;
        });
    }
}
