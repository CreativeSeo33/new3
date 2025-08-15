<?php
declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Entity\PvzPoints;
use App\Repository\DeliveryTypeRepository;
use App\Service\DeliveryContext;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DeliveryPageController extends AbstractController
{
    public function __construct(
        private readonly DeliveryTypeRepository $deliveryTypes,
        private readonly DeliveryContext $deliveryContext,
        private readonly ManagerRegistry $registry,
    ) {}

    #[Route('/delivery', name: 'delivery_page', methods: ['GET'])]
    public function index(): Response
    {
        $types = $this->deliveryTypes->findBy(['active' => true], ['sortOrder' => 'ASC']);
        $ctx = $this->deliveryContext->get();
        $selectedCode = $ctx['methodCode'] ?? null;

        $pvzPoints = [];
        if ($selectedCode === 'pvz' && !empty($ctx['cityName'])) {
            $repo = $this->registry->getRepository(PvzPoints::class);
            $pvzPoints = $repo->findBy(['city' => $ctx['cityName']], ['name' => 'ASC']);
        }

        return $this->render('catalog/delivery/index.html.twig', [
            'types' => $types,
            'pvzPoints' => $pvzPoints,
            'deliveryContext' => $ctx,
            'breadcrumbs' => [
                ['label' => 'Доставка'],
            ],
            'navbar' => [
                ['label' => 'Доставка', 'url' => $this->generateUrl('delivery_page')],
            ],
        ]);
    }
}


