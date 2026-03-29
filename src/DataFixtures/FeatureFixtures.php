<?php

namespace App\DataFixtures;

use App\Entity\Feature;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class FeatureFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $features_magiques = [
            'Sort de rafraîchissement',
            'Boussole enchantée (GPS magique)',
            'Sièges auto-chauffants par enchantement',
            'Toit translucide métamorphique',
            'Miroir de vision arrière enchanté',
            'Cristal de communication à distance ',
            'Sort de vitesse constante',
            'Détection de pluie automatique par nuage domestiqué',
            'Guidage spectral pour le stationnement',
            'Orbe sonore harmonique',
            'Volant enchanté contre le froid',
            'Sort de trajectoire assistée',
            'Protection par champ de force',
            'Déchiffrage des symboles runiques',
            'Alerte de dérive magique',
            'Sièges en peau de dragon souple',
            'Recharge de baguette sans fil',
            'Éclairage par lucioles enchantées',
            'Ouverture par mot de passe mystique',
            'Suspension sur coussin d’air élémentaire'
        ];

        foreach ($features_magiques as $index => $featureName) {
            $feature = new Feature();
            $feature->setName($featureName);
            $manager->persist($feature);
            $this->addReference('feature-' . ($index + 1), $feature);
        }

        $manager->flush();
    }
}