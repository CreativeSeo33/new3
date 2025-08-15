<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\PaginationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class ConfigController extends AbstractController
{
    #[Route('/api/config/pagination', name: 'api_config_pagination', methods: ['GET'])]
    public function getPaginationConfig(PaginationService $pagination): JsonResponse
    {
        return new JsonResponse([
            'itemsPerPageOptions' => $pagination->getAllowedItemsPerPage(),
            'defaultItemsPerPage' => $pagination->getDefaultItemsPerPage(),
        ]);
    }

    #[Route('/api/config/pagination/city', name: 'api_config_pagination_city', methods: ['GET'])]
    public function getCityPaginationConfig(PaginationService $pagination): JsonResponse
    {
        return new JsonResponse([
            'itemsPerPageOptions' => $pagination->getCityAllowedItemsPerPage(),
            'defaultItemsPerPage' => $pagination->getCityDefaultItemsPerPage(),
        ]);
    }
}


