<?php

namespace App\Exceptions;

use Error;
use ErrorException;
use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\CssSelector\Exception\InternalErrorException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
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
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($e instanceof AuthenticationException || $e instanceof UnauthorizedHttpException || $e instanceof UnauthorizedException) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $e->getMessage()
            ], 401);
        }

        if ($e instanceof AccessDeniedHttpException) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $e->getMessage()
            ], 403);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof RecordsNotFoundException) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Resource has been removed.'
            ], 404);
        }

        if ($e instanceof MethodNotAllowedException) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $e->getMessage()
            ], 405);
        }

        if ($e instanceof NotAcceptableHttpException) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $e->getMessage()
            ], 406);
        }

        if ($e instanceof TokenMismatchException) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $e->getMessage()
            ], 419);
        }

        if ($e instanceof ValidationException) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        }

        if ($e instanceof UnprocessableEntityHttpException || $e instanceof HttpException) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $e->getMessage()
            ], 422);
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $e->getMessage()
            ], 429);
        }

        if ($e instanceof SuspiciousOperationException) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $e->getMessage()
            ], 475);
        }

        if ($e instanceof InternalErrorException || $e instanceof ErrorException  || $e instanceof Error || $e instanceof Exception) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
