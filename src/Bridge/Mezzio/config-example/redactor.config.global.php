<?php

declare(strict_types=1);

use Sirix\Redaction\Enum\ObjectViewModeEnum;
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
        'aliases' => [
            RedactorInterface::class => Redactor::class,
        ],
        'factories' => [
            Redactor::class => RedactorFactory::class,
        ],
    ],

    // Redactor configuration
    'redactor' => [
        'options' => [
            // Custom rules (same structure as passing to the constructor)
            'rules' => [
                'card_number' => new StartEndRule(6, 4),
            ],

            // Whether to load built‑in default rules (bool, default: true)
            'use_default_rules' => true,

            // Core options mirrored from setters (all optional)
            'replacement' => '*',                 // string
            'template' => '%s',                   // string
            'length_limit' => null,               // int|null
            'object_view_mode' => ObjectViewModeEnum::Copy,
            'max_depth' => null,                  // int|null
            'max_items_per_container' => null,    // int|null
            'max_total_nodes' => null,            // int|null
            'on_limit_exceeded_callback' => null, // callable|null
            'overflow_placeholder' => '...',      // string
        ],
    ],
];
