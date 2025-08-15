<?php
declare(strict_types=1);

namespace App\Controller\Debug;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
}


