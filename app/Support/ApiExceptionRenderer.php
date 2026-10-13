<?php

namespace App\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns any exception thrown during an api/* request into the
 * standard error envelope from docs/api-conventions.md.
 */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        if ($exception instanceof ValidationException) {
            return ApiResponse::error('The given data was invalid.', $exception->errors(), $exception->status);
        }

        if ($exception instanceof AuthenticationException) {
            return ApiResponse::error('Unauthenticated.', status: Response::HTTP_UNAUTHORIZED);
        }

        if ($exception instanceof HttpExceptionInterface) {
            return ApiResponse::error(
                $this->messageForHttpException($exception),
                status: $exception->getStatusCode(),
            )->withHeaders($exception->getHeaders());
        }

        return ApiResponse::error(
            'Something went wrong. Please try again later.',
            config('app.debug') ? $this->debugDetails($exception) : [],
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }

    /**
     * A 404 never repeats the exception message, so a record that belongs to
     * another organization is indistinguishable from one that does not exist.
     */
    private function messageForHttpException(HttpExceptionInterface $exception): string
    {
        $statusCode = $exception->getStatusCode();

        if ($statusCode === Response::HTTP_NOT_FOUND) {
            return 'Resource not found.';
        }

        $message = $exception->getMessage() ?: (Response::$statusTexts[$statusCode] ?? 'Error');

        return str_ends_with($message, '.') ? $message : $message.'.';
    }

    /**
     * @return array{exception: class-string<Throwable>, message: string, file: string, line: int}
     */
    private function debugDetails(Throwable $exception): array
    {
        return [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
    }
}
