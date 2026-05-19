<?php

namespace App\Controller;

use App\Entity\File;
use App\Repository\FileRepository;
use App\Service\StorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class FileController extends AbstractController
{
    public function __construct(
        private FileRepository $fileRepository,
        private StorageService $storage,
    ) {
    }

    #[Route('/files/avatars', name: 'app_files_avatars')]
    public function getAvatars(): JsonResponse
    {
        $files = $this->fileRepository->findAll();

        return $this->json(
            array_map(
                fn(File $file) => [
                    'id' => $file->getId(),
                    'url' => $this->storage->getPublicUrl($file->getPath()),
                ],
                $files
            )
        );
    }
}

