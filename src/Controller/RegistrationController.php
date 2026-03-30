<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

/**
 * Contrôleur d'inscription des nouveaux utilisateurs.
 * Gère la création de compte (email + mot de passe) et la vérification
 * de l'adresse email via un lien signé envoyé par email.
 */
class RegistrationController extends AbstractController
{
    /**
     * Injection d'EmailVerifier via le constructeur.
     * EmailVerifier est un service maison (src/Security/EmailVerifier.php)
     * qui s'appuie sur symfonycasts/verify-email-bundle pour générer et
     * valider les liens de vérification signés.
     */
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    /**
     * Affiche et traite le formulaire d'inscription.
     *
     * En GET : affiche le formulaire vide (email + mot de passe).
     *
     * En POST :
     *  1. Hache le mot de passe en clair via UserPasswordHasherInterface.
     *  2. Persiste le nouvel utilisateur en BDD (isVerified = false par défaut).
     *  3. Génère une URL signée et l'envoie par email (lien de vérification).
     *  4. Connecte automatiquement l'utilisateur via $security->login()
     *     (firewall 'main', mécanisme 'form_login') pour éviter de le
     *     rediriger vers la page de connexion après inscription.
     *
     * Route : GET|POST /register
     */
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // Hachage du mot de passe en clair avant persistance
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            // Génération et envoi de l'URL signée de vérification d'email
            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                (new TemplatedEmail())
                    ->from(new Address('contact@magicvehicles.com', 'Magic Vehicles'))
                    ->to((string) $user->getEmail())
                    ->subject('Confirmez votre adresse email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            // Connexion automatique après inscription pour améliorer l'expérience utilisateur
            return $security->login($user, 'form_login', 'main');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    /**
     * Valide le lien de vérification d'email reçu par l'utilisateur.
     *
     * Ce lien contient un paramètre ?id= (identifiant de l'utilisateur)
     * et une signature HMAC générée par symfonycasts/verify-email-bundle.
     *
     * Comportement :
     *  - Si l'id est absent ou l'utilisateur introuvable → redirection vers l'inscription.
     *  - Si la signature est invalide ou expirée → flash d'erreur et redirection.
     *  - Si tout est valide → isVerified passe à true en BDD, flash de succès.
     *
     * Route : GET /verify/email
     */
    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, UserRepository $userRepository): Response
    {
        $id = $request->query->get('id');

        // Pas d'id dans l'URL → lien invalide
        if (null === $id) {
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($id);

        // Utilisateur introuvable en BDD → lien invalide
        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        // Validation de l'URL signée : met isVerified à true et flush si valide
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            // Le bundle traduit le message d'erreur via le catalogue VerifyEmailBundle
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        $this->addFlash('success', 'Votre adresse email a bien été vérifiée.');

        return $this->redirectToRoute('app_register');
    }
}
