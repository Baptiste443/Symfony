<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Form\ChangePasswordType;
use App\Form\ProfileType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur du compte client.
 * Gère le profil utilisateur (données personnelles, changement de mot de passe)
 * et la gestion des réservations (consultation, modification, annulation).
 * Gère également le changement de langue (locale) de l'application.
 */
class ProfileController extends AbstractController
{
    /**
     * Change la langue de l'application pour la session courante.
     *
     * Stocke la locale choisie ('fr' ou 'en') dans la session.
     * Le LocaleListener (EventListener) lira cette valeur à chaque requête
     * pour appliquer la bonne langue aux traductions Twig.
     * Redirige vers la page précédente (referer) ou la page d'accueil.
     *
     * Route : GET /change-locale/{locale}
     */
    #[Route('/change-locale/{locale}', name: 'change_locale')]
    public function changeLocale(string $locale, Request $request): RedirectResponse
    {
        // Stocker la langue en session
        $request->getSession()->set('_locale', $locale);

        // Rediriger vers la page précédente ou vers la page d'accueil
        $referer = $request->headers->get('referer');
        return new RedirectResponse($referer ?: $this->generateUrl('home'));
    }

    /**
     * Affiche et traite le formulaire de profil du client connecté.
     *
     * En GET : affiche la page profil avec :
     *   - le formulaire ProfileType (données personnelles : prénom, nom, adresse, téléphone)
     *   - le formulaire ChangePasswordType (changement de mot de passe)
     *   - les réservations à venir et passées de l'utilisateur
     *
     * En POST (soumission du formulaire données personnelles) :
     *   - met à jour l'entité User en BDD via flush() (les champs sont directement mappés)
     *   - redirige avec un flash 'success'
     *
     * Accès restreint à ROLE_USER via access_control dans security.yaml.
     *
     * Route : GET|POST /profile
     */
    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $em, ReservationRepository $reservationRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Formulaire de mise à jour des données personnelles
        $profileForm = $this->createForm(ProfileType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Vos informations ont été mises à jour.');
            return $this->redirectToRoute('app_profile');
        }

        // Formulaire de changement de mot de passe (non mappé, traité séparément)
        $passwordForm = $this->createForm(ChangePasswordType::class);

        // Récupérer les réservations passées et à venir de l'utilisateur
        $reservationsPassees = $reservationRepo->findPastByUser($user);
        $reservationsAvenir = $reservationRepo->findUpcomingByUser($user);

        return $this->render('profile/index.html.twig', [
            'profileForm' => $profileForm,
            'passwordForm' => $passwordForm,
            'reservationsPassees' => $reservationsPassees,
            'reservationsAvenir' => $reservationsAvenir,
        ]);
    }

    /**
     * Traite la demande de changement de mot de passe (étape 1 sur 2).
     *
     * Reçoit le formulaire ChangePasswordType en POST.
     * Si valide :
     *  1. Hache le nouveau mot de passe via UserPasswordHasherInterface.
     *  2. Stocke le hash ET un code à 6 chiffres dans la session
     *     (le mot de passe n'est PAS encore enregistré en BDD à ce stade).
     *  3. Envoie le code par email (template profile/confirmation_email.html.twig).
     *  4. Redirige vers la page de confirmation (étape 2).
     *
     * Ce double mécanisme (code par email) évite qu'un tiers ayant accès à la session
     * puisse changer le mot de passe sans accès à la boîte mail.
     *
     * Route : POST /profile/change-password
     */
    #[Route('/profile/change-password', name: 'app_profile_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $hasher,
        MailerInterface $mailer
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $passwordForm = $this->createForm(ChangePasswordType::class);
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            // Récupérer le nouveau mot de passe saisi
            $newPassword = $passwordForm->get('newPassword')->getData();

            // Hasher le nouveau mot de passe et le stocker temporairement en session
            $hashedPassword = $hasher->hashPassword($user, $newPassword);
            $request->getSession()->set('pending_password', $hashedPassword);

            // Générer un code de confirmation à 6 chiffres
            $code = random_int(100000, 999999);
            $request->getSession()->set('password_confirm_code', $code);

            // Envoyer le code par mail
            $email = (new TemplatedEmail())
                ->from(new Address('contact@magicvehicles.com', 'Magic Vehicles'))
                ->to((string) $user->getEmail())
                ->subject('Confirmation de changement de mot de passe')
                ->htmlTemplate('profile/confirmation_email.html.twig')
                ->context(['code' => $code]);

            $mailer->send($email);

            $this->addFlash('info', 'Un code de confirmation a été envoyé à votre adresse email.');
            return $this->redirectToRoute('app_profile_confirm_password');
        }

        // Si le formulaire est invalide, réafficher la page profil avec les erreurs
        $profileForm = $this->createForm(ProfileType::class, $user);
        return $this->render('profile/index.html.twig', [
            'profileForm' => $profileForm,
            'passwordForm' => $passwordForm,
            'reservationsPassees' => [],
            'reservationsAvenir' => [],
        ]);
    }

    /**
     * Valide le code de confirmation et applique le nouveau mot de passe (étape 2 sur 2).
     *
     * En GET : affiche le formulaire de saisie du code.
     *
     * En POST :
     *  - Compare le code soumis avec celui stocké en session.
     *  - Si correct : récupère le mot de passe hashé en session, l'applique
     *    à l'entité User, flush en BDD, nettoie la session.
     *  - Si incorrect : flash 'danger' et on reste sur la page.
     *
     * Note : la comparaison est faite avec == (pas ===) car le code vient
     * du formulaire HTML en tant que string et est stocké en session en int.
     *
     * Route : GET|POST /profile/confirm-password
     */
    #[Route('/profile/confirm-password', name: 'app_profile_confirm_password', methods: ['GET', 'POST'])]
    public function confirmPassword(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $submittedCode = $request->request->get('confirmation_code');
            $expectedCode = $request->getSession()->get('password_confirm_code');
            $pendingPassword = $request->getSession()->get('pending_password');

            if ($submittedCode == $expectedCode && $pendingPassword) {
                // Appliquer le nouveau mot de passe
                $user->setPassword($pendingPassword);
                $em->flush();

                // Nettoyer la session
                $request->getSession()->remove('password_confirm_code');
                $request->getSession()->remove('pending_password');

                $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');
                return $this->redirectToRoute('app_profile');
            }

            $this->addFlash('danger', 'Code de confirmation incorrect. Veuillez réessayer.');
        }

        return $this->render('profile/confirm_password.html.twig');
    }

    /**
     * Permet à l'utilisateur de modifier une réservation à venir.
     *
     * En GET : affiche le formulaire pré-rempli avec le véhicule et les dates actuels,
     * ainsi que la liste de tous les véhicules disponibles (dropdown).
     *
     * En POST :
     *  1. Récupère les nouvelles dates et le nouveau véhicule depuis la requête.
     *  2. Valide la cohérence des dates (fin > début).
     *  3. Vérifie la disponibilité du véhicule choisi, en excluant la réservation
     *     courante de la recherche de conflits (pour permettre de garder le même véhicule).
     *  4. Recalcule le prix total et met à jour la réservation en BDD.
     *
     * Protections :
     *  - Seul le propriétaire de la réservation peut y accéder.
     *  - Impossible de modifier une réservation dont la date de fin est passée.
     *
     * Route : GET|POST /profile/reservation/{id}/edit
     */
    #[Route('/profile/reservation/{id}/edit', name: 'app_profile_reservation_edit', methods: ['GET', 'POST'])]
    public function editReservation(Reservation $reservation, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Seul le propriétaire de la réservation peut la modifier
        if ($reservation->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès interdit.');
        }

        // On ne peut modifier qu'une réservation à venir
        if ($reservation->getEndDate() < new \DateTimeImmutable('today')) {
            $this->addFlash('danger', 'Impossible de modifier une réservation passée.');
            return $this->redirectToRoute('app_profile');
        }

        // Liste de tous les véhicules pour le choix du véhicule
        $vehicles = $em->getRepository(Vehicle::class)->findAll();

        if ($request->isMethod('POST')) {
            $dateStartStr = $request->request->get('dateStart');
            $dateEndStr = $request->request->get('dateEnd');
            $vehicleId = $request->request->get('vehicleId');

            $dateStart = $dateStartStr ? new \DateTimeImmutable($dateStartStr) : null;
            $dateEnd = $dateEndStr ? new \DateTimeImmutable($dateEndStr) : null;
            $vehicle = $em->getRepository(Vehicle::class)->find($vehicleId);

            // Validation : tous les champs doivent être renseignés
            if (!$dateStart || !$dateEnd || !$vehicle) {
                $this->addFlash('danger', 'Veuillez remplir tous les champs.');
                return $this->render('profile/edit_reservation.html.twig', [
                    'reservation' => $reservation,
                    'vehicles' => $vehicles,
                ]);
            }

            // Validation : la date de fin doit être après la date de début
            if ($dateEnd <= $dateStart) {
                $this->addFlash('danger', 'La date de fin doit être postérieure à la date de début.');
                return $this->render('profile/edit_reservation.html.twig', [
                    'reservation' => $reservation,
                    'vehicles' => $vehicles,
                ]);
            }

            // Vérification de disponibilité : on exclut la réservation en cours (r.id != currentId)
            // pour autoriser le client à garder le même véhicule avec de nouvelles dates
            $conflit = $em->getRepository(Reservation::class)->createQueryBuilder('r')
                ->where('r.vehicle = :vehicle')
                ->andWhere('r.id != :currentId')
                ->andWhere('r.startDate < :dateEnd')
                ->andWhere('r.endDate > :dateStart')
                ->setParameter('vehicle', $vehicle)
                ->setParameter('currentId', $reservation->getId())
                ->setParameter('dateStart', $dateStart)
                ->setParameter('dateEnd', $dateEnd)
                ->getQuery()
                ->getOneOrNullResult();

            if ($conflit) {
                $this->addFlash('danger', 'Ce véhicule n\'est pas disponible sur cette période.');
                return $this->render('profile/edit_reservation.html.twig', [
                    'reservation' => $reservation,
                    'vehicles' => $vehicles,
                ]);
            }

            // Recalcul du prix total et mise à jour de la réservation
            $nbJours = $dateStart->diff($dateEnd)->days;
            $reservation->setVehicle($vehicle);
            $reservation->setStartDate($dateStart);
            $reservation->setEndDate($dateEnd);
            $reservation->setTotalPrice((string) ((float) $vehicle->getPrice() * $nbJours));
            $em->flush();

            $this->addFlash('success', 'Réservation modifiée avec succès.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/edit_reservation.html.twig', [
            'reservation' => $reservation,
            'vehicles' => $vehicles,
        ]);
    }

    /**
     * Annule (supprime) une réservation à venir du client.
     *
     * Accepte uniquement les requêtes POST pour éviter les suppressions
     * accidentelles via un simple lien (GET). Dans la vue, un bouton "Annuler"
     * soumet un mini-formulaire avec une confirmation JavaScript (confirm()).
     *
     * Protections :
     *  - Seul le propriétaire peut annuler sa réservation.
     *  - Impossible d'annuler une réservation passée (date de fin dépassée).
     *
     * Route : POST /profile/reservation/{id}/cancel
     */
    #[Route('/profile/reservation/{id}/cancel', name: 'app_profile_reservation_cancel', methods: ['POST'])]
    public function cancelReservation(Reservation $reservation, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Seul le propriétaire peut annuler
        if ($reservation->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès interdit.');
        }

        // On ne peut annuler qu'une réservation à venir
        if ($reservation->getEndDate() < new \DateTimeImmutable('today')) {
            $this->addFlash('danger', 'Impossible d\'annuler une réservation passée.');
            return $this->redirectToRoute('app_profile');
        }

        $em->remove($reservation);
        $em->flush();

        $this->addFlash('success', 'Réservation annulée avec succès.');
        return $this->redirectToRoute('app_profile');
    }
}
