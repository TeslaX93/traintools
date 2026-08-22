<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (!class_exists(Dotenv::class)) {
    throw new LogicException('Please run "composer require symfony/dotenv" to load the ".env" files configuring the application.');
}

// bootEnv sam obsluguje .env.local.php, .env.local oraz .env.$APP_ENV.
// Wczesniej bylo tu "new Dotenv(false)" - sygnatura z Symfony 4, gdzie
// pierwszy argument oznaczal usePutenv. Dzis to string $envKey, wiec false
// stawalo sie pustym kluczem i pliki .env.$APP_ENV nigdy sie nie ladowaly.
(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
