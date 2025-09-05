<?php
declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\Service\ProductCopyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class ProductCopyController extends AbstractController
{
    public function __construct(
        private ProductCopyService $copyService
    ) {}

    #[Route('/api/admin/products/{id}/copy', name: 'admin_api_product_copy', methods: ['POST'])]
    public function copyProduct(int $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true) ?? [];

            // Валидация входных данных
            $options = $this->validateAndPrepareOptions($data);

            // Копирование товара
            $newProduct = $this->copyService->copyProduct($id, $options);

            return $this->json([
                'success' => true,
                'message' => 'Товар успешно скопирован',
                'data' => [
                    'id' => $newProduct->getId(),
                    'name' => $newProduct->getName(),
                    'code' => $newProduct->getCode()?->toRfc4122(),
                    'type' => $newProduct->getType(),
                ]
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Произошла ошибка при копировании товара'
            ], 500);
        }
    }

    private function validateAndPrepareOptions(array $data): array
    {
        $options = [];

        // Валидация опций копирования
        if (isset($data['copyCategories'])) {
            $options['copyCategories'] = (bool)$data['copyCategories'];
        }

        if (isset($data['copyImages'])) {
            $options['copyImages'] = (bool)$data['copyImages'];
        }

        if (isset($data['copyAttributes'])) {
            $options['copyAttributes'] = (bool)$data['copyAttributes'];
        }

        if (isset($data['copyOptions'])) {
            $options['copyOptions'] = (bool)$data['copyOptions'];
        }

        if (isset($data['copySeo'])) {
            $options['copySeo'] = (bool)$data['copySeo'];
        }

        if (isset($data['namePrefix'])) {
            $options['namePrefix'] = (string)$data['namePrefix'];
        }

        if (isset($data['setInactive'])) {
            $options['setInactive'] = (bool)$data['setInactive'];
        }

        if (isset($data['changeType'])) {
            $allowedTypes = ['simple', 'variable'];
            if (!in_array($data['changeType'], $allowedTypes)) {
                throw new \InvalidArgumentException('Недопустимый тип товара');
            }
            $options['changeType'] = $data['changeType'];
        }

        return $options;
    }
}
