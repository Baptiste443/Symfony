<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire de changement de mot de passe.
 *
 * Utilise RepeatedType pour demander le mot de passe deux fois et vérifier
 * automatiquement que les deux saisies sont identiques avant toute validation.
 * Ce formulaire n'est PAS mappé sur une entité (pas de data_class) car le
 * mot de passe n'est pas appliqué directement : il passe par un processus
 * de confirmation par email (code à 6 chiffres) avant d'être enregistré.
 */
class ChangePasswordType extends AbstractType
{
    /**
     * Définit le champ de saisie du mot de passe avec double confirmation.
     *
     * RepeatedType génère deux champs <input type="password"> :
     *  - 'first'  : "Nouveau mot de passe"
     *  - 'second' : "Confirmer le mot de passe"
     *
     * Si les deux valeurs diffèrent, Symfony ajoute automatiquement l'erreur
     * définie dans invalid_message, sans atteindre les contraintes de validation.
     *
     * mapped => false : la valeur ne sera pas écrite directement sur l'entité.
     * Le contrôleur récupère la valeur via $form->get('newPassword')->getData().
     *
     * Contraintes :
     *  - NotBlank : le champ est obligatoire
     *  - Length min 6 : sécurité minimale du mot de passe
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Nouveau mot de passe'],
                'second_options' => ['label' => 'Confirmer le mot de passe'],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir un mot de passe.']),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        'max' => 4096,
                    ]),
                ],
            ])
        ;
    }

    /**
     * Pas de data_class car ce formulaire n'est pas lié à une entité.
     * La valeur est traitée manuellement dans ProfileController::changePassword().
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
