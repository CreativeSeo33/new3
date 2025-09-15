<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use Symfony\Component\Lock\LockFactory;

/**
 * Исключение, бросаемое при невозможности захватить блокировку корзины
 *
 * AI-META v1
 * role: Исключение для сигнализации об отказе в блокировке корзины
 * module: Cart
 * dependsOn:
 *   - TODO: Нет прямых зависимостей
 * invariants:
 *   - Сообщение/задержка retryAfterMs используются клиентом для Retry-After
 * transaction: none
 * lastUpdated: 2025-09-15
 */
final class CartLockException extends \RuntimeException
{
    private int $retryAfterMs;

    public function __construct(string $message = 'Cart is busy, try again', int $retryAfterMs = 150)
    {
        parent::__construct($message);
        $this->retryAfterMs = $retryAfterMs;
    }

    public function getRetryAfterMs(): int
    {
        return $this->retryAfterMs;
    }
}

final class CartLockService
{
	/**
	 * AI-META v1
	 * role: Неблокирующие локи корзины с ретраями и джиттером
	 * module: Cart
	 * dependsOn:
	 *   - Symfony\Component\Lock\LockFactory
	 * invariants:
	 *   - Критическая секция ограничена; при исчерпании попыток выбрасывается CartLockException
	 * transaction: none
	 * lastUpdated: 2025-09-15
	 */
	public function __construct(private LockFactory $lockFactory) {}

	/**
	 * Неблокирующий захват блокировки с ретраями и джиттером
	 *
	 * @param Cart $cart Корзина для блокировки
	 * @param callable $callback Код, выполняемый под блокировкой
	 * @param array $opts Настройки: ttl(float)=3.0, attempts(int)=3, minSleepMs=25, maxSleepMs=120
	 * @return mixed Результат выполнения callback
	 * @throws CartLockException Если не удалось захватить блокировку после всех попыток
	 */
	public function withCartLock(Cart $cart, callable $callback, array $opts = []): mixed
	{
		$ttl       = (float)($opts['ttl'] ?? 3.0);
		$attempts  = (int)($opts['attempts'] ?? 3);
		$minSleep  = (int)($opts['minSleepMs'] ?? 25);
		$maxSleep  = (int)($opts['maxSleepMs'] ?? 120);

		$key = 'cart:' . $cart->getIdString();
		$lock = $this->lockFactory->createLock($key, $ttl);

		for ($i = 0; $i < $attempts; $i++) {
			if ($lock->acquire(false)) {
				try {
					return $callback();
				} finally {
					$lock->release();
				}
			}

			// Джиттер между попытками
			if ($i < $attempts - 1) {
				usleep(random_int($minSleep, $maxSleep) * 1000);
			}
		}

		throw new CartLockException(
			'Cart is busy, try again',
			random_int(100, 300) // Случайная задержка для retry-after
		);
	}

	/**
	 * Устаревший метод для обратной совместимости
	 * @deprecated Используйте withCartLock с опциями
	 */
	public function withCartLockLegacy(Cart $cart, callable $callback): void
	{
		$this->withCartLock($cart, $callback, ['ttl' => 10.0, 'attempts' => 1]);
	}
}


