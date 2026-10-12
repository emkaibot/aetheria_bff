<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;

final class JsonBody
{
    /** El cuerpo como array; uno vacío o que no sea un objeto JSON cuenta como `{}`. */
    public static function of(Request $request): array
    {
        $data = json_decode($request->getContent() ?: '{}', true);

        return is_array($data) ? $data : [];
    }

    /**
     * Un campo que tiene que ser texto. Lo que venga de otro tipo (un array,
     * un número) se trata como vacío: así lo rechaza la validación del dominio
     * con un 422, en vez de reventar en «Array to string conversion».
     */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
