<?php

namespace App\Service;

use App\Entity\Guest;
use App\Repository\GuestRepository;
use Doctrine\ORM\EntityManagerInterface;

class GuestService
{
    public function __construct(
        private GuestRepository $guestRepository,
        private EntityManagerInterface $entityManager
    )
    {}

    public function saveGuest($nickname, $avatar)
    {
        $guest = new Guest();
        $guest->setNickname($nickname);
        $guest->setAvatar($avatar);
        $guest->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($guest);
        $this->entityManager->flush();

        return $this->guestRepository->findWithAvatarById($guest->getId());
    }
}
