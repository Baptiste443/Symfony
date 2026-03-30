<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Contrôleur de sécurité — gère la connexion et la déconnexion.
 *
 * La véritable logique d'authentification est prise en charge par Symfony Security
 * (firewall 'main' configuré dans security.yaml). Ces méthodes servent uniquement
 * de points d'entrée (routes) pour les templates et la configuration du firewall.
 */
class SecurityController extends AbstractController
{
    /**
     * Affiche le formulaire de connexion.
     *
     * AuthenticationUtils est injecté par Symfony pour récupérer :
     *  - la dernière erreur d'authentification (mauvais mot de passe, compte inexistant...)
     *  - le dernier identifiant saisi (pour pré-remplir le champ email)
     *
     * Après soumission du formulaire, c'est le firewall (security.yaml) qui
     * intercepte la requête POST et gère l'authentification — cette méthode
     * n'est donc jamais appelée en POST.
     *
     * Route : GET /login
     */
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Récupère l'erreur d'authentification s'il y en a une (null sinon)
        $error = $authenticationUtils->getLastAuthenticationError();

        // Récupère le dernier email saisi pour pré-remplir le formulaire
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Point d'entrée de la déconnexion.
     *
     * Cette méthode ne sera jamais exécutée : la route /logout est interceptée
     * directement par le firewall Symfony (clé 'logout' dans security.yaml)
     * avant d'atteindre le contrôleur. L'exception LogicException est là pour
     * signaler clairement qu'il s'agit d'un comportement intentionnel.
     *
     * Route : GET /logout
     */
    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Cette méthode est interceptée par le firewall Symfony — elle ne sera jamais exécutée.');
    }
}
