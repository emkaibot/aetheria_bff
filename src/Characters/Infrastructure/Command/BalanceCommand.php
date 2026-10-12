<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Command;

use App\Characters\Domain\Battle\BattleSimulator;
use App\Characters\Domain\Battle\Boss;
use App\Characters\Domain\Battle\Rng;
use App\Characters\Domain\CharacterClass;
use App\Characters\Domain\Progression;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cuánto gana cada clase contra cada jefe, a su nivel recomendado y a ±2.
 *
 * Es la herramienta para tocar números: al nivel recomendado la pelea debería
 * rondar el 50 %, dos niveles por debajo ser difícil y dos por encima, cómoda.
 * Usa semillas fijas, así que dos ejecuciones dan lo mismo.
 */
#[AsCommand(name: 'eter:balance', description: 'Simula combates para equilibrar clases y jefes')]
final class BalanceCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('fights', 'f', InputOption::VALUE_REQUIRED, 'Combates por casilla', '300');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $fights = max(1, (int) $input->getOption('fights'));

        foreach ([-2, 0, 2] as $offset) {
            $io->section(sprintf('Nivel recomendado %+d', $offset));
            $rows = [];

            foreach (Boss::cases() as $boss) {
                $level  = max(1, $boss->recommendedLevel() + $offset);
                $energy = Progression::energyForLevel($level);
                $row    = [sprintf('%s %s', $boss->emoji(), $boss->name()), $level, $energy];

                foreach (CharacterClass::cases() as $class) {
                    $wins = 0;
                    for ($i = 1; $i <= $fights; $i++) {
                        $sim = new BattleSimulator(new Rng($i * 7919));
                        $wins += $sim->simulate('Test', $class, $energy, $boss)->won ? 1 : 0;
                    }
                    $row[]  = sprintf('%3d%%', (int) round($wins * 100 / $fights));
                }

                $rows[] = $row;
            }

            $io->table(
                ['Jefe', 'Nivel', 'Éter', ...array_map(fn (CharacterClass $c) => $c->label(), CharacterClass::cases())],
                array_values($rows),
            );
        }

        return Command::SUCCESS;
    }
}
