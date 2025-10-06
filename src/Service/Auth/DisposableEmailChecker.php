<?php
declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class DisposableEmailChecker
{
    private array $denyList = [];

    public function __construct(ParameterBagInterface $params)
    {
        $path = (string) ($params->get('env(AUTH_DISPOSABLE_DOMAINS_PATH)') ?? '');
        if ($path && is_file($path)) {
            $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $this->denyList = array_map('strtolower', array_map('trim', $lines));
        }
    }

    public function isDisposable(string $email): bool
    {
        $domain = strtolower(trim((string) substr(strrchr($email, '@') ?: '', 1)));
        if ($domain === '') return false;
        return in_array($domain, $this->denyList, true);
    }
}


