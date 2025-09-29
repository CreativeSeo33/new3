<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\FacetConfig;
use App\Repository\FacetConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class FacetConfigController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FacetConfigRepository $repo,
        private readonly int $defaultValuesLimit = 20,
    ) {}

    #[Route('/api/admin/facets/config', name: 'admin_facets_config_get', methods: ['GET'])]
    public function getConfig(Request $request): JsonResponse
    {
        $category = $request->query->get('category');
        $cfg = null;
        if ($category === 'global') {
            $cfg = $this->repo->findOneBy(['scope' => FacetConfig::SCOPE_GLOBAL]);
        } elseif (is_numeric((string)$category)) {
            $cfg = $this->repo->createQueryBuilder('c')
                ->leftJoin('c.category', 'cat')
                ->andWhere('c.scope = :scope')
                ->andWhere('cat.id = :cid')
                ->setParameter('scope', FacetConfig::SCOPE_CATEGORY)
                ->setParameter('cid', (int)$category)
                ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        }

        if (!$cfg instanceof FacetConfig) {
            return $this->json(['message' => 'not_found'], 404);
        }

        return $this->json([
            'scope' => $cfg->getScope(),
            'categoryId' => $cfg->getCategory()?->getId(),
            'attributes' => $cfg->getAttributes(),
            'options' => $cfg->getOptions(),
            'showZeros' => $cfg->isShowZeros(),
            'collapsedByDefault' => $cfg->isCollapsedByDefault(),
            'valuesLimit' => $cfg->getValuesLimit(),
            'valuesSort' => $cfg->getValuesSort(),
        ]);
    }

    #[Route('/api/admin/facets/config', name: 'admin_facets_config_put', methods: ['PUT'])]
    public function putConfig(Request $request): JsonResponse
    {
        $data = json_decode((string)$request->getContent(), true) ?: [];

        $scope = (string)($data['scope'] ?? FacetConfig::SCOPE_CATEGORY);
        $categoryId = $data['categoryId'] ?? null;

        if (!in_array($scope, [FacetConfig::SCOPE_GLOBAL, FacetConfig::SCOPE_CATEGORY], true)) {
            return $this->json(['message' => 'invalid_scope'], 400);
        }

        $repo = $this->repo;
        if ($scope === FacetConfig::SCOPE_GLOBAL) {
            $cfg = $repo->findOneBy(['scope' => FacetConfig::SCOPE_GLOBAL]) ?? new FacetConfig();
            $cfg->setScope(FacetConfig::SCOPE_GLOBAL)->setCategory(null);
        } else {
            if (!is_int($categoryId)) {
                return $this->json(['message' => 'category_required'], 400);
            }
            $cfg = $repo->createQueryBuilder('c')
                ->leftJoin('c.category', 'cat')
                ->andWhere('c.scope = :scope')
                ->andWhere('cat.id = :cid')
                ->setParameter('scope', FacetConfig::SCOPE_CATEGORY)
                ->setParameter('cid', $categoryId)
                ->setMaxResults(1)->getQuery()->getOneOrNullResult() ?? new FacetConfig();
            $cfg->setScope(FacetConfig::SCOPE_CATEGORY);
            $catRef = $this->em->getReference(Category::class, $categoryId);
            $cfg->setCategory($catRef);
        }

        $cfg->setAttributes($data['attributes'] ?? [])
            ->setOptions($data['options'] ?? [])
            ->setShowZeros((bool)($data['showZeros'] ?? false))
            ->setCollapsedByDefault((bool)($data['collapsedByDefault'] ?? true))
            ->setValuesLimit((int)($data['valuesLimit'] ?? $this->defaultValuesLimit))
            ->setValuesSort((string)($data['valuesSort'] ?? 'popularity'));

        $this->em->persist($cfg);
        $this->em->flush();

        return $this->json(['status' => 'ok', 'id' => $cfg->getId()]);
    }
}


