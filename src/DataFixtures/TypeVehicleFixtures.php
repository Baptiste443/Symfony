<?php

namespace App\DataFixtures;

use App\Entity\TypeVehicle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TypeVehicleFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $Type1 = new TypeVehicle();
        $Type1->setName('Carrosse Magique');

        $this->addReference('type-vehicle-type-1', $Type1);
        $manager->persist($Type1);

        $Type2 = new TypeVehicle();
        $Type2->setName('À Roues Enchanté');

        $this->addReference('type-vehicle-type-2', $Type2);
        $manager->persist($Type2);

        $Type3 = new TypeVehicle();
        $Type3->setName('Animal Monté');

        $this->addReference('type-vehicle-type-3', $Type3);
        $manager->persist($Type3);

        $Type4 = new TypeVehicle();
        $Type4->setName('Tapis volant');

        $this->addReference('type-vehicle-type-4', $Type4);
        $manager->persist($Type4);

        $manager->flush();

    }
}
