<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Command;

use App\Auth\Infrastructure\Service\KeycloakService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Da de alta una cuenta y le manda el correo para que elija su contraseña.
 *
 * Es la única forma de crear cuentas: el juego no tiene registro. Con un
 * correo que ya tiene cuenta, reenvía la invitación (por si caducó o se
 * perdió) en vez de fallar.
 */
#[AsCommand(name: 'eter:account:create', description: 'Crea una cuenta y le manda el correo para poner la contraseña')]
final class CreateAccountCommand extends Command
{
    /** Tres días: lo bastante para ver el correo con calma. */
    private const LINK_LIFESPAN = 3 * 24 * 3600;

    public function __construct(private readonly KeycloakService $keycloak)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Correo de la cuenta')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nombre de jugador (por defecto, lo de antes de la @)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getArgument('email')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error(sprintf('«%s» no es un correo válido.', $email));

            return Command::INVALID;
        }

        $name = trim((string) ($input->getOption('name') ?? '')) ?: ucfirst(strstr($email, '@', true));
        if (mb_strlen($name) > 40) {
            $io->error('El nombre no puede pasar de 40 caracteres.');

            return Command::INVALID;
        }

        $account = $this->keycloak->createUserPendingPassword($email, $name);
        $this->keycloak->sendSetPasswordEmail($account['id'], self::LINK_LIFESPAN);

        $account['created']
            ? $io->success(sprintf('Cuenta creada para %s («%s»). Le ha llegado el correo para poner la contraseña.', $email, $name))
            : $io->note(sprintf('%s ya tenía cuenta: le he reenviado el correo para poner la contraseña.', $email));
        $io->text(sprintf('El enlace caduca en %d días.', self::LINK_LIFESPAN / 86400));

        return Command::SUCCESS;
    }
}
