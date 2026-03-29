<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /** Réservations passées d'un utilisateur (date de fin < aujourd'hui), triées de la plus récente */
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

    /** Réservations à venir d'un utilisateur (date de fin >= aujourd'hui), triées par date de début */
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
