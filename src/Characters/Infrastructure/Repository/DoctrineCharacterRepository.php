<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Repository;

use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterHero;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\EnergyMovement;
use App\Characters\Domain\Expeditions\Expedition;
use App\Characters\Domain\Gear\GearItem;
use App\Characters\Domain\Pets\CharacterPet;
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

    public function heroesFor(array $characterIds): array
    {
        return $this->grouped(CharacterHero::class, $characterIds, 'ASC');
    }

    public function gearFor(array $characterIds): array
    {
        return $this->grouped(GearItem::class, $characterIds, 'DESC');
    }

    public function findGear(string $characterId, string $itemId): ?GearItem
    {
        $item = $this->em->find(GearItem::class, $itemId);

        return $item !== null && $item->getCharacterId() === $characterId ? $item : null;
    }

    public function petsFor(array $characterIds): array
    {
        return $this->grouped(CharacterPet::class, $characterIds, 'ASC');
    }

    public function activeExpedition(string $characterId): ?Expedition
    {
        return $this->em->createQueryBuilder()
            ->select('e')
            ->from(Expedition::class, 'e')
            ->where('IDENTITY(e.character) = :id')
            ->andWhere('e.claimedAt IS NULL')
            ->setParameter('id', $characterId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Filas de una tabla con `character` y `createdAt`, agrupadas por personaje.
     *
     * @template T of object
     * @param  class-string<T> $class
     * @param  string[]        $characterIds
     * @return array<string, T[]>
     */
    private function grouped(string $class, array $characterIds, string $order): array
    {
        if ($characterIds === []) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('x')
            ->from($class, 'x')
            ->where('IDENTITY(x.character) IN (:ids)')
            ->setParameter('ids', $characterIds)
            ->orderBy('x.createdAt', $order)
            ->addOrderBy('x.id', $order)
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->getCharacterId()][] = $row;
        }

        return $out;
    }
}
