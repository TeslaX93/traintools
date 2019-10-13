<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class ZtrController extends AbstractController
{
    /**
     * @Route("/ztr", name="ztr")
     */
    public function index()
    {
        return $this->render('ztr/index.html.twig', [
            'controller_name' => 'ZTRController',
        ]);
    }
	/**
	 * @Route("/ztrres", name="ztrResults")
	 */
	public function results() {
	    if(empty($_POST)) return $this->redirectToRoute("ztr");
		return $this->render('ztr/ztr.html.twig', ['dane'=>$_POST]);
	}
}
