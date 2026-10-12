<?php

declare(strict_types=1);

namespace App\Auth\Domain;

/** El correo ya tiene cuenta. No es una avería: es la respuesta a lo pedido. */
final class EmailAlreadyRegistered extends \RuntimeException
{
    public function __construct(public readonly string $email)
    {
        parent::__construct(sprintf('Email already registered: %s', $email));
    }
}
