<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/** Lo que la app guarda de una sesión: igual al entrar, al registrarse y al renovar. */
final class TokenResponse
{
    public static function from(array $tokens, int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in'    => $tokens['expires_in'],
            'token_type'    => $tokens['token_type'] ?? 'Bearer',
        ], $status);
    }
}
