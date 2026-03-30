<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité Reservation — représente une réservation de véhicule par un client.
 *
 * Une réservation lie un User à un Vehicle sur une période (startDate → endDate).
 * Le prix total est calculé et figé à la création (pas recalculé à chaque lecture).
 * Le confirmationToken est un identifiant unique lisible, utilisé dans les emails.
 */
#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    /** Identifiant auto-incrémenté, clé primaire */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Véhicule réservé.
     * Relation ManyToOne : un véhicule peut avoir plusieurs réservations
     * (à des périodes différentes). Non nullable : une réservation sans
     * véhicule n'a pas de sens.
     */
    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vehicle $vehicle = null;

    /**
     * Client ayant effectué la réservation.
     * Relation ManyToOne : un utilisateur peut avoir plusieurs réservations.
     * Non nullable : une réservation sans propriétaire n'a pas de sens.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /** Date de début de la location (type DATE : sans heure) */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $startDate = null;

    /** Date de fin de la location (type DATE : sans heure) */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $endDate = null;

    /**
     * Prix total calculé à la création : prix/jour × nombre de jours.
     * Stocké en DECIMAL(10,2) pour éviter les erreurs d'arrondi des flottants.
     * Stocké en string côté PHP (comportement Doctrine pour les DECIMAL).
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalPrice = null;

    /**
     * Token unique de 32 caractères hexadécimaux (issu de bin2hex(random_bytes(16))).
     * Permet d'identifier la réservation dans les emails et sur le bon de réservation
     * sans exposer l'id numérique. Contraint UNIQUE en BDD.
     */
    #[ORM\Column(length: 64, unique: true)]
    private ?string $confirmationToken = null;

    /**
     * Horodatage de la confirmation (= moment où l'email a été envoyé).
     * Nullable car une réservation pourrait théoriquement exister sans confirmation.
     * Dans notre flux, il est toujours renseigné immédiatement après la création.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    /** Retourne l'identifiant de la réservation */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Retourne le véhicule réservé */
    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(?Vehicle $vehicle): static
    {
        $this->vehicle = $vehicle;

        return $this;
    }

    /** Retourne l'utilisateur propriétaire de la réservation */
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /** Retourne la date de début de la location */
    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    /** Retourne la date de fin de la location */
    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    /** Retourne le prix total (chaîne décimale, ex: "210.00") */
    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): static
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    /** Retourne le token unique de confirmation */
    public function getConfirmationToken(): ?string
    {
        return $this->confirmationToken;
    }

    public function setConfirmationToken(string $confirmationToken): static
    {
        $this->confirmationToken = $confirmationToken;

        return $this;
    }

    /** Retourne la date/heure de confirmation de la réservation */
    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;

        return $this;
    }

    /**
     * Calcule et retourne le nombre de jours de location.
     * Utilise DateInterval::diff() entre startDate et endDate.
     * Appelable depuis Twig : {{ reservation.nbJours }}
     */
    public function getNbJours(): int
    {
        return $this->startDate->diff($this->endDate)->days;
    }
}
