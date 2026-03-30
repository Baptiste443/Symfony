<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Entité User — représente un utilisateur de l'application.
 *
 * Implémente UserInterface (requis par le système de sécurité Symfony)
 * et PasswordAuthenticatedUserInterface (requis pour le hachage de mot de passe).
 * La contrainte UniqueEntity garantit qu'il ne peut pas exister deux comptes
 * avec la même adresse email en BDD.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse email.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /** Identifiant auto-incrémenté, clé primaire */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Adresse email — sert également d'identifiant de connexion (getUserIdentifier) */
    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * Rôles de l'utilisateur, stockés en JSON en BDD.
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * Mot de passe haché (jamais stocké en clair).
     * Le hachage est effectué par UserPasswordHasherInterface avant la persistance.
     */
    #[ORM\Column]
    private ?string $password = null;

    /** Indique si l'utilisateur a confirmé son adresse email */
    #[ORM\Column]
    private bool $isVerified = false;

    /** Prénom du client (optionnel, renseigné depuis la page profil) */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $firstName = null;

    /** Nom de famille du client (optionnel, renseigné depuis la page profil) */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastName = null;

    /** Adresse postale complète (optionnelle, renseignée depuis la page profil) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    /** Numéro de téléphone (optionnel, renseigné depuis la page profil) */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    /** Retourne l'identifiant numérique de l'utilisateur */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Retourne l'adresse email de l'utilisateur */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Retourne l'identifiant visuel de l'utilisateur utilisé par Symfony Security.
     * Ici, c'est l'adresse email qui sert d'identifiant de connexion.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Retourne la liste des rôles de l'utilisateur.
     * ROLE_USER est toujours ajouté automatiquement, même si le tableau est vide,
     * pour garantir qu'un utilisateur a toujours au moins un rôle de base.
     *
     * @see UserInterface
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Tout utilisateur a au minimum ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Retourne le mot de passe haché de l'utilisateur.
     *
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Efface les données sensibles temporaires de l'utilisateur après l'authentification.
     * Utile si l'on stocke temporairement le mot de passe en clair (ex: $plainPassword).
     * Dans notre cas, rien à effacer car on ne stocke jamais le mot de passe en clair.
     *
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // Si des données sensibles temporaires étaient stockées, les effacer ici
        // $this->plainPassword = null;
    }

    /** Retourne true si l'utilisateur a vérifié son adresse email */
    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    /** Retourne le prénom du client */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    /** Retourne le nom de famille du client */
    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    /** Retourne l'adresse postale du client */
    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    /** Retourne le numéro de téléphone du client */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }
}
