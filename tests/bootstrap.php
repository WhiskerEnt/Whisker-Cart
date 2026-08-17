<?php
/**
 * WHISKER — Test bootstrap.
 *
 * Unit tests are pure: no database, no config/*.php, no network. Anything
 * needing MySQL belongs in tests/ci/integration.php (run by the CI
 * integration job), not in tests/Unit.
 */

define('WK_ROOT', dirname(__DIR__));

require_once WK_ROOT . '/core/autoload.php';
