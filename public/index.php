<?php

declare(strict_types=1);

/**
 * FluxBB Forum Engine — Modernized from v1.5.11 legacy.
 *
 * @copyright 2008-2012 FluxBB
 * @license GPL-2.0-or-later
 */

use FluxBB\Shared\Infrastructure\Kernel;
use Symfony\Component\HttpFoundation\Request;

(function () {
    // Define the project root constant
    define('FLUXBB_ROOT', realpath(__DIR__ . '/..'));

    // Load Composer autoloader
    $autoloader = require FLUXBB_ROOT . '/vendor/autoload.php';

    // Load environment variables from .env file
    if (file_exists(FLUXBB_ROOT . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(FLUXBB_ROOT);
        $dotenv->load();
    }

    // Fallback to getenv() if $_SERVER not populated (PHP-FPM with clear_env=yes)
    $env = $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'prod';
    $debug = filter_var(
        $_SERVER['APP_DEBUG'] ?? getenv('APP_DEBUG'),
        FILTER_VALIDATE_BOOLEAN
    );

    // Create the application kernel
    $kernel = new Kernel(
        environment: $env,
        debug: $debug
    );

    // Handle the request
    $request = Request::createFromGlobals();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
})();