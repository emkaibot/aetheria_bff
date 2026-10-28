<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Repository;

use App\Characters\Domain\Guilds\Guild;
use App\Characters\Domain\Guilds\GuildMember;
use App\Characters\Domain\Guilds\GuildRepository;
use App\Characters\Domain\Guilds\Raid;
use App\Characters\Domain\Guilds\RaidAttack;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineGuildRepository implements GuildRepository
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function find(string $id): ?Guild
    {
        return $this->em->find(Guild::class, $id);
    }

    public function nameTaken(string $nameKey): bool
    {
        return $this->em->getRepository(Guild::class)->count(['nameKey' => $nameKey]) > 0;
    }

    public function membershipOf(string $characterId): ?GuildMember
    {
        return $this->em->createQueryBuilder()
            ->select('m', 'g')
            ->from(GuildMember::class, 'm')
            ->join('m.guild', 'g')
            ->where('IDENTITY(m.character) = :id')
            ->setParameter('id', $characterId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function members(Guild $guild): array
    {
        return $this->em->createQueryBuilder()
            ->select('m', 'c')
            ->from(GuildMember::class, 'm')
            ->join('m.character', 'c')
            ->where('m.guild = :g')
            ->setParameter('g', $guild)
            ->orderBy('m.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function listWithCounts(int $limit): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('g', 'COUNT(m.id) AS members')
            ->from(Guild::class, 'g')
            ->leftJoin(GuildMember::class, 'm', 'WITH', 'm.guild = g')
            ->groupBy('g.id')
            ->orderBy('members', 'DESC')
            ->addOrderBy('g.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(fn (array $r) => ['guild' => $r[0], 'members' => (int) $r['members']], $rows);
    }

    public function raid(Guild $guild, string $week): ?Raid
    {
        return $this->em->getRepository(Raid::class)->findOneBy(['guild' => $guild, 'week' => $week]);
    }

    public function attacksSince(Raid $raid, string $characterId, \DateTimeImmutable $since): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(RaidAttack::class, 'a')
            ->where('a.raid = :r')
            ->andWhere('IDENTITY(a.character) = :c')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('r', $raid)
            ->setParameter('c', $characterId)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function contributions(Raid $raid): array
    {
        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT c.id::text AS character_id, c.name, SUM(a.damage) AS damage, COUNT(*) AS attacks
               FROM guild_raid_attacks a
               JOIN characters c ON c.id = a.character_id
              WHERE a.raid_id = ?
              GROUP BY c.id, c.name
              ORDER BY damage DESC',
            [$raid->getId()],
        );

        return array_map(fn (array $r) => [
            'character_id' => $r['character_id'],
            'name'         => $r['name'],
            'damage'       => (int) $r['damage'],
            'attacks'      => (int) $r['attacks'],
        ], $rows);
    }
}
