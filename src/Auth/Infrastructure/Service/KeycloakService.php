<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Service;

use App\Auth\Domain\EmailAlreadyRegistered;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Lo que el BFF le pide a Keycloak. La app nunca habla con Keycloak: manda
 * correo y contraseña aquí, y aquí se canjean por tokens (Direct Access Grant)
 * con el secreto del cliente, que así no viaja dentro del binario.
 */
class KeycloakService
{
    private string $tokenUrl;
    private string $adminTokenUrl;
    private string $usersUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $keycloakUrl,
        private readonly string $keycloakRealm,
        private readonly string $keycloakClientId,
        private readonly string $keycloakClientSecret,
        private readonly string $keycloakAdminUser,
        private readonly string $keycloakAdminPassword,
    ) {
        $this->tokenUrl      = sprintf('%s/realms/%s/protocol/openid-connect/token', $keycloakUrl, $keycloakRealm);
        $this->adminTokenUrl = sprintf('%s/realms/master/protocol/openid-connect/token', $keycloakUrl);
        $this->usersUrl      = sprintf('%s/admin/realms/%s/users', $keycloakUrl, $keycloakRealm);
    }

    /** @return array{access_token: string, refresh_token: string, expires_in: int, token_type?: string} */
    public function loginWithPassword(string $username, string $password): array
    {
        return $this->httpClient->request('POST', $this->tokenUrl, [
            'body' => [
                'grant_type'    => 'password',
                'client_id'     => $this->keycloakClientId,
                'client_secret' => $this->keycloakClientSecret,
                'username'      => $username,
                'password'      => $password,
                'scope'         => 'openid profile email',
            ],
        ])->toArray();
    }

    /** @return array{access_token: string, refresh_token: string, expires_in: int, token_type?: string} */
    public function refreshToken(string $refreshToken): array
    {
        return $this->httpClient->request('POST', $this->tokenUrl, [
            'body' => [
                'grant_type'    => 'refresh_token',
                'client_id'     => $this->keycloakClientId,
                'client_secret' => $this->keycloakClientSecret,
                'refresh_token' => $refreshToken,
            ],
        ])->toArray();
    }

    public function revokeToken(string $refreshToken): void
    {
        $this->httpClient->request('POST', sprintf('%s/realms/%s/protocol/openid-connect/logout', $this->keycloakUrl, $this->keycloakRealm), [
            'body' => [
                'client_id'     => $this->keycloakClientId,
                'client_secret' => $this->keycloakClientSecret,
                'refresh_token' => $refreshToken,
            ],
        ])->getStatusCode();
    }

    /**
     * Crea la cuenta **sin contraseña**: la elige su dueño desde el correo que
     * manda `sendSetPasswordEmail`. No hay registro público —ni en la app ni en
     * el realm—; las cuentas se dan de alta con `eter:account:create`.
     *
     * Si el correo ya tiene cuenta devuelve su id con `created: false`, para
     * poder reenviar la invitación sin tratarlo como un error.
     *
     * @return array{id: string, created: bool}
     */
    public function createUserPendingPassword(string $email, string $displayName): array
    {
        $adminToken = $this->getAdminToken();

        $existing = $this->findUserIdByEmail($email, $adminToken);
        if ($existing !== null) {
            return ['id' => $existing, 'created' => false];
        }

        $response = $this->httpClient->request('POST', $this->usersUrl, [
            'headers' => ['Authorization' => 'Bearer ' . $adminToken],
            'json'    => [
                'username'        => $email,
                'email'           => $email,
                'firstName'       => $displayName,
                // Keycloak 26 pide apellido en el perfil por defecto y, sin él,
                // el primer login falla con «Account is not fully set up». El
                // juego sólo usa el nombre; éste no se enseña en ningún sitio.
                'lastName'        => 'Jugador',
                'enabled'         => true,
                // Quien abre el enlace del correo ya ha demostrado que es suyo.
                'emailVerified'   => true,
                'requiredActions' => ['UPDATE_PASSWORD'],
            ],
        ]);

        if ($response->getStatusCode() === 409) {
            throw new EmailAlreadyRegistered($email);
        }
        if ($response->getStatusCode() !== 201) {
            $body = $response->toArray(throw: false);
            throw new \RuntimeException($body['errorMessage'] ?? 'Failed to create user in Keycloak.');
        }

        $userId = basename($response->getHeaders()['location'][0] ?? '');
        if ($userId === '') {
            throw new \RuntimeException('Keycloak did not return the new user id.');
        }

        return ['id' => $userId, 'created' => true];
    }

    /**
     * Manda el correo para poner (o cambiar) la contraseña. Lo envía **Keycloak**
     * con uno de sus *action tokens*: de un solo uso, caduca y la contraseña no
     * pasa nunca por el BFF.
     *
     * @param int $lifespanSeconds cuánto vale el enlace
     */
    public function sendSetPasswordEmail(string $userId, int $lifespanSeconds): void
    {
        $response = $this->httpClient->request('PUT', sprintf('%s/%s/execute-actions-email', $this->usersUrl, $userId), [
            'headers' => ['Authorization' => 'Bearer ' . $this->getAdminToken()],
            'query'   => ['lifespan' => $lifespanSeconds],
            'json'    => ['UPDATE_PASSWORD'],
        ]);

        if (!in_array($response->getStatusCode(), [200, 204], true)) {
            $body = $response->toArray(throw: false);
            throw new \RuntimeException(sprintf(
                'Keycloak no pudo mandar el correo (%d): %s',
                $response->getStatusCode(),
                $body['errorMessage'] ?? $body['error'] ?? 'sin detalle',
            ));
        }
    }

    private function findUserIdByEmail(string $email, string $adminToken): ?string
    {
        $users = $this->httpClient->request('GET', $this->usersUrl, [
            'headers' => ['Authorization' => 'Bearer ' . $adminToken],
            'query'   => ['email' => $email, 'exact' => 'true'],
        ])->toArray(throw: false);

        return $users[0]['id'] ?? null;
    }

    private function getAdminToken(): string
    {
        return $this->httpClient->request('POST', $this->adminTokenUrl, [
            'body' => [
                'grant_type' => 'password',
                'client_id'  => 'admin-cli',
                'username'   => $this->keycloakAdminUser,
                'password'   => $this->keycloakAdminPassword,
            ],
        ])->toArray()['access_token'];
    }
}
