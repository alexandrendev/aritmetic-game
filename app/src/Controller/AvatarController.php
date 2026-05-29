<?php
namespace App\Controller;

use App\Service\StorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class AvatarController extends AbstractController
{
    public function __construct(private StorageService $storage) {}

    #[Route('/avatars/{key}', name: 'avatars_serve', methods: ['GET'])]
    public function serve(string $key): StreamedResponse
    {
        $object = $this->storage->getObjectStreamWithMeta($key);
        if (!$object) {
            return new StreamedResponse(fn() => null, 404);
        }

        $stream = $object['stream'];
        $contentType = $object['contentType'] ?? 'application/octet-stream';
        $contentLength = $object['contentLength'] ?? null;

        $response = new StreamedResponse(function () use ($stream) {
            while (!$stream->eof()) {
                echo $stream->read(8192);
                flush();
            }
        });

        $response->headers->set('Content-Type', $contentType);
        if ($contentLength) {
            $response->headers->set('Content-Length', (string)$contentLength);
        }
        $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');

        return $response;
    }
}

