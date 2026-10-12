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

#[Route('/api/auth/login', name: 'auth_login', methods: ['POST'])]
final class LoginController
{
    public function __construct(private readonly KeycloakService $keycloak) {}

    public function __invoke(Request $request): Response
    {
        $data     = JsonBody::of($request);
        $email    = trim(strtolower((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return new JsonResponse(['error' => 'missing_credentials'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            return TokenResponse::from($this->keycloak->loginWithPassword($email, $password));
        } catch (ClientExceptionInterface) {
            return new JsonResponse(['error' => 'invalid_credentials'], Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'auth_unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
