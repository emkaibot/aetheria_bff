<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Characters\Domain\Character;
use App\Characters\Domain\Guilds\Guild;
use App\Characters\Domain\Guilds\GuildMember;
use App\Characters\Domain\Guilds\GuildRepository;
use App\Characters\Domain\InvalidGuild;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/** Entrar y salir de los gremios. Un personaje está como mucho en uno. */
final class Guilds
{
    public function __construct(
        private readonly GuildRepository $guilds,
        private readonly EntityManagerInterface $em,
    ) {}

    public function create(Character $c, string $name): Guild
    {
        return $this->em->wrapInTransaction(function () use ($c, $name): Guild {
            $this->notInAGuild($c);
            $guild = new Guild(Uuid::v7()->toRfc4122(), $name);
            if ($this->guilds->nameTaken(Guild::key($guild->getName()))) {
                throw new InvalidGuild('Ya hay un gremio con ese nombre.');
            }
            $this->em->persist($guild);
            $this->em->persist(new GuildMember(Uuid::v7()->toRfc4122(), $guild, $c, leader: true));

            return $guild;
        });
    }

    public function join(Character $c, Guild $guild): void
    {
        $this->em->wrapInTransaction(function () use ($c, $guild): void {
            $this->notInAGuild($c);
            if (count($this->guilds->members($guild)) >= Guild::MAX_MEMBERS) {
                throw new InvalidGuild(sprintf('%s está lleno.', $guild->getName()));
            }
            $this->em->persist(new GuildMember(Uuid::v7()->toRfc4122(), $guild, $c));
        });
    }

    /**
     * Salir. Si se va el líder, lo es el miembro más antiguo; si no queda
     * nadie, el gremio desaparece (y su raid con él).
     */
    public function leave(Character $c): void
    {
        $this->em->wrapInTransaction(function () use ($c): void {
            $member = $this->guilds->membershipOf($c->getId()) ?? throw new InvalidGuild('No estás en ningún gremio.');
            $guild  = $member->getGuild();
            $this->em->remove($member);
            $this->em->flush();

            $rest = $this->guilds->members($guild);
            if ($rest === []) {
                $this->em->remove($guild);
            } elseif ($member->isLeader()) {
                $rest[0]->makeLeader();
            }
        });
    }

    private function notInAGuild(Character $c): void
    {
        if ($this->guilds->membershipOf($c->getId()) !== null) {
            throw new InvalidGuild('Ya estás en un gremio: sal de él antes.');
        }
    }
}
