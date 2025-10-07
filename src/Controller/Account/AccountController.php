<?php
declare(strict_types=1);

namespace App\Controller\Account;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\AccountOrdersProvider;
use App\Repository\OrderStatusRepository;

#[Route('/account')]
final class AccountController extends AbstractController
{
    #[Route('', name: 'account_index', methods: ['GET'])]
    public function index(Request $request, AccountOrdersProvider $provider, OrderStatusRepository $statusRepo): Response
    {
        // Страница доступна только при IS_AUTHENTICATED_FULLY (см. security.yaml)
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $limit = (int)($request->query->get('limit', 20));
        $page = (int)($request->query->get('page', 1));
        if ($limit <= 0) { $limit = 20; }
        if ($limit > 100) { $limit = 100; }
        if ($page <= 0) { $page = 1; }
        $offset = ($page - 1) * $limit;

        $data = $provider->getOrdersWithProductMap($user, $limit, $offset);

        // Преобразуем справочник статусов: id -> name
        $statusNameById = [];
        foreach ($statusRepo->findAll() as $s) {
            $id = method_exists($s, 'getId') ? $s->getId() : null;
            $name = method_exists($s, 'getName') ? $s->getName() : null;
            if ($id !== null && $name) {
                $statusNameById[(string)$id] = (string)$name;
            }
        }

        return $this->render('account/index.html.twig', [
            'orders' => $data['orders'] ?? [],
            'productMap' => $data['productMap'] ?? [],
            'statusNameById' => $statusNameById,
            'page' => $page,
            'limit' => $limit,
        ]);
    }
}


