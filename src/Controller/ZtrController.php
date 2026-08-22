<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ZtrController extends AbstractController
{
    /** Style, dla których szablon podpina arkusz o tej nazwie. */
    private const TEMPLATES = ['pkpic', 'ks'];

    private const DEFAULT_TEMPLATE = 'pkpic';

    private const DEFAULT_COLOR = '#000000';

    #[Route('/ztr', name: 'ztr')]
    public function index(): Response
    {
        return $this->render('ztr/index.html.twig', [
            'controller_name' => 'ZTRController',
        ]);
    }

    #[Route('/ztrres', name: 'ztrResults')]
    public function results(Request $request): Response
    {
        $raw = $request->request->all();

        if (!$raw) {
            return $this->redirectToRoute('ztr');
        }

        // Do szablonu przepuszczamy wyłącznie znane pola o znanym kształcie.
        // Wcześniej szedł tam surowy $_POST, więc "stb" podane jako zwykły
        // ciąg zamiast tablicy wywracało array_keys() na 500.
        $dane = [
            'templateType' => $this->pickTemplate($raw['templateType'] ?? null),
            'numberColor' => $this->pickColor($raw['numberColor'] ?? null),
            'nameColor' => $this->pickColor($raw['nameColor'] ?? null),
            'trainNo' => $this->pickText($raw['trainNo'] ?? null),
            'trainName' => $this->pickText($raw['trainName'] ?? null),
            'firstStation' => $this->pickText($raw['firstStation'] ?? null),
            'lastStation' => $this->pickText($raw['lastStation'] ?? null),
            'st' => $this->pickStations($raw['st'] ?? null),
        ];

        return $this->render('ztr/ztr.html.twig', [
            'dane' => $dane,
            'stationsBold' => $this->pickBoldIndexes($raw['stb'] ?? null),
        ]);
    }

    private function pickTemplate(mixed $value): string
    {
        return is_string($value) && in_array($value, self::TEMPLATES, true)
            ? $value
            : self::DEFAULT_TEMPLATE;
    }

    /**
     * Kolor trafia wprost do atrybutu style, więc przepuszczamy tylko zapis
     * #rrggbb - dokładnie to, co wysyła <input type="color">.
     */
    private function pickColor(mixed $value): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)
            ? $value
            : self::DEFAULT_COLOR;
    }

    private function pickText(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @return list<string>
     */
    private function pickStations(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $stations = [];
        foreach ($value as $station) {
            if (is_scalar($station)) {
                $stations[] = trim((string) $station);
            }
        }

        return $stations;
    }

    /**
     * Checkboxy "stb" przychodzą tylko dla zaznaczonych pozycji, więc liczą
     * się ich klucze, nie wartości.
     *
     * @return list<int>
     */
    private function pickBoldIndexes(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $indexes = [];
        foreach (array_keys($value) as $index) {
            if (is_int($index) || (is_string($index) && ctype_digit($index))) {
                $indexes[] = (int) $index;
            }
        }

        return $indexes;
    }
}
