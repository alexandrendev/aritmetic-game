<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\User;
use App\Repository\FileRepository;
use App\Repository\GuestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/admin/files', name: 'api_admin_files_')]
class AdminFileController extends AbstractController
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;
    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    public function __construct(
        private FileRepository $fileRepository,
        private GuestRepository $guestRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $files = $this->fileRepository->findBy([], ['id' => 'DESC']);

        return $this->json(array_map(fn(File $file) => $this->serialize($file), $files));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile instanceof UploadedFile) {
            return $this->json(
                ['message' => 'No file provided. Send multipart/form-data with a "file" field.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $error = $this->validateUpload($uploadedFile);
        if ($error) {
            return $this->json(['message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $path = $this->storeFile($uploadedFile);

        $file = (new File())
            ->setPath($path)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        return $this->json($this->serialize($file), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $file = $this->fileRepository->find($id);
        if (!$file) {
            return $this->json(['message' => 'File not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($file));
    }

    #[Route('/{id}', name: 'update', methods: ['POST', 'PATCH'])]
    public function update(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $file = $this->fileRepository->find($id);
        if (!$file) {
            return $this->json(['message' => 'File not found.'], Response::HTTP_NOT_FOUND);
        }

        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile instanceof UploadedFile) {
            return $this->json(
                ['message' => 'No file provided. Send multipart/form-data with a "file" field.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $error = $this->validateUpload($uploadedFile);
        if ($error) {
            return $this->json(['message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $oldPhysicalPath = $this->getPublicDir() . '/' . $file->getPath();
        if (is_file($oldPhysicalPath)) {
            @unlink($oldPhysicalPath);
        }

        $path = $this->storeFile($uploadedFile);
        $file->setPath($path)->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $this->json($this->serialize($file));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $file = $this->fileRepository->find($id);
        if (!$file) {
            return $this->json(['message' => 'File not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($this->guestRepository->isFileInUse($file)) {
            return $this->json(
                ['message' => 'Cannot delete: file is referenced by one or more guests.'],
                Response::HTTP_CONFLICT
            );
        }

        $physicalPath = $this->getPublicDir() . '/' . $file->getPath();
        if (is_file($physicalPath)) {
            @unlink($physicalPath);
        }

        $this->entityManager->remove($file);
        $this->entityManager->flush();

        return $this->json(['message' => 'File deleted.']);
    }

    private function validateUpload(UploadedFile $uploadedFile): ?string
    {
        if ($uploadedFile->getSize() > self::MAX_FILE_SIZE) {
            return sprintf('File too large. Maximum size is %dMB.', self::MAX_FILE_SIZE / 1024 / 1024);
        }

        $mimeType = $uploadedFile->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return sprintf(
                'Invalid file type "%s". Allowed: %s.',
                $mimeType,
                implode(', ', self::ALLOWED_MIME_TYPES)
            );
        }

        return null;
    }

    private function storeFile(UploadedFile $uploadedFile): string
    {
        $ext = self::MIME_TO_EXT[$uploadedFile->getMimeType()] ?? 'jpg';
        $filename = uniqid('avatar_', true) . '.' . $ext;

        $uploadedFile->move($this->getPublicDir() . '/avatars', $filename);

        return 'avatars/' . $filename;
    }

    private function getPublicDir(): string
    {
        return $this->getParameter('kernel.project_dir') . '/public';
    }

    private function serialize(File $file): array
    {
        $baseUrl = $this->getParameter('app.public_url');

        return [
            'id'        => $file->getId(),
            'path'      => $file->getPath(),
            'url'       => $baseUrl . '/' . $file->getPath(),
            'createdAt' => $file->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $file->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
