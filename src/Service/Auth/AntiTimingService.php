<?php
declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class AntiTimingService
{
    public function __construct(private readonly ParameterBagInterface $params) {}

    public function sleepOnFailure(): void
    {
        try {
            $min = (int) $this->params->get('app.auth.failure_delay_ms_min');
            $max = (int) $this->params->get('app.auth.failure_delay_ms_max');
            if ($max < $min) { $max = $min; }
            $ms = random_int($min, $max);
            usleep($ms * 1000);
        } catch (\Throwable) {
            // ignore
        }
    }
}


