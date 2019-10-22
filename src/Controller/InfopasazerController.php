<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DomCrawler\Crawler;

class InfopasazerController extends AbstractController
{
    /**
     * @Route("/infopasazer", name="infopasazer")
     */
    public function index()
    {

        return $this->render('infopasazer/index.html.twig', [

        ]);
    }

    /**
     * @Route("/infopasazer/allArrivals/{station}")
     * @param Request $request
     * @return Response
     */
    public function allArrivals(Request $request)
    {
        $response = new Response();
        $json = ['data' => 123];
        $response->setContent(json_encode($json));
        $response->headers->set('Content-Type','application/json');
        return $response;
    }
}
