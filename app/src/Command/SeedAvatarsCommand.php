<?php

namespace App\Command;

use App\Entity\File;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:avatars',
    description: 'Add a short description for your command',
)]
class SeedAvatarsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }


    /*
     * docker compose run --rm php php bin/console app:seed:avatars
     * */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $avatars = [
            'avatar1.png',
            'avatar2.png',
            'avatar3.png',
            'avatar4.png',
            'avatar5.png',
        ];


        foreach ($avatars as $avatar) {
            $entity = new File;
            $entity->setPath('avatars/' . $avatar);
            $entity->setCreatedAt(new \DateTimeImmutable());
            $this->em->persist($entity);
        }

        $this->em->flush();

        $output->writeln('Avatares seedados.');

        return Command::SUCCESS;
    }
}
