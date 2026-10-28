<?php

declare(strict_types=1);

namespace App\Characters\Domain;

use App\Shared\Domain\DomainError;

final class NotEnoughSeals extends \DomainException implements DomainError
{
    public function __construct(int $have, int $need)
    {
        parent::__construct(sprintf('Te faltan %d sellos de incursión (tienes %d).', $need - $have, $have));
    }

    public function code(): string
    {
        return 'not_enough_seals';
    }
}
