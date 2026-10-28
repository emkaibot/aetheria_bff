<?php

declare(strict_types=1);

namespace App\Characters\Domain\Guilds;

use App\Characters\Domain\Character;
use Doctrine\ORM\Mapping as ORM;

/** Un personaje en un gremio. Cada personaje, en uno como mucho. */
#[ORM\Entity]
#[ORM\Table(name: 'guild_members')]
#[ORM\UniqueConstraint(name: 'uniq_guild_members_character', columns: ['character_id'])]
#[ORM\Index(name: 'idx_guild_members_guild', columns: ['guild_id', 'joined_at'])]
class GuildMember
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Guild::class)]
    #[ORM\JoinColumn(name: 'guild_id', nullable: false, onDelete: 'CASCADE')]
    private Guild $guild;

    #[ORM\ManyToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(name: 'character_id', nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\Column(name: 'is_leader', type: 'boolean', options: ['default' => false])]
    private bool $leader;

    #[ORM\Column(name: 'joined_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $joinedAt;

    public function __construct(string $id, Guild $guild, Character $character, bool $leader = false)
    {
        $this->id        = $id;
        $this->guild     = $guild;
        $this->character = $character;
        $this->leader    = $leader;
        $this->joinedAt  = new \DateTimeImmutable();
    }

    public function getGuild(): Guild                 { return $this->guild; }
    public function getCharacter(): Character         { return $this->character; }
    public function isLeader(): bool                  { return $this->leader; }
    public function getJoinedAt(): \DateTimeImmutable { return $this->joinedAt; }

    public function makeLeader(): void
    {
        $this->leader = true;
    }
}
