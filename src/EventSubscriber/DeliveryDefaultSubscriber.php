<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Repository\DeliveryTypeRepository;
use App\Service\DeliveryContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class DeliveryDefaultSubscriber implements EventSubscriberInterface
{
    private const API_PATH_PREFIX = '/api';
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly DeliveryContext $deliveryContext,
        private readonly DeliveryTypeRepository $deliveryTypes,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        // Не трогаем сессию на API-запросах (API Platform помечает их stateless)
        if (str_starts_with($request->getPathInfo(), self::API_PATH_PREFIX)) {
            return;
        }
        $session = $request->getSession();
        if ($session === null) {
            return;
        }

        // Уже выбран способ доставки в новой структуре?
        $ctx = $this->deliveryContext->get();
        if (isset($ctx['methodCode']) && is_string($ctx['methodCode']) && $ctx['methodCode'] !== '') {
            return;
        }

        // Легаси-ключи в корне сессии
        $legacy = $session->get('delivery', []);
        if (is_array($legacy)) {
            $legacyMethod = (string)($legacy['methodCode'] ?? ($legacy['type'] ?? ''));
            if ($legacyMethod !== '') {
                // Синхронизируем в новую структуру для единообразия
                $this->deliveryContext->setMethod($legacyMethod);
                return;
            }
        }

        // Найдём дефолтный активный способ доставки
        $default = $this->deliveryTypes->findOneBy(['default' => true, 'active' => true]);
        if ($default === null) {
            return;
        }

        $code = (string) $default->getCode();
        if ($code === '') {
            return;
        }

        // Установим в новую структуру (используется приложением)
        $this->deliveryContext->setMethod($code);

        // Дублируем в легаси для совместимости с требованием delivery.type
        $legacy['type'] = $code;
        $legacy['methodCode'] = $code;
        $session->set('delivery', $legacy);
    }
}


