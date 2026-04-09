<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AdminUserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidationService $validation,
        private readonly string $projectDir,
    ) {
    }

    public function updateUser(User $user, string $nom, string $prenom, string $email, string $role, ?UploadedFile $uploadedImage = null): User
    {
        $validatedNom = $this->validation->validateName($nom, 'nom');
        $validatedPrenom = $this->validation->validateName($prenom, 'prénom');
        $validatedEmail = $this->validation->validateEmail($email);
        $validatedRole = $this->validation->validateRole($role);

        // Vérifier l'unicité de l'email si changement
        if ($user->getEmail() !== $validatedEmail) {
            if ($this->userRepository->findByEmail($validatedEmail)) {
                throw new \DomainException('Cet e-mail est déjà utilisé.');
            }
        }

        $user->setNom($validatedNom);
        $user->setPrenom($validatedPrenom);
        $user->setEmail($validatedEmail);
        $user->setRole($validatedRole);

        if ($uploadedImage instanceof UploadedFile) {
            $this->validation->validateImageFile($uploadedImage);

            $uploadDirectory = $this->projectDir . '/public/uploads/profiles';
            if (!is_dir($uploadDirectory)) {
                @mkdir($uploadDirectory, 0775, true);
            }

            $extension = $this->validation->resolveImageExtension($uploadedImage);
            $newFileName = uniqid('profile_', true) . '.' . $extension;

            try {
                $uploadedImage->move($uploadDirectory, $newFileName);
                $user->setImageProfil('/uploads/profiles/' . $newFileName);
            } catch (FileException) {
                throw new \RuntimeException('Impossible de televerser la photo de profil.');
            }
        }

        $this->entityManager->flush();

        return $user;
    }

    public function deleteUser(User $user, int $adminUserId): void
    {
        if ($user->getId() === $adminUserId) {
            throw new \DomainException('Vous ne pouvez pas supprimer votre propre compte admin.');
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
