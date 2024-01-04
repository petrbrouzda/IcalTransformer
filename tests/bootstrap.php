<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';   # načte Composer autoloader

Tester\Environment::setup();                # inicializace Nette Tester

// a další konfigurace (jde jen o příklad, v našem případě nejsou potřeba)
date_default_timezone_set('Europe/Prague');

