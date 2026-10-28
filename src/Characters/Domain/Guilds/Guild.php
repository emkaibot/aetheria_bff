<?php

declare(strict_types=1);

namespace App\Characters\Domain\Guilds;

use App\Characters\Domain\InvalidGuild;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un gremio: personajes (no cuentas) que hacen juntos la raid de la semana.
 * Abierto: cualquiera puede entrar mientras quepa.
 */
#[ORM\Entity]
#[ORM\Table(name: 'guilds')]
#[ORM\UniqueConstraint(name: 'uniq_guilds_name_key', columns: ['name_key'])]
class Guild
{
    public const NAME_MIN = 3;
    public const NAME_MAX = 24;
    public const MAX_MEMBERS = 20;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(type: 'string', length: self::NAME_MAX)]
    private string $name;

    /** El nombre en minúsculas y sin tildes: dos gremios no pueden llamarse igual. */
    #[ORM\Column(name: 'name_key', type: 'string', length: self::NAME_MAX)]
    private string $nameKey;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, string $name)
    {
        $this->id        = $id;
        $this->name      = self::validName($name);
        $this->nameKey   = self::key($this->name);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string                    { return $this->id; }
    public function getName(): string                  { return $this->name; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public static function key(string $name): string
    {
        $plain = iconv('UTF-8', 'ASCII//TRANSLIT', $name);

        return mb_strtolower($plain === false ? $name : $plain);
    }

    private static function validName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $len  = mb_strlen($name);
        if ($len < self::NAME_MIN || $len > self::NAME_MAX) {
            throw new InvalidGuild(sprintf('El nombre del gremio debe tener entre %d y %d caracteres.', self::NAME_MIN, self::NAME_MAX));
        }

        return $name;
    }
}
