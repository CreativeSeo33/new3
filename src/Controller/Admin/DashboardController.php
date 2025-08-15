<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\PaginationService;

#[Route('/admin', name: 'admin_')]
final class DashboardController extends AbstractController
{
    #[Route('/{vueRouting}', name: 'dashboard', requirements: ['vueRouting' => '.*'], defaults: ['vueRouting' => ''], methods: ['GET'])]
    public function dashboard(PaginationService $pagination): Response
    {
        return $this->render('admin/base.html.twig', [
            'pagination_config_default' => [
                'itemsPerPageOptions' => $pagination->getAllowedItemsPerPage(),
                'defaultItemsPerPage' => $pagination->getDefaultItemsPerPage(),
            ],
            'pagination_config_city' => [
                'itemsPerPageOptions' => $pagination->getCityAllowedItemsPerPage(),
                'defaultItemsPerPage' => $pagination->getCityDefaultItemsPerPage(),
            ],
        ]);
    }
}


