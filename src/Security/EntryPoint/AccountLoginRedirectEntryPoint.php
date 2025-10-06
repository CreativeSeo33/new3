<?php
declare(strict_types=1);

namespace App\Security\EntryPoint;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class AccountLoginRedirectEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function start(Request $request, AuthenticationException $authException = null): RedirectResponse
    {
        // Для API-запросов оставляем стандартное поведение (не редиректим),
        // но этот entry point вешаем только на firewall `account` (HTML страница),
        // так что здесь достаточно просто редиректить.

        $targetUrl = $this->urlGenerator->generate('customer_login');

        // Можно добавить параметр next, чтобы вернуть пользователя обратно после логина
        $originalUri = $request->getRequestUri();
        if ($originalUri && $originalUri !== '/') {
            $targetUrl .= (str_contains($targetUrl, '?') ? '&' : '?') . 'next=' . rawurlencode($originalUri);
        }

        return new RedirectResponse($targetUrl, 302);
    }
}


