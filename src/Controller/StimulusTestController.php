<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StimulusTestController extends AbstractController
{
    #[Route('/stimulus/test', name: 'stimulus_test')]
    public function test(): Response
    {
        return $this->render('stimulus/test.html.twig');
    }
}
