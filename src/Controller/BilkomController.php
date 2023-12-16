<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BilkomController extends AbstractController
{
    #[Route('/bilkom', name: 'app_bilkom')]
    public function index(): Response
    {
        return $this->render('bilkom/index.html.twig', [
            'controller_name' => 'BilkomController',
        ]);
    }
}
