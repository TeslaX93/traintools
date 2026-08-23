<?php

namespace App\Controller;

use App\Service\StationNameFragments;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomepageController extends AbstractController
{
    public function __construct(private readonly StationNameFragments $fragments)
    {
    }

    #[Route('/', name: 'homepage')]
    public function index(): Response
    {
        return $this->render('homepage/index.html.twig', [
            'controller_name' => 'HomepageController',
            'stationFragments' => $this->fragments->sample(),
        ]);
    }
}
