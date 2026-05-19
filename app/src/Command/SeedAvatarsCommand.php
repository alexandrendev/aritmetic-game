<?php

namespace App\Command;

use App\Entity\File;
use App\Repository\FileRepository;
use App\Service\StorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:seed:avatars',
    description: 'Upload default avatars to MinIO and seed the database.',
)]
class SeedAvatarsCommand extends Command
{
    private const AVATARS = [
        'avatar1.png' => 'image/png',
        'avatar2.png' => 'image/png',
        'avatar3.png' => 'image/png',
        'avatar4.png' => 'image/png',
        'avatar5.png' => 'image/png',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private StorageService $storage,
        private FileRepository $fileRepository,
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $avatarsDir = $this->projectDir . '/public/avatars';

        foreach (self::AVATARS as $filename => $mimeType) {
            $existing = $this->fileRepository->findOneBy(['path' => $filename]);
            if ($existing) {
                $output->writeln("Skipping {$filename} (already seeded).");
                continue;
            }

            $localPath = $avatarsDir . '/' . $filename;
            if (!is_file($localPath)) {
                $output->writeln("Skipping {$filename} (file not found at {$localPath}).");
                continue;
            }

            $this->storage->upload($filename, $localPath, $mimeType);

            $entity = new File();
            $entity->setPath($filename);
            $entity->setCreatedAt(new \DateTimeImmutable());
            $this->em->persist($entity);

            $output->writeln("Seeded {$filename}.");
        }

        $this->em->flush();
        $output->writeln('Done.');

        return Command::SUCCESS;
    }
}
