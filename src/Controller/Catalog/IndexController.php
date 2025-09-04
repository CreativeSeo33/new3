<?php
declare(strict_types=1);

namespace App\Controller\Catalog;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/catalog', name: 'catalog_')]
final class IndexController extends AbstractController
{

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('catalog/base.html.twig');
    }
}


