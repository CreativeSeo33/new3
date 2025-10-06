<?php
declare(strict_types=1);

namespace App\Controller\Site;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthPageController extends AbstractController
{
    #[Route('/auth/login', name: 'customer_login', methods: ['GET'])]
    public function login(): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('account_index');
        }

        return $this->render('security/customer_login.html.twig');
    }

    #[Route('/auth/register', name: 'customer_register', methods: ['GET'])]
    public function register(): Response
    {
        return $this->render('security/register.html.twig');
    }

    #[Route('/auth/password/request', name: 'customer_password_request', methods: ['GET'])]
    public function passwordRequest(): Response
    {
        return $this->render('security/password_request.html.twig');
    }

    #[Route('/auth/password/reset', name: 'customer_password_reset', methods: ['GET'])]
    public function passwordReset(): Response
    {
        return $this->render('security/password_reset.html.twig');
    }
}


