<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sitemapa generowana z nazw tras, a nie wpisywana ręcznie - dzięki temu nie
 * rozjedzie się z routingiem po zmianie adresu.
 */
class SitemapController extends AbstractController
{
    /**
     * Tylko strony dla ludzi. Endpointy API i dane JSON są wyłączone także
     * w robots.txt.
     *
     * @var array<string, array{changefreq: string, priority: string}>
     */
    private const PAGES = [
        'homepage' => ['changefreq' => 'monthly', 'priority' => '1.0'],
        'station_map' => ['changefreq' => 'monthly', 'priority' => '0.9'],
        'distance' => ['changefreq' => 'monthly', 'priority' => '0.9'],
        'station_panels' => ['changefreq' => 'monthly', 'priority' => '0.8'],
        'ztr' => ['changefreq' => 'monthly', 'priority' => '0.7'],
        'app_bilkom' => ['changefreq' => 'monthly', 'priority' => '0.7'],
        'app_random_station' => ['changefreq' => 'yearly', 'priority' => '0.4'],
    ];

    #[Route('/sitemap.xml', name: 'sitemap', defaults: ['_format' => 'xml'])]
    public function index(): Response
    {
        $urls = [];
        foreach (self::PAGES as $route => $meta) {
            $urls[] = [
                'loc' => $this->generateUrl($route, [], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => $meta['changefreq'],
                'priority' => $meta['priority'],
            ];
        }

        $response = $this->render('sitemap/index.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
