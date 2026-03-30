<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Formulaire de mise à jour des données personnelles du client.
 *
 * Tous les champs sont optionnels (required => false) car l'utilisateur
 * peut créer un compte avec seulement son email et compléter son profil plus tard.
 * Les champs sont directement mappés sur l'entité User (data_class => User::class).
 */
class ProfileType extends AbstractType
{
    /**
     * Définit les champs du formulaire.
     *
     * Champs :
     *  - firstName : prénom, 100 caractères max
     *  - lastName  : nom de famille, 100 caractères max
     *  - address   : adresse postale complète, 255 caractères max
     *  - phone     : numéro de téléphone, 20 caractères max
     *
     * Tous utilisent TextType (champ texte simple, balise <input type="text">).
     * Les contraintes Length sont ajoutées en cohérence avec la BDD (VARCHAR).
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 100]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 100]),
                ],
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 255]),
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 20]),
                ],
            ])
        ;
    }

    /**
     * Configure les options du formulaire.
     * Lie le formulaire à l'entité User pour le mapping automatique des champs.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
