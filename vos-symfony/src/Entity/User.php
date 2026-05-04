<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`utilisateur`', uniqueConstraints: [new ORM\UniqueConstraint(name: 'email', columns: ['email'])])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_utilisateur', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'image_profil', type: 'string', length: 255, nullable: true)]
    private ?string $imageProfil = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    private ?string $prenom = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank(message: "L'adresse e-mail est obligatoire.")]
    #[Assert\Email(message: 'Adresse e-mail invalide.')]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.')]
    private ?string $motDePasse = null;

    #[ORM\Column(type: 'string', length: 50, columnDefinition: "ENUM('CLIENT','ADMIN_RH','ADMIN_TECHNIQUE') NOT NULL DEFAULT 'CLIENT'")]
    private string $role = 'CLIENT';

    #[ORM\Column(name: 'signature_url', type: 'string', length: 500, nullable: true)]
    private ?string $signatureUrl = null;

    #[ORM\Column(name: 'reset_token_hash', type: 'string', length: 64, nullable: true)]
    #[Ignore]
    private ?string $resetTokenHash = null;

    #[ORM\Column(name: 'reset_expires_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resetExpiresAt = null;

    #[ORM\Column(name: 'face_descriptor', type: 'text', nullable: true)]
    private ?string $faceDescriptor = null;

    #[ORM\Column(name: 'face_auth_enabled', type: 'boolean', options: ['default' => false])]
    private bool $faceAuthEnabled = false;

    private ?string $plainPassword = null;

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getImageProfil(): ?string
    {
        return $this->imageProfil;
    }

    public function setImageProfil(?string $imageProfil): static
    {
        $this->imageProfil = $imageProfil;
        return $this;
    }

    public function getImageProfilUrl(): ?string
    {
        return $this->imageProfil !== null ? '/uploads/profiles/' . $this->imageProfil : null;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getMotDePasse(): ?string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(string $motDePasse): static
    {
        $this->motDePasse = $motDePasse;
        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getSignatureUrl(): ?string
    {
        return $this->signatureUrl;
    }

    public function setSignatureUrl(?string $signatureUrl): static
    {
        $this->signatureUrl = $signatureUrl;
        return $this;
    }

    #[Ignore]
    public function getResetTokenHash(): ?string
    {
        return $this->resetTokenHash;
    }

    public function setResetTokenHash(#[\SensitiveParameter] ?string $resetTokenHash): static
    {
        $this->resetTokenHash = $resetTokenHash;

        return $this;
    }

    public function getResetExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetExpiresAt;
    }

    public function setResetExpiresAt(?\DateTimeImmutable $resetExpiresAt): static
    {
        $this->resetExpiresAt = $resetExpiresAt;

        return $this;
    }

    public function getFaceDescriptor(): ?string
    {
        return $this->faceDescriptor;
    }

    public function setFaceDescriptor(?string $faceDescriptor): static
    {
        $this->faceDescriptor = $faceDescriptor;

        return $this;
    }

    public function isFaceAuthEnabled(): bool
    {
        return $this->faceAuthEnabled;
    }

    public function setFaceAuthEnabled(bool $faceAuthEnabled): static
    {
        $this->faceAuthEnabled = $faceAuthEnabled;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->motDePasse !== null ? (string) $this->motDePasse : null;
    }

    public function getRoles(): array
    {
        return [$this->role];
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}
