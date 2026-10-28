<?php

declare(strict_types=1);

namespace App\Characters\Domain;

interface CharacterRepository
{
    /** El personaje si es de esa cuenta; si no existe o es de otra, null (y la API da 404). */
    public function findOwned(string $id, string $ownerId): ?Character;

    /** @return Character[] del más antiguo al más nuevo */
    public function listByOwner(string $ownerId): array;

    /** @return array{count: int, energy: int} */
    public function summaryForOwner(string $ownerId): array;

    public function save(Character $character): void;

    public function remove(Character $character): void;

    /** @return array{items: EnergyMovement[], total: int} el más reciente primero */
    public function movements(string $characterId, int $page, int $size): array;

    /**
     * Victorias y derrotas por jefe.
     *
     * @param  string[] $characterIds
     * @return array<string, array<string, array{victories: int, defeats: int}>> characterId => bossId => récord
     */
    public function battleRecords(array $characterIds): array;

    /**
     * El equipo de cada héroe.
     *
     * @param  string[] $characterIds
     * @return array<string, Item[]> characterId => objetos
     */
    public function itemsFor(array $characterIds): array;

    /**
     * Los héroes de cada personaje, del primero que consiguió al último.
     *
     * @param  string[] $characterIds
     * @return array<string, CharacterHero[]> characterId => héroes
     */
    public function heroesFor(array $characterIds): array;
}
