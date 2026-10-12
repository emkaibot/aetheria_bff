<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterRepository;
use App\Security\Player;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Carga un personaje **de la cuenta de la sesión**. El de otra cuenta da 404 y
 * no 403: decir «existe pero no es tuyo» ya es contar algo que no toca.
 */
final class OwnedCharacter
{
    public function __construct(private readonly CharacterRepository $characters) {}

    public function get(string $id, Player $player): Character
    {
        return $this->characters->findOwned($id, $player->getId())
            ?? throw new NotFoundHttpException('character_not_found');
    }
}
