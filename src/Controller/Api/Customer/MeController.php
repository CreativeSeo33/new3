<?php
declare(strict_types=1);

namespace App\Controller\Api\Customer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;

#[Route('/api/customer', name: 'customer_')]
final class MeController extends AbstractController
{
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var User $user */
        $user = $this->getUser();
        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'isVerified' => $user->isVerified(),
        ]);
    }

    #[Route('/orders', name: 'orders', methods: ['GET'])]
    public function myOrders(\Doctrine\ORM\EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var User $user */
        $user = $this->getUser();

        $qb = $em->createQueryBuilder()
            ->select('o', 'oc', 'od', 'op')
            ->from(\App\Entity\Order::class, 'o')
            ->leftJoin('o.customer', 'oc')
            ->leftJoin('o.delivery', 'od')
            ->leftJoin('o.products', 'op')
            ->where('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.dateAdded', 'DESC');

        $orders = $qb->getQuery()->getResult();

        $result = [];
        foreach ($orders as $o) {
            $row = [
                'id' => $o->getId(),
                'orderId' => $o->getOrderId(),
                'dateAdded' => $o->getDateAdded()?->format(DATE_ATOM),
                'status' => $o->getStatus(),
                'total' => $o->getTotal(),
            ];
            $c = $o->getCustomer();
            if ($c) {
                $row['customer'] = [
                    'name' => $c->getName(),
                    'phone' => $c->getPhone(),
                    'email' => $c->getEmail(),
                ];
            }
            $d = $o->getDelivery();
            if ($d) {
                $row['delivery'] = [
                    'type' => method_exists($d, 'getType') ? $d->getType() : null,
                    'city' => method_exists($d, 'getCity') ? $d->getCity() : null,
                    'cost' => method_exists($d, 'getCost') ? $d->getCost() : null,
                ];
            }
            $items = [];
            foreach ($o->getProducts() as $op) {
                $items[] = [
                    'productId' => $op->getProductId(),
                    'name' => $op->getProductName(),
                    'price' => $op->getPrice(),
                    'quantity' => $op->getQuantity(),
                    'salePrice' => $op->getSalePrice(),
                ];
            }
            $row['items'] = $items;
            $result[] = $row;
        }

        return new JsonResponse(['data' => $result]);
    }
}


