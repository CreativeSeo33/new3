<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route(path: '/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('base.html.twig', [
            // Можно передавать данные в Vue через data-атрибуты/JSON, но базово не требуется
        ]);
    }
}


