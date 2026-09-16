<?php

declare(strict_types=1);

/**
 * Global constants for the FluxBB application.
 *
 * This file is loaded early by PHPStan (via scanFiles) and by the
 * application entry point. All path constants are rooted at the
 * project root directory.
 */

$realPath = realpath(__DIR__ . '/../../..');
define('FLUXBB_ROOT', $realPath !== false ? $realPath : __DIR__ . '/../../..');