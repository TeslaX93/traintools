<?php

namespace App\Helper;

class Constants
{
    const ANGZARR = "⍼";

    /**
     * Rozklady jazdy sa w czasie polskim. Bez jawnego ustawienia PHP bierze
     * strefe z php.ini, przez co ta sama aplikacja pyta Bilkom o inna godzine
     * na produkcji niz lokalnie.
     */
    const TIMEZONE = 'Europe/Warsaw';

    public static function getAngzarr(): string
    {
        return self::ANGZARR;
    }
}