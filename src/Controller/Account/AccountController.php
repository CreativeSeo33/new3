<?php
declare(strict_types=1);

namespace App\Controller\Account;

use App\Entity\Order;
use App\Dto\AccountOrdersQuery;
use App\Entity\User;
use App\Repository\OrderStatusRepository;
use App\Service\AccountOrdersProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/account')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class AccountController extends AbstractController
{
    #[Route('', name: 'account_index', methods: ['GET'])]
    public function index(Request $request, AccountOrdersProvider $provider, OrderStatusRepository $statusRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $query = AccountOrdersQuery::fromRequest($request);
        $data = $provider->getOrdersWithProductMap($user, $query->getLimit(), $query->getOffset());

        return $this->render('account/index.html.twig', [
            'orders' => $data['orders'] ?? [],
            'productMap' => $data['productMap'] ?? [],
            'statusNameById' => $this->collectStatusNames($statusRepo),
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/profile/update', name: 'account_profile_update', methods: ['POST'])]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();

        $token = new CsrfToken('account_profile_update', (string)$request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Неверный токен безопасности. Повторите попытку.');
            return $this->redirectToRoute('account_index');
        }

        $firstName = $request->request->get('firstName');
        $phone = $request->request->get('phone');
        $email = $request->request->get('email');

        // Простейшая валидация на сервере (базовая; бизнес-правила должны быть валидацией слоя сервиса/форм)
        if ($email !== null) {
            $email = is_string($email) ? trim($email) : '';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Некорректный email.');
                return $this->redirectToRoute('account_index');
            }
        }

        $user->setFirstName(is_string($firstName) ? $firstName : null);
        $user->setPhone(is_string($phone) ? $phone : null);
        $user->setEmail(is_string($email) ? $email : null);

        $em->persist($user);
        $em->flush();

        $this->addFlash('success', 'Данные аккаунта обновлены.');
        return $this->redirectToRoute('account_index');
    }

    #[Route('/orders/{orderId}/cancel', name: 'account_order_cancel', methods: ['POST'])]
    public function cancelOrder(
        int $orderId,
        Request $request,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();

        $token = new CsrfToken('order_cancel_' . (string) $orderId, (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Неверный токен безопасности.');
            return $this->redirectToRoute('account_index');
        }

        $order = $em->getRepository(Order::class)->findOneBy([
            'orderId' => $orderId,
            'user' => $user,
        ]);

        if ($order === null) {
            $this->addFlash('error', 'Заказ не найден.');
            return $this->redirectToRoute('account_index');
        }

        if ($order->getStatus() === Order::STATUS_CANCELLED) {
            $this->addFlash('info', 'Заказ уже отменён.');
            return $this->redirectToRoute('account_index');
        }

        $order->setStatus(Order::STATUS_CANCELLED);
        $em->flush();

        $this->addFlash('success', 'Заказ отменён.');
        return $this->redirectToRoute('account_index');
    }

    /**
     * @return array<string, string>
     */
    private function collectStatusNames(OrderStatusRepository $statusRepo): array
    {
        $statusNameById = [];
        foreach ($statusRepo->findAll() as $status) {
            $id = $status->getId();
            $name = $status->getName();
            if ($id === null || $name === null || $name === '') {
                continue;
            }
            $statusNameById[(string) $id] = (string) $name;
        }

        return $statusNameById;
    }
}


