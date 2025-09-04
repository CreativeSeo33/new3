<?php
declare(strict_types=1);

namespace App\Controller\Debug;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

final class SessionDebugController extends AbstractController
{
    #[Route(path: '/_debug/session', name: 'app_debug_session', methods: ['GET'])]
    public function __invoke(SessionInterface $session): JsonResponse
    {
        if (!(bool) $this->getParameter('kernel.debug')) {
            throw $this->createNotFoundException();
        }

        $metadataBag = $session->getMetadataBag();
        $data = [
            'id' => $session->getId(),
            'name' => $session->getName(),
            'started' => $session->isStarted(),
            'all' => $session->all(),
            'metadata' => [
                'created' => $metadataBag->getCreated(),
                'last_used' => $metadataBag->getLastUsed(),
                'lifetime' => $metadataBag->getLifetime(),
            ],
        ];

        dump($data);

        return $this->json($data);
    }

    #[Route(path: '/_debug/session-page', name: 'app_debug_session_page', methods: ['GET'])]
    public function page(SessionInterface $session): Response
    {
        if (!(bool) $this->getParameter('kernel.debug')) {
            throw $this->createNotFoundException();
        }

        $metadataBag = $session->getMetadataBag();
        $sessionData = [
            'id' => $session->getId(),
            'name' => $session->getName(),
            'started' => $session->isStarted(),
            'all' => $session->all(),
            'metadata' => [
                'created' => $metadataBag->getCreated(),
                'last_used' => $metadataBag->getLastUsed(),
                'lifetime' => $metadataBag->getLifetime(),
            ],
        ];

        return $this->render('debug/session.html.twig', [
            'session' => $sessionData,
        ]);
    }

    #[Route(path: '/_debug/session/clear', name: 'app_debug_session_clear', methods: ['POST'])]
    public function clear(SessionInterface $session): RedirectResponse
    {
        if (!(bool) $this->getParameter('kernel.debug')) {
            throw $this->createNotFoundException();
        }

        // Полностью очищаем сессию
        $session->clear();

        // Добавляем flash сообщение для обратной связи
        $this->addFlash('success', 'Сессия успешно очищена');

        return $this->redirectToRoute('app_debug_session_page');
    }

    #[Route(path: '/_debug/session/destroy', name: 'app_debug_session_destroy', methods: ['POST'])]
    public function destroy(SessionInterface $session): RedirectResponse
    {
        if (!(bool) $this->getParameter('kernel.debug')) {
            throw $this->createNotFoundException();
        }

        // Уничтожаем сессию (invalidate)
        $session->invalidate();

        // Добавляем flash сообщение для обратной связи
        $this->addFlash('success', 'Сессия уничтожена');

        return $this->redirectToRoute('app_debug_session_page');
    }

    #[Route(path: '/_debug/session/regenerate', name: 'app_debug_session_regenerate', methods: ['POST'])]
    public function regenerate(SessionInterface $session): RedirectResponse
    {
        if (!(bool) $this->getParameter('kernel.debug')) {
            throw $this->createNotFoundException();
        }

        // Регенерируем ID сессии
        $session->migrate(true);

        // Добавляем flash сообщение для обратной связи
        $this->addFlash('success', 'ID сессии регенерирован');

        return $this->redirectToRoute('app_debug_session_page');
    }
}


