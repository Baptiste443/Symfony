<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\ProfileType;
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

class ProfileController extends AbstractController
{
    #[Route('/change-locale/{locale}', name: 'change_locale')]
    public function changeLocale(string $locale, Request $request): RedirectResponse
    {
        // Stocker la langue en session
        $request->getSession()->set('_locale', $locale);

        // Rediriger vers la page précédente ou vers la page d'accueil
        $referer = $request->headers->get('referer');
        return new RedirectResponse($referer ?: $this->generateUrl('home'));
    }

    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $em): Response
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

        return $this->render('profile/index.html.twig', [
            'profileForm' => $profileForm,
            'passwordForm' => $passwordForm,
        ]);
    }

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
        ]);
    }

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
}
