<?php

namespace App\DataFixtures;

use App\Entity\Feature;
use App\Entity\TypeVehicle;
use App\Entity\Vehicle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class VehicleFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $vehicles = [
            ['Carrosse Enchanté', 50000, 4, 'img/3m2tQrnE6M.webp', 'type-vehicle-type-1'],
            ['Carrosse Royal Lunaire', 60000, 6, 'img/UocqLJipsV.webp', 'type-vehicle-type-1'],
            ['Vélo Magique', 15000, 1, 'img/36uil3bNYo.webp', 'type-vehicle-type-2'],
            ['Trottinette Sorcière', 12000, 1, 'img/05QGyn9q3k.webp', 'type-vehicle-type-2'],
            ['Scooter Envoûté', 18000, 2, 'img/KXgLaPlQ8f.webp', 'type-vehicle-type-2'],
            ['Moto Spectrale', 25000, 2, 'img/3rgw5UQ0uG.webp', 'type-vehicle-type-2'],
            ['Licorne Mystique', 30000, 1, 'img/92DWUWdpja.webp', 'type-vehicle-type-3'],
            ['Griffon de Transport', 45000, 2, 'img/IOGIXv8VzC.webp', 'type-vehicle-type-3'],
            ['Dragon Domestiqué', 70000, 2, 'img/Bh1zHi9mgO.webp', 'type-vehicle-type-3'],
            ['Tapis Volant Élémentaire', 22000, 4, 'img/o5nVOYVN98.webp', 'type-vehicle-type-4'],
        ];

        foreach ($vehicles as $index => $data) {
            $vehicle = new Vehicle();
            $vehicle->setName($data[0]);
            $vehicle->setPrice($data[1]);
            $vehicle->setCapacity($data[2]);
            $vehicle->setImagePath($data[3]);
            $vehicle->setTypeVehicle($this->getReference($data[4], TypeVehicle::class));

            $numFeatures = ($index * 3 + 7) % 10 + 1; // Assure entre 1 et 5 features par véhicule

            for ($j = 0; $j < $numFeatures; $j++) {
                $featureIndex = (($index * 7 + $j * 3) % 20) + 1; // Mélange mieux tout en restant déterministe
                $vehicle->addFeature($this->getReference('feature-' . $featureIndex, Feature::class));
            }


            $manager->persist($vehicle);
        }

        $manager->flush();

    }
}
