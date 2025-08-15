<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\PaginationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class ConfigController extends AbstractController
{
    #[Route('/api/config/pagination', name: 'api_config_pagination', methods: ['GET'])]
    public function getPaginationConfig(PaginationService $pagination, Request $request): JsonResponse
    {
        $data = [
            'itemsPerPageOptions' => $pagination->getAllowedItemsPerPage(),
            'defaultItemsPerPage' => $pagination->getDefaultItemsPerPage(),
        ];
        $response = new JsonResponse($data);
        $response->setPublic();
        $response->setMaxAge(86400); // 1 day
        $response->setSharedMaxAge(86400);
        $response->setEtag(sha1(json_encode($data)));
        if ($response->isNotModified($request)) {
            return $response;
        }
        return $response;
    }

    #[Route('/api/config/pagination/city', name: 'api_config_pagination_city', methods: ['GET'])]
    public function getCityPaginationConfig(PaginationService $pagination, Request $request): JsonResponse
    {
        $data = [
            'itemsPerPageOptions' => $pagination->getCityAllowedItemsPerPage(),
            'defaultItemsPerPage' => $pagination->getCityDefaultItemsPerPage(),
        ];
        $response = new JsonResponse($data);
        $response->setPublic();
        $response->setMaxAge(86400); // 1 day
        $response->setSharedMaxAge(86400);
        $response->setEtag(sha1(json_encode($data)));
        if ($response->isNotModified($request)) {
            return $response;
        }
        return $response;
    }

    #[Route('/api/config/pagination/pvz', name: 'api_config_pagination_pvz', methods: ['GET'])]
    public function getPvzPaginationConfig(PaginationService $pagination, Request $request): JsonResponse
    {
        $data = [
            'itemsPerPageOptions' => $pagination->getPvzAllowedItemsPerPage(),
            'defaultItemsPerPage' => $pagination->getPvzDefaultItemsPerPage(),
        ];
        $response = new JsonResponse($data);
        $response->setPublic();
        $response->setMaxAge(86400); // 1 day
        $response->setSharedMaxAge(86400);
        $response->setEtag(sha1(json_encode($data)));
        if ($response->isNotModified($request)) {
            return $response;
        }
        return $response;
    }
}


