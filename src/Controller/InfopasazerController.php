<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class InfopasazerController extends AbstractController
{
    /**
     * @Route("/infopasazer", name="infopasazer")
     */
    public function index()
    {
        return $this->render('infopasazer/index.html.twig', [
            'controller_name' => 'InfopasazerController',
        ]);
    }
}
