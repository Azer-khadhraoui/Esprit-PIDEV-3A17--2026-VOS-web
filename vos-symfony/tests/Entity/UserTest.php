<?php

namespace App\Tests\Entity;

use PHPUnit\Framework\TestCase;
use App\Entity\User;

class UserTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $user = new User();
        $this->assertNull($user->getId());

        $user->setNom('Dupont');
        $user->setPrenom('Jean');
        $user->setEmail('jean@example.com');
        $user->setMotDePasse('secret');
        $user->setRole('CLIENT');

        $this->assertSame('Dupont', $user->getNom());
        $this->assertSame('Jean', $user->getPrenom());
        $this->assertSame('jean@example.com', $user->getEmail());
        $this->assertSame('secret', $user->getMotDePasse());
        $this->assertSame('CLIENT', $user->getRole());

        $this->assertNull($user->getImageProfil());
        $user->setImageProfil('avatar.png');
        $this->assertSame('/uploads/profiles/avatar.png', $user->getImageProfilUrl());
    }
}
