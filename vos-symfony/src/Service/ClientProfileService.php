<?php

namespace App\Service;

use App\Dto\ClientProfileDto;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ClientProfileService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidationService $validation,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $projectDir,
    ) {
    }

    public function updateProfile(User $user, ClientProfileDto $profileDto, ?UploadedFile $uploadedImage = null): User
    {
        $validatedNom = $this->validation->validateName($profileDto->nom, 'nom');
        $validatedPrenom = $this->validation->validateName($profileDto->prenom, 'prénom');
        $validatedEmail = $this->validation->validateEmail($profileDto->email);

        if ($user->getEmail() !== $validatedEmail && $this->userRepository->findByEmail($validatedEmail)) {
            throw new \DomainException('Cet e-mail est déjà utilisé.');
        }

        $newPassword = trim((string) ($profileDto->newPassword ?? ''));
        $confirmNewPassword = trim((string) ($profileDto->confirmNewPassword ?? ''));

        if ($newPassword !== '' || $confirmNewPassword !== '') {
            if ($newPassword === '' || $confirmNewPassword === '') {
                throw new \InvalidArgumentException('Veuillez remplir les deux champs de mot de passe.');
            }

            if ($newPassword !== $confirmNewPassword) {
                throw new \InvalidArgumentException('Les nouveaux mots de passe ne correspondent pas.');
            }

            $validatedPassword = $this->validation->validatePassword($newPassword, 6, 'nouveau mot de passe');
            $user->setMotDePasse($this->passwordHasher->hashPassword($user, $validatedPassword));
        }

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

        $user->setNom($validatedNom);
        $user->setPrenom($validatedPrenom);
        $user->setEmail($validatedEmail);

        $this->entityManager->flush();

        return $user;
    }
}