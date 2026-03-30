<?php

namespace App\Controller;

use App\Entity\Feature;
use App\Entity\Reservation;
use App\Entity\TypeVehicle;
use App\Entity\Vehicle;
use App\Form\VehicleType;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contrôleur principal de l'application.
 * Gère l'affichage, le filtrage, le CRUD des véhicules
 * ainsi que le processus complet de réservation.
 */
final class VehicleController extends AbstractController
{
    /**
     * Page d'accueil de l'application.
     * Affiche simplement la vue d'accueil (vehicle/index.html.twig).
     *
     * Route : GET /
     */
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('vehicle/index.html.twig');
    }

    /**
     * Liste paginée des véhicules avec filtres.

     * Route : GET /vehicles
     */
    #[Route('/vehicles', name: 'app_vehicle')]
    public function vehicles(Request $request, EntityManagerInterface $entityManager, PaginatorInterface $paginator): Response
    {
        $queryBuilder = $entityManager->getRepository(Vehicle::class)->createQueryBuilder('v');

        $form = $this->createFormBuilder(null, ['method' => 'GET', 'csrf_protection' => false])
            ->add('type', EntityType::class, [
                'class' => TypeVehicle::class,
                'choice_label' => 'name',
                'placeholder' => 'Tous',
                'required' => false,
                'label' => 'Type de véhicule'
            ])
            ->add('capacity', IntegerType::class, [
                'required' => false,
                'label' => 'Capacité du Véhicule',
                'constraints' => [new Assert\GreaterThan(0), new Assert\LessThan(10)]
            ])
            ->add('priceMin', IntegerType::class, [
                'required' => false,
                'label' => 'Prix minimum',
                'constraints' => [new Assert\GreaterThan(0)]
            ])
            ->add('priceMax', IntegerType::class, [
                'required' => false,
                'label' => 'Prix maximum',
                'constraints' => [new Assert\GreaterThan(0)]
            ])
            ->add('features', EntityType::class, [
                'class' => Feature::class,
                'choice_label' => 'name',
                'placeholder' => 'Tous',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'label' => 'Options du véhicule'
            ])
            ->add('dateStart', DateType::class, [
                'required' => false,
                'label' => 'Date de début',
                'widget' => 'single_text',
            ])
            ->add('dateEnd', DateType::class, [
                'required' => false,
                'label' => 'Date de fin',
                'widget' => 'single_text',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Filtrer',
                'attr' => ['class' => 'btn btn-primary']
            ])
            ->add('reset', \Symfony\Component\Form\Extension\Core\Type\ResetType::class, [
                'label' => 'Réinitialiser',
                'attr' => ['class' => 'btn btn-secondary']
            ])
            ->getForm();
        $form->handleRequest($request);

        $dateStart = null;
        $dateEnd = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            if ($data['type']) {
                $queryBuilder->andWhere('v.typeVehicle = :type')
                    ->setParameter('type', $data['type']);
            }
            if (!empty($data['features'])) {
              
                foreach ($data['features'] as $index => $feature) {
                    $queryBuilder
                        ->andWhere(':feature' . $index . ' MEMBER OF v.features')
                        ->setParameter('feature' . $index, $feature);
                }
            }
            if ($data['capacity']) {
                $queryBuilder->andWhere('v.capacity >= :capacity')
                    ->setParameter('capacity', $data['capacity']);
            }
            if ($data['priceMin']) {
                $queryBuilder->andWhere('v.price >= :priceMin')
                    ->setParameter('priceMin', $data['priceMin']);
            }
            if ($data['priceMax']) {
                $queryBuilder->andWhere('v.price <= :priceMax')
                    ->setParameter('priceMax', $data['priceMax']);
            }


            if ($data['dateStart'] && $data['dateEnd']) {
                $dateStart = $data['dateStart'];
                $dateEnd = $data['dateEnd'];

                $queryBuilder
                    ->andWhere('v.id NOT IN (
                        SELECT IDENTITY(r.vehicle) FROM App\Entity\Reservation r
                        WHERE r.startDate < :dateEnd AND r.endDate > :dateStart
                    )')
                    ->setParameter('dateStart', $dateStart)
                    ->setParameter('dateEnd', $dateEnd);
            }
        }

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            3 // nombre d'éléments par page
        );

        return $this->render('vehicle/vehicles.html.twig', [
            'vehicles' => $queryBuilder->getQuery()->getResult(),
            'pagination' => $pagination,
            'form' => $form,
            'dateStart' => $dateStart ? $dateStart->format('Y-m-d') : null,
            'dateEnd' => $dateEnd ? $dateEnd->format('Y-m-d') : null,
        ]);
    }

    /**
     * Page de réservation d'un véhicule (formulaire + traitement).
     * En GET : affiche le formulaire pré-rempli avec les dates transmises depuis
     * la liste des véhicules (paramètres ?dateStart=...&dateEnd=...).
     * En POST :
     *  1. Valide que les deux dates sont présentes et cohérentes (fin > début).
     *  2. Vérifie qu'aucune autre réservation ne chevauche la période demandée.
     *  3. Calcule le prix total (prix/jour × nombre de jours).
     *  4. Crée la réservation en BDD avec un token unique (bin2hex de 16 octets aléatoires).
     *  5. Envoie l'email de confirmation via Symfony Mailer.
     *  6. Redirige vers le bon de réservation.
     * Route : GET|POST /reservation/{id}
     */
    #[Route('/reservation/{id}', name: 'reservation_vehicle', methods: ['GET', 'POST'])]
    public function reservation(
        Vehicle $vehicle,
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {
        // L'utilisateur doit être connecté pour réserver
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Récupérer les dates depuis les paramètres GET (transmises depuis le filtre)
        $dateStartStr = $request->query->get('dateStart');
        $dateEndStr = $request->query->get('dateEnd');

        $dateStart = $dateStartStr ? new \DateTimeImmutable($dateStartStr) : null;
        $dateEnd = $dateEndStr ? new \DateTimeImmutable($dateEndStr) : null;

        if ($request->isMethod('POST')) {
            // Récupérer les dates depuis le formulaire POST
            $dateStartStr = $request->request->get('dateStart');
            $dateEndStr = $request->request->get('dateEnd');

            $dateStart = $dateStartStr ? new \DateTimeImmutable($dateStartStr) : null;
            $dateEnd = $dateEndStr ? new \DateTimeImmutable($dateEndStr) : null;

            // Validation : les deux dates doivent être présentes
            if (!$dateStart || !$dateEnd) {
                $this->addFlash('danger', 'Veuillez sélectionner les dates de début et de fin.');
                return $this->render('vehicle/reservation.html.twig', [
                    'vehicle' => $vehicle,
                    'dateStart' => $dateStartStr,
                    'dateEnd' => $dateEndStr,
                ]);
            }

            // Validation : la date de fin doit être postérieure à la date de début
            if ($dateEnd <= $dateStart) {
                $this->addFlash('danger', 'La date de fin doit être postérieure à la date de début.');
                return $this->render('vehicle/reservation.html.twig', [
                    'vehicle' => $vehicle,
                    'dateStart' => $dateStartStr,
                    'dateEnd' => $dateEndStr,
                ]);
            }

            // Vérification de disponibilité : cherche une réservation existante
            // qui chevauche la période demandée pour ce même véhicule
            $conflit = $em->getRepository(Reservation::class)->createQueryBuilder('r')
                ->where('r.vehicle = :vehicle')
                ->andWhere('r.startDate < :dateEnd')
                ->andWhere('r.endDate > :dateStart')
                ->setParameter('vehicle', $vehicle)
                ->setParameter('dateStart', $dateStart)
                ->setParameter('dateEnd', $dateEnd)
                ->getQuery()
                ->getOneOrNullResult();

            if ($conflit) {
                $this->addFlash('danger', 'Ce véhicule n\'est pas disponible sur cette période.');
                return $this->render('vehicle/reservation.html.twig', [
                    'vehicle' => $vehicle,
                    'dateStart' => $dateStartStr,
                    'dateEnd' => $dateEndStr,
                ]);
            }

            // Calcul du prix total : prix journalier × nombre de jours
            $nbJours = $dateStart->diff($dateEnd)->days;
            $totalPrice = (float) $vehicle->getPrice() * $nbJours;

            // Création et persistance de la réservation
            $reservation = new Reservation();
            $reservation->setVehicle($vehicle);
            $reservation->setUser($user);
            $reservation->setStartDate($dateStart);
            $reservation->setEndDate($dateEnd);
            $reservation->setTotalPrice((string) $totalPrice);
            // Token unique de 32 caractères hexadécimaux pour identifier la réservation
            $reservation->setConfirmationToken(bin2hex(random_bytes(16)));
            $reservation->setConfirmedAt(new \DateTimeImmutable());

            $em->persist($reservation);
            $em->flush();

            // Envoi de l'email de confirmation au client
            $email = (new TemplatedEmail())
                ->from(new Address('contact@magicvehicles.com', 'Magic Vehicles'))
                ->to((string) $user->getEmail())
                ->subject('Confirmation de votre réservation - Magic Vehicles')
                ->htmlTemplate('vehicle/reservation_email.html.twig')
                ->context(['reservation' => $reservation]);

            $mailer->send($email);

            $this->addFlash('success', 'Réservation confirmée ! Un email de confirmation vous a été envoyé.');
            return $this->redirectToRoute('reservation_bon', ['id' => $reservation->getId()]);
        }

        return $this->render('vehicle/reservation.html.twig', [
            'vehicle' => $vehicle,
            'dateStart' => $dateStart ? $dateStart->format('Y-m-d') : null,
            'dateEnd' => $dateEnd ? $dateEnd->format('Y-m-d') : null,
        ]);
    }

    /**
     * Affiche le bon de réservation imprimable.
     *
     * Page HTML stylisée avec @media print pour masquer navbar/footer à l'impression.
     * Un bouton "Imprimer" déclenche window.print() côté navigateur.
     * Seul le propriétaire de la réservation peut y accéder (vérification manuelle).
     *
     * Route : GET /reservation/bon/{id}
     */
    #[Route('/reservation/bon/{id}', name: 'reservation_bon', methods: ['GET'])]
    public function bon(Reservation $reservation): Response
    {
        // Seul l'utilisateur qui a réservé peut voir son bon
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès interdit.');
        }

        return $this->render('vehicle/bon_reservation.html.twig', [
            'reservation' => $reservation,
        ]);
    }

 
    #[Route('/details/{id}', name: 'details_vehicle')]
    public function details(Vehicle $vehicle, EntityManagerInterface $entityManager): Response
    {
        $features = $entityManager->getRepository(Feature::class)->findAll();
        return $this->render('vehicle/details.html.twig', [
            'vehicle' => $vehicle,
            'features' => $features
        ]);
    }

    /**
     * Création ou modification d'un véhicule (formulaire unique).
 
     * Traitements spécifiques à la soumission :
     *  - Upload d'image : déplacement dans /assets/img/, nom unique via uniqid()
     *  - Règles métier : un véhicule Utilitaire doit avoir "Caméra de recul",
     *    un 4x4 doit avoir "GPS" (erreurs ajoutées manuellement au formulaire)
     *  - isNew : permet d'afficher un message flash différent selon l'opération
     * Route : GET|POST /vehicle/edit_or_create/{id?}
     */
    #[Route('/vehicle/edit_or_create/{id?}', name: 'vehicle_edit_or_create')]
    public function editOrCreate(Request $request, EntityManagerInterface $entityManager, ?Vehicle $vehicle = null): Response
    {
        if (!$vehicle) {
            $vehicle = new Vehicle();
        }

        $form = $this->createForm(VehicleType::class, $vehicle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Permet d'afficher un flash différent pour la création vs la mise à jour
            $isNew = $vehicle->getId() === null;
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile instanceof UploadedFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/assets/img';
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move($uploadsDir, $newFilename);
                $vehicle->setImagePath('img/' . $newFilename);
            }

            if ($vehicle->getImagePath() == null) {
                $form->get('imageFile')->addError(new FormError("Veuillez télécharger une image valide (JPG ou PNG)."));
            }
            $typeVehicle = $vehicle->getTypeVehicle();
            if ($typeVehicle) {
                $featureNames = [];
                foreach ($vehicle->getFeatures() as $feature) {
                    $featureNames[] = $feature->getName();
                }

                if ($typeVehicle->getName() === 'Utilitaire' && !in_array('Caméra de recul', $featureNames)) {
                    $form->addError(new FormError('Un véhicule de type Utilitaire doit avoir la caractéristique "Caméra de recul".'));
                }

                if ($typeVehicle->getName() === '4x4' && !in_array('GPS', $featureNames)) {
                    $form->addError(new FormError('Un véhicule de type 4x4 doit avoir la caractéristique "GPS".'));
                }
            }

            if ($form->isValid()) {
                $entityManager->persist($vehicle);
                $entityManager->flush();
                $this->addFlash(
                    $isNew ? 'success' : 'warning',
                    $isNew ? 'Le véhicule a bien été créé.' : 'Le véhicule a bien été mis à jour.'
                );

                return $this->redirectToRoute('app_vehicle');
            }
        }

        return $this->render('vehicle/edit_or_create.html.twig', [
            'form' => $form,
            'vehicle' => $vehicle
        ]);
    }

 
    #[Route('/vehicle/confirm-delete/{id}', name: 'vehicle_confirm_delete', methods: ['GET', 'POST'])]
    public function confirmDelete(Request $request, EntityManagerInterface $entityManager, Vehicle $vehicle): Response
    {
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('vehicle_confirm_delete', ['id' => $vehicle->getId()]))
            ->setMethod('POST')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->remove($vehicle);
            $entityManager->flush();
            $this->addFlash('danger', 'Le véhicule a bien été supprimé.');
            return $this->redirectToRoute('app_vehicle');
        }

        return $this->render('vehicle/confirm_delete.html.twig', [
            'vehicle' => $vehicle,
            'form' => $form->createView(),
        ]);
    }
}
