<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Controller;

use App\Auth\Infrastructure\Service\KeycloakService;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth/logout', name: 'auth_logout', methods: ['POST'])]
final class LogoutController
{
    public function __construct(private readonly KeycloakService $keycloak) {}

    public function __invoke(Request $request): Response
    {
        $refreshToken = (string) (JsonBody::of($request)['refresh_token'] ?? '');

        if ($refreshToken !== '') {
            try {
                $this->keycloak->revokeToken($refreshToken);
            } catch (\Throwable) {
                // Salir funciona aunque Keycloak no conteste: la app tira los
                // tokens igual, y el de refresco caduca solo.
            }
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
