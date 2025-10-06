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
        return $this->render('security/customer_login.html.twig');
    }

    #[Route('/auth/register', name: 'customer_register', methods: ['GET'])]
    public function register(): Response
    {
        return $this->render('security/register.html.twig');
    }
}


