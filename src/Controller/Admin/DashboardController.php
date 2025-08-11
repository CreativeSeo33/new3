<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
final class DashboardController extends AbstractController
{
    #[Route('{vueRouting?}', name: 'dashboard', requirements: ['vueRouting' => '.*'], methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('admin/base.html.twig');
    }

    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/base.html.twig');
    }
}


