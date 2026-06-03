<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->ignoreErrorsOnPackageAndPath(
        'monolog/monolog',
        __DIR__ . '/src/Bridge/Monolog/RedactorProcessor.php',
        [ErrorType::DEV_DEPENDENCY_IN_PROD],
    )
    ->ignoreErrorsOnExtensionAndPath(
        'ext-intl',
        __DIR__ . '/src/Rule/UnicodeStartEndRule.php',
        [ErrorType::SHADOW_DEPENDENCY],
    )
;
