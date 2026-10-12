<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class KeycloakAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly KeycloakTokenVerifier $verifier,
    ) {}

    public function supports(Request $request): ?bool
    {
        return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        // Verificado de verdad —firma, caducidad, emisor y cliente— antes de
        // creerse una sola línea de lo que hay dentro.
        $payload = $this->verifier->verify(substr((string) $request->headers->get('Authorization'), 7));

        $id = $payload['sub'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new AuthenticationException('Invalid JWT: missing subject claim.');
        }

        $email = $payload['email'] ?? null;
        // `given_name` y no `name`: éste añade el apellido de relleno (ver KeycloakService).
        $name  = $payload['given_name'] ?? $payload['name'] ?? null;

        return new SelfValidatingPassport(
            new UserBadge($id, fn () => new Player($id, $email, $name)),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // El login y el registro no deben fallar porque la app mande un token
        // viejo que se le quedó a mano: ahí se sigue como anónimo.
        if (str_starts_with($request->getPathInfo(), '/api/auth/') || str_starts_with($request->getPathInfo(), '/public/')) {
            return null;
        }

        return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
    }
}
