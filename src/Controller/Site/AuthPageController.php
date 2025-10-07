<?php
declare(strict_types=1);

namespace App\Controller\Site;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use App\Service\Auth\RefreshTokenManager;

final class AuthPageController extends AbstractController
{
    #[Route('/auth/login', name: 'customer_login', methods: ['GET'])]
    public function login(
        Request $request,
        AuthenticationSuccessHandler $successHandler,
        RefreshTokenManager $refreshManager
    ): Response
    {
        // Если уже авторизованы и есть параметр next — проставим access/refresh куки и вернём на next.
        $next = (string) $request->query->get('next', '');
        if (null !== $this->getUser() && $next !== '') {
            $target = rawurldecode($next);
            if ($target === '' || $target[0] !== '/') {
                $target = $this->generateUrl('account_index');
            }

            $lexikResponse = $successHandler->handleAuthenticationSuccess($this->getUser());
            [$rawRefresh, $storedRefresh] = $refreshManager->create($this->getUser(), $request);
            $refreshCookie = $refreshManager->makeCookie($rawRefresh);

            $resp = $this->redirect($target);
            foreach ($lexikResponse->headers->getCookies() as $cookie) {
                $resp->headers->setCookie($cookie);
            }
            $resp->headers->setCookie($refreshCookie);
            return $resp;
        }

        if (null !== $this->getUser()) {
            return $this->redirectToRoute('account_index');
        }

        return $this->render('security/customer_login.html.twig');
    }

    #[Route('/auth/register', name: 'customer_register', methods: ['GET'])]
    public function register(): Response
    {
        return $this->render('security/register.html.twig');
    }

    #[Route('/auth/password/request', name: 'customer_password_request', methods: ['GET'])]
    public function passwordRequest(): Response
    {
        return $this->render('security/password_request.html.twig');
    }

    #[Route('/auth/password/reset', name: 'customer_password_reset', methods: ['GET'])]
    public function passwordReset(): Response
    {
        return $this->render('security/password_reset.html.twig');
    }
}


