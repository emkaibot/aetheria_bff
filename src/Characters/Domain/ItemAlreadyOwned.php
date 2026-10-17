<?php

declare(strict_types=1);

namespace App\Characters\Domain;

use App\Shared\Domain\DomainError;

final class ItemAlreadyOwned extends \DomainException implements DomainError
{
    public function __construct(Item $item)
    {
        parent::__construct(sprintf('Este héroe ya tiene %s.', $item->label()));
    }

    public function code(): string
    {
        return 'item_owned';
    }
}
