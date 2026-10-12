<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * La cuenta que juega. Su id es el `sub` de Keycloak y no hay tabla local de
 * usuarios: todo lo que se necesita de la cuenta viene en el token, y los
 * personajes cuelgan de ese id directamente.
 */
final class Player implements UserInterface
{
    public function __construct(
        private readonly string $id,
        private readonly ?string $email,
        private readonly ?string $name = null,
    ) {}

    public function getId(): string      { return $this->id; }
    public function getEmail(): ?string  { return $this->email; }
    public function getName(): ?string   { return $this->name; }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void {}

    public function getUserIdentifier(): string
    {
        return $this->id;
    }
}
