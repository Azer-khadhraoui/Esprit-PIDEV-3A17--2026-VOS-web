<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class SignupDto
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

    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.')]
    #[Assert\Length(min: 6, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.', max: 255)]
    public ?string $password = null;

    #[Assert\NotBlank(message: 'Veuillez confirmer le mot de passe.')]
    public ?string $confirmPassword = null;
}
