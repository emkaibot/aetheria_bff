<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Controller;

use App\Characters\Domain\CharacterRepository;
use App\Security\Player;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Borra el personaje y todo su histórico. No hay papelera. */
#[Route('/api/characters/{id}', name: 'characters_delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
final class DeleteCharacterController
{
    public function __construct(
        private readonly OwnedCharacter $owned,
        private readonly CharacterRepository $characters,
    ) {}

    public function __invoke(string $id, #[CurrentUser] Player $player): Response
    {
        $this->characters->remove($this->owned->get($id, $player));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
