<?php

namespace App\DataFixtures;

use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ReservationFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // Créer un utilisateur de test
        $user = new User();
        $user->setEmail('client@magicvehicles.com');
        $user->setPassword($this->hasher->hashPassword($user, 'password'));
        $user->setIsVerified(true);
        $user->setFirstName('Alice');
        $user->setLastName('Durand');
        $user->setPhone('0601020304');
        $manager->persist($user);

        // Récupérer quelques véhicules
        $vehicles = $manager->getRepository(Vehicle::class)->findAll();

        if (empty($vehicles)) {
            $manager->flush();
            return;
        }

        // Créer quelques réservations d'exemple
        $reservations = [
            ['2026-04-01', '2026-04-05', $vehicles[0]],
            ['2026-04-10', '2026-04-12', $vehicles[1]],
            ['2026-05-01', '2026-05-03', $vehicles[2]],
        ];

        foreach ($reservations as [$start, $end, $vehicle]) {
            $dateStart = new \DateTimeImmutable($start);
            $dateEnd = new \DateTimeImmutable($end);
            $nbJours = $dateStart->diff($dateEnd)->days;

            $reservation = new Reservation();
            $reservation->setVehicle($vehicle);
            $reservation->setUser($user);
            $reservation->setStartDate($dateStart);
            $reservation->setEndDate($dateEnd);
            $reservation->setTotalPrice((string) ((float) $vehicle->getPrice() * $nbJours));
            $reservation->setConfirmationToken(bin2hex(random_bytes(16)));
            $reservation->setConfirmedAt(new \DateTimeImmutable());

            $manager->persist($reservation);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [VehicleFixtures::class];
    }
}
