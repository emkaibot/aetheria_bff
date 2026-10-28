<?php

declare(strict_types=1);

namespace App\Characters\Domain;

use App\Characters\Domain\Expeditions\Expedition;
use App\Characters\Domain\Gear\GearItem;
use App\Characters\Domain\Pets\CharacterPet;

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
     * Los héroes de cada personaje, del primero que consiguió al último.
     *
     * @param  string[] $characterIds
     * @return array<string, CharacterHero[]> characterId => héroes
     */
    public function heroesFor(array $characterIds): array;

    /**
     * El inventario de cada personaje, del más nuevo al más viejo.
     *
     * @param  string[] $characterIds
     * @return array<string, GearItem[]>
     */
    public function gearFor(array $characterIds): array;

    /** Un objeto del inventario de ese personaje, o null si no es suyo. */
    public function findGear(string $characterId, string $itemId): ?GearItem;

    /**
     * Las mascotas de cada personaje, de la primera a la última.
     *
     * @param  string[] $characterIds
     * @return array<string, CharacterPet[]>
     */
    public function petsFor(array $characterIds): array;

    /** La expedición en marcha (sin recoger) del personaje, si la hay. */
    public function activeExpedition(string $characterId): ?Expedition;
}
