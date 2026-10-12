<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Controller;

use App\Auth\Infrastructure\Service\KeycloakService;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

#[Route('/api/auth/refresh', name: 'auth_refresh', methods: ['POST'])]
final class RefreshTokenController
{
    public function __construct(private readonly KeycloakService $keycloak) {}

    public function __invoke(Request $request): Response
    {
        $refreshToken = (string) (JsonBody::of($request)['refresh_token'] ?? '');

        if ($refreshToken === '') {
            return new JsonResponse(['error' => 'missing_refresh_token'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            return TokenResponse::from($this->keycloak->refreshToken($refreshToken));
        } catch (ClientExceptionInterface) {
            // 401 = la sesión ya no vale y la app debe pedir entrar. Cualquier
            // otra cosa (503) es pasajera y no debe echar a nadie.
            return new JsonResponse(['error' => 'session_expired'], Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'auth_unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
