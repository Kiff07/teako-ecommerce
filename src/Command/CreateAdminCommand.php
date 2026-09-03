<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Admin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Crée (ou met à jour) un compte administrateur')]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'E-mail', 'admin@teako.mg')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe', 'teako2026')
            ->addArgument('name', InputArgument::OPTIONAL, 'Nom affiché', 'Admin Teako');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $password = (string) $input->getArgument('password');

        if (strlen($password) < 6) {
            $io->error('Le mot de passe doit faire au moins 6 caractères.');

            return Command::FAILURE;
        }

        $repo = $this->em->getRepository(Admin::class);
        $admin = $repo->findOneBy(['email' => mb_strtolower($email)]);
        if (null === $admin) {
            $admin = new Admin();
            $admin->setEmail($email);
            $this->em->persist($admin);
            $io->writeln(sprintf('Création du compte <info>%s</info>.', $email));
        } else {
            $io->writeln(sprintf('Mise à jour du mot de passe de <info>%s</info>.', $email));
        }

        $admin->setName((string) $input->getArgument('name'));
        $admin->setActive(true);
        $admin->setPassword($this->hasher->hashPassword($admin, $password));
        $this->em->flush();

        $io->success('Compte administrateur prêt. Connexion : '.$email);

        return Command::SUCCESS;
    }
}
