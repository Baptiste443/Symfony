<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository de l'entité Reservation.
 *
 * Fournit des méthodes de requête personnalisées pour récupérer les
 * réservations d'un utilisateur, séparées en deux catégories :
 * réservations passées et réservations à venir.
 *
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Retourne les réservations passées d'un utilisateur.
     *
     * Une réservation est considérée "passée" si sa date de fin (endDate)
     * est strictement antérieure à aujourd'hui (minuit).
     * Résultats triés de la plus récente à la plus ancienne (DESC sur endDate)
     * pour afficher en priorité les dernières locations effectuées.
     *
     * @return Reservation[]
     */
    public function findPastByUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.endDate < :today')
            ->setParameter('user', $user)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('r.endDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les réservations à venir d'un utilisateur.
     *
     * Une réservation est considérée "à venir" si sa date de fin (endDate)
     * est supérieure ou égale à aujourd'hui (inclut les locations en cours).
     * Résultats triés par date de début croissante (ASC sur startDate)
     * pour afficher la prochaine location en tête de liste.
     *
     * @return Reservation[]
     */
    public function findUpcomingByUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.endDate >= :today')
            ->setParameter('user', $user)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('r.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
