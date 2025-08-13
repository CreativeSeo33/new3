<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/admin/login', name: 'admin_login_redirect_get', methods: ['GET'])]
    public function loginRedirectGet(): Response
    {
        return $this->redirectToRoute('app_login');
    }

    #[Route('/admin/login', name: 'admin_login_redirect_post', methods: ['POST'])]
    public function loginRedirectPost(): Response
    {
        // Preserve POST method for Symfony Security to handle credentials on /login
        return $this->redirectToRoute('app_login', [], 307);
    }
}


