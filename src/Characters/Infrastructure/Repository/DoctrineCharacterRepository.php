<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Repository;

use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterHero;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\EnergyMovement;
use App\Characters\Domain\Item;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineCharacterRepository implements CharacterRepository
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function findOwned(string $id, string $ownerId): ?Character
    {
        return $this->em->getRepository(Character::class)->findOneBy(['id' => $id, 'ownerId' => $ownerId]);
    }

    public function listByOwner(string $ownerId): array
    {
        return $this->em->getRepository(Character::class)->findBy(['ownerId' => $ownerId], ['createdAt' => 'ASC']);
    }

    public function summaryForOwner(string $ownerId): array
    {
        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT COUNT(*) AS count, COALESCE(SUM(energy), 0) AS energy FROM characters WHERE owner_id = ?',
            [$ownerId],
        );

        return ['count' => (int) $row['count'], 'energy' => (int) $row['energy']];
    }

    public function save(Character $character): void
    {
        $this->em->persist($character);
        $this->em->flush();
    }

    public function remove(Character $character): void
    {
        // Los movimientos se van con él por el ON DELETE CASCADE de la FK.
        $this->em->remove($character);
        $this->em->flush();
    }

    public function movements(string $characterId, int $page, int $size): array
    {
        $items = $this->em->createQueryBuilder()
            ->select('m')
            ->from(EnergyMovement::class, 'm')
            ->where('IDENTITY(m.character) = :id')
            ->setParameter('id', $characterId)
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setFirstResult(($page - 1) * $size)
            ->setMaxResults($size)
            ->getQuery()
            ->getResult();

        $total = (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM energy_movements WHERE character_id = ?',
            [$characterId],
        );

        return ['items' => $items, 'total' => $total];
    }

    public function battleRecords(array $characterIds): array
    {
        if ($characterIds === []) {
            return [];
        }

        $rows = $this->em->getConnection()->fetchAllAssociative(
            "SELECT character_id::text AS character_id, boss_id,
                    COUNT(*) FILTER (WHERE won)     AS victories,
                    COUNT(*) FILTER (WHERE NOT won) AS defeats
               FROM battles
              WHERE character_id IN (?)
              GROUP BY character_id, boss_id",
            [$characterIds],
            [ArrayParameterType::STRING],
        );

        $records = [];
        foreach ($rows as $row) {
            $records[$row['character_id']][$row['boss_id']] = [
                'victories' => (int) $row['victories'],
                'defeats'   => (int) $row['defeats'],
            ];
        }

        return $records;
    }

    public function itemsFor(array $characterIds): array
    {
        if ($characterIds === []) {
            return [];
        }

        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT character_id::text AS character_id, item FROM character_items WHERE character_id IN (?) ORDER BY created_at',
            [$characterIds],
            [ArrayParameterType::STRING],
        );

        $items = [];
        foreach ($rows as $row) {
            $item = Item::tryFrom($row['item']);
            if ($item !== null) {
                $items[$row['character_id']][] = $item;
            }
        }

        return $items;
    }

    public function heroesFor(array $characterIds): array
    {
        if ($characterIds === []) {
            return [];
        }

        /** @var CharacterHero[] $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('h')
            ->from(CharacterHero::class, 'h')
            ->where('IDENTITY(h.character) IN (:ids)')
            ->setParameter('ids', $characterIds)
            ->orderBy('h.createdAt', 'ASC')
            ->addOrderBy('h.id', 'ASC')
            ->getQuery()
            ->getResult();

        $heroes = [];
        foreach ($rows as $row) {
            $heroes[$row->getCharacterId()][] = $row;
        }

        return $heroes;
    }
}
