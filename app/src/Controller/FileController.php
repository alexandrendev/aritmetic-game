<?php

namespace App\Controller;

use App\Entity\File;
use App\Repository\FileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class FileController extends AbstractController
{


    public function __construct(
        private FileRepository $fileRepository,
    )
    {
    }

    #[Route('/files/avatars', name: 'app_files_avatars')]
    public function getAvatars()
    {

        $files = $this->fileRepository->findAll();

        $baseUrl = $this->getParameter('app.public_url');


        return $this->json(
            array_map(
                fn(File $file) => [
                    'id' => $file->getId(),
                    'path' => $baseUrl . '/' . $file->getPath(),
                ],
                $files
            )
        );
    }

}
