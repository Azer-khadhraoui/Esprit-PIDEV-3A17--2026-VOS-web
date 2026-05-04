<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\UserAccountService;
use App\Dto\SignupDto;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ValidationService;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserAccountServiceTest extends TestCase
{
    public function testAuthenticateUserSucceeds(): void
    {
        $user = new User();
        $user->setEmail('a@b.com');
        $user->setMotDePasse('hashed');

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findByEmail')->willReturn($user);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('isPasswordValid')->with($user, 'plain')->willReturn(true);

        $validation = $this->createMock(ValidationService::class);
        $validation->method('validateEmail')->willReturn('a@b.com');

        $svc = new UserAccountService(
            $repo,
            $this->createMock(EntityManagerInterface::class),
            $hasher,
            $validation,
            $this->createMock(EmailVerificationService::class),
            __DIR__
        );

        $result = $svc->authenticateUser('a@b.com', 'plain');
        $this->assertSame($user, $result);
    }

    public function testAuthenticateUserFailsOnBadPassword(): void
    {
        $user = new User();
        $user->setEmail('a@b.com');

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findByEmail')->willReturn($user);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('isPasswordValid')->willReturn(false);

        $validation = $this->createMock(ValidationService::class);
        $validation->method('validateEmail')->willReturn('a@b.com');

        $svc = new UserAccountService(
            $repo,
            $this->createMock(EntityManagerInterface::class),
            $hasher,
            $validation,
            $this->createMock(EmailVerificationService::class),
            __DIR__
        );

        $this->assertNull($svc->authenticateUser('a@b.com', 'wrong'));
    }

    public function testRegisterCreatesUser(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findByEmail')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        $validation = $this->createMock(ValidationService::class);
        $validation->method('validateName')->willReturnArgument(0);
        $validation->method('validateEmail')->willReturnArgument(0);
        $validation->method('validatePassword')->willReturnArgument(0);

        $emailVerifier = $this->createMock(EmailVerificationService::class);
        $emailVerifier->method('isDeliverable')->willReturn(true);

        $signup = new SignupDto();
        $signup->nom = 'Test';
        $signup->prenom = 'User';
        $signup->email = 't@u.com';
        $signup->password = 'secret123';
        $signup->confirmPassword = 'secret123';

        $svc = new UserAccountService(
            $repo,
            $entityManager,
            $hasher,
            $validation,
            $emailVerifier,
            sys_get_temp_dir()
        );

        $user = $svc->register($signup, null);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('t@u.com', $user->getEmail());
    }
}
