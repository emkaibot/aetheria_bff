<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Command;

use App\Characters\Domain\Battle\BattleSimulator;
use App\Characters\Domain\Battle\Benchmark;
use App\Characters\Domain\Battle\Boss;
use App\Characters\Domain\Battle\Rng;
use App\Characters\Domain\CharacterClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Enfrenta a cada clase de líder, con el equipo típico de ese momento, a
 * cada jefe, dos niveles por debajo, al recomendado y dos por encima.
 *
 * El equipo típico (Benchmark) es lo que se suele tener al llegar a un jefe
 * sin comprar nada. Con `--calibrate` busca,
 * para cada jefe, la `Boss::partyScale` con la que ese equipo gana más o
 * menos la mitad de las veces al nivel recomendado.
 */
#[AsCommand(name: 'eter:balance', description: 'Simula combates para equilibrar clases y jefes')]
final class BalanceCommand extends Command
{
    /** Lo que se busca al calibrar: algo más de la mitad de victorias. */
    private const TARGET = 0.55;

    protected function configure(): void
    {
        $this
            ->addOption('fights', 'f', InputOption::VALUE_REQUIRED, 'Combates por casilla', '300')
            ->addOption('calibrate', null, InputOption::VALUE_NONE, 'Busca la partyScale de cada jefe');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $fights = max(1, (int) $input->getOption('fights'));

        $scales = [];
        if ($input->getOption('calibrate')) {
            $rows = [];
            foreach (Boss::cases() as $boss) {
                $scales[$boss->value] = $this->calibrate($boss, max(40, intdiv($fights, 3)));
                $rows[] = [sprintf('%s %s', $boss->emoji(), $boss->name()), $boss->partyScale(), $scales[$boss->value]];
            }
            $io->section('partyScale');
            $io->table(['Jefe', 'Ahora', 'Calibrada'], $rows);
        }

        foreach ([-2, 0, 2] as $offset) {
            $io->section(sprintf('Nivel recomendado %+d%s', $offset, $scales !== [] ? ' (con la calibrada)' : ''));
            $rows = [];

            foreach (Boss::cases() as $boss) {
                $level = max(1, $boss->recommendedLevel() + $offset);
                $row   = [sprintf('%s %s', $boss->emoji(), $boss->name()), $level, count(Benchmark::party($boss)), count(Benchmark::pets($boss, 1)), count(Benchmark::slots($boss))];

                foreach (CharacterClass::cases() as $class) {
                    $row[] = sprintf('%3d%%', (int) round(100 * $this->winRate($class, $boss, $level, $fights, $scales[$boss->value] ?? null)));
                }

                $rows[] = $row;
            }

            $io->table(
                ['Jefe', 'Nivel', 'Héroes', 'Mascotas', 'Ranuras', ...array_map(fn (CharacterClass $c) => $c->label(), CharacterClass::cases())],
                $rows,
            );
        }

        return Command::SUCCESS;
    }

    /** Búsqueda binaria de la escala con la que el equipo típico gana TARGET al nivel recomendado (media de clases). */
    private function calibrate(Boss $boss, int $fights): float
    {
        [$lo, $hi] = [0.0, 4.0];
        for ($i = 0; $i < 12; $i++) {
            $mid  = ($lo + $hi) / 2;
            $rate = 0.0;
            foreach (CharacterClass::cases() as $class) {
                $rate += $this->winRate($class, $boss, $boss->recommendedLevel(), $fights, $mid);
            }
            $rate / count(CharacterClass::cases()) > self::TARGET ? $lo = $mid : $hi = $mid;
        }

        return round(($lo + $hi) / 2, 2);
    }

    private function winRate(CharacterClass $class, Boss $boss, int $level, int $fights, ?float $scale): float
    {
        $team = Benchmark::team($class, $boss, $level);
        $pets = Benchmark::pets($boss, $level);
        $wins = 0;
        for ($i = 1; $i <= $fights; $i++) {
            $wins += (new BattleSimulator(new Rng($i * 7919)))->run($team, $boss, $pets, partyScale: $scale)->won ? 1 : 0;
        }

        return $wins / $fights;
    }
}
