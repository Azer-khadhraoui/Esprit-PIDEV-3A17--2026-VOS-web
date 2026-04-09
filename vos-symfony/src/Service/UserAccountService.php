<?php

namespace App\Service;

use App\Dto\SignupDto;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserAccountService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidationService $validation,
        private readonly string $projectDir,
    ) {
    }

    public function register(SignupDto $signupDto, ?UploadedFile $uploadedImage): User
    {
        $nom = $this->validation->validateName($signupDto->nom, 'nom');
        $prenom = $this->validation->validateName($signupDto->prenom, 'prénom');
        $email = $this->validation->validateEmail($signupDto->email);
        $password = $this->validation->validatePassword($signupDto->password);

        if ($signupDto->password !== $signupDto->confirmPassword) {
            throw new \InvalidArgumentException('Les mots de passe ne correspondent pas.');
        }

        if ($this->userRepository->findByEmail($email)) {
            throw new \DomainException('Cet e-mail est deja utilise.');
        }

        $user = new User();
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setEmail($email);
        $user->setRole('CLIENT');

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

        $hashedPassword = $this->passwordHasher->hashPassword($user, $signupDto->password);
        $user->setMotDePasse($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function authenticateAdmin(string $email, string $password): ?User
    {
        try {
            $email = $this->validation->validateEmail($email);
        } catch (\InvalidArgumentException) {
            return null;
        }

        // For signin, keep validation non-throwing to avoid exposing exception pages.
        // Any non-empty password is accepted here and verified against the hashed password.
        $password = trim($password);
        if ($password == '') {
            return null;
        }

        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            return null;
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return null;
        }

        if (!str_starts_with($user->getRole(), 'ADMIN')) {
            return null;
        }

        return $user;
    }

    public function authenticateUser(string $email, string $password): ?User
    {
        try {
            $email = $this->validation->validateEmail($email);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $password = trim($password);
        if ($password == '') {
            return null;
        }

        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            return null;
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return null;
        }

        return $user;
    }
}
