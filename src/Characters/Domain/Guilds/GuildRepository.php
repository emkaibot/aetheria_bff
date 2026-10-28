<?php

declare(strict_types=1);

namespace App\Characters\Domain\Guilds;

interface GuildRepository
{
    public function find(string $id): ?Guild;

    public function nameTaken(string $nameKey): bool;

    /** El gremio de un personaje, si está en alguno. */
    public function membershipOf(string $characterId): ?GuildMember;

    /** @return GuildMember[] del más antiguo al más nuevo */
    public function members(Guild $guild): array;

    /** @return array<int, array{guild: Guild, members: int}> los gremios con su número de miembros, del más poblado al menos */
    public function listWithCounts(int $limit): array;

    public function raid(Guild $guild, string $week): ?Raid;

    /** Ataques de un personaje a una raid desde `$since`. */
    public function attacksSince(Raid $raid, string $characterId, \DateTimeImmutable $since): int;

    /**
     * Lo que ha hecho cada uno en una raid, de más daño a menos.
     *
     * @return array<int, array{character_id: string, name: string, damage: int, attacks: int}>
     */
    public function contributions(Raid $raid): array;
}
