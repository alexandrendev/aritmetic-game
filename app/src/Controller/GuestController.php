<?php

namespace App\Controller;


use App\Entity\Guest;
use App\Repository\FileRepository;
use App\Repository\GuestRepository;
use App\Service\GuestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class GuestController extends AbstractController
{
    public function __construct(
        private GuestService $guestService,
        private FileRepository $fileRepository,
        private EntityManagerInterface $entityManager,
        private GuestRepository $guestRepository,
    )
    {
    }


    #[Route('/guests', name: 'app_guests_create', methods: ['POST'])]
    public function createNewGuest(Request $request)
    {
        $body  = json_decode($request->getContent(), true);
        $nickname = $body['nickname'];
        $avatar = $this->fileRepository->find($body['avatarId']);

        if (!$avatar) {
            return $this->json(['message' => 'Avatar not found.'], 404);
        }

        $newGuest = $this->guestService->saveGuest(
            nickname: $nickname,
            avatar:  $avatar
        );
        
        $baseUrl = $this->getParameter('app.public_url');

        return $this->json([
            'id' => $newGuest->getId(),
            'nickname' => $newGuest->getNickname(),
            'avatar' => [
                'id' => $newGuest->getAvatar()->getId(),
                'url' => $baseUrl . '/' .$newGuest->getAvatar()->getPath(),
            ]
        ], 201);
    }
}
