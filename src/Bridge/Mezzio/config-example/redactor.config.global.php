<?php

declare(strict_types=1);

use Sirix\Redaction\Factory\RedactorFactory;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\RedactorInterface;
use Sirix\Redaction\Rule\StartEndRule;

/*
 * Example Mezzio/Laminas configuration for Sirix Redaction
 *
 * Save this file as config/autoload/redactor.config.global.php in your application
 * (or merge its contents into your configuration structure).
 */
return [
    'dependencies' => [
        // You can omit this if you use the provided ConfigProvider
        'aliases'   => [
            RedactorInterface::class => Redactor::class,
        ],
        'factories' => [
            Redactor::class => RedactorFactory::class,
        ],
    ],

    // Redactor configuration
    'redactor'     => [
        'options' => [
            // Custom rules (same structure as passing to the constructor)
            'rules'                      => [
                'card_number' => new StartEndRule(6, 4),
            ],

            // Whether to load built‑in default rules (bool, default: true)
            'use_default_rules'          => true,

            // Core options (all optional, read strictly; no scalar coercion)
            'replacement'                => '*',                 // string
            'template'                   => '%s',                   // string, exactly one plain %s placeholder
            'length_limit'               => null,               // int|null, numeric strings are invalid
            'object_view_mode'           => 'copy',         // enum instance or: copy|public_array|skip
            'max_depth'                  => null,                  // int|null, numeric strings are invalid
            'max_items_per_container'    => null,    // int|null, numeric strings are invalid
            'max_total_nodes'            => null,            // int|null, numeric strings are invalid
            'on_limit_exceeded_callback' => null, // callable|null
            'overflow_placeholder'       => '...',      // string|null, null omits markers and uses null for exceeded branches
        ],
    ],
];
