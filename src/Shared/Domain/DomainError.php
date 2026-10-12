<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Un error de reglas del juego: la API lo devuelve como 422 con `{error, message}`.
 * `error` es un código estable para la app; `message`, el texto para el usuario.
 */
interface DomainError extends \Throwable
{
    public function code(): string;
}
