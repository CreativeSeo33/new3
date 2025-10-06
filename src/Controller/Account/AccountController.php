<?php
declare(strict_types=1);

namespace App\Controller\Account;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/account')]
final class AccountController extends AbstractController
{
    #[Route('', name: 'account_index', methods: ['GET'])]
    public function index(): Response
    {
        // Страница доступна только при IS_AUTHENTICATED_FULLY (см. security.yaml)
        return $this->render('account/index.html.twig');
    }
}


