<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ClientProfileDto
{
    public mixed $imageFile = null;

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.', max: 50)]
    public ?string $nom = null;

    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(min: 2, minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.', max: 50)]
    public ?string $prenom = null;

    #[Assert\NotBlank(message: 'L\'adresse e-mail est obligatoire.')]
    #[Assert\Email(message: 'Veuillez entrer un e-mail valide.')]
    public ?string $email = null;

    #[Assert\Length(min: 6, minMessage: 'Le nouveau mot de passe doit contenir au moins {{ limit }} caractères.', max: 255)]
    public ?string $newPassword = null;

    public ?string $confirmNewPassword = null;
}