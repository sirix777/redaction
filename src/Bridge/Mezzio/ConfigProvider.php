<?php

declare(strict_types=1);

namespace Sirix\Redaction\Bridge\Mezzio;

use Sirix\Redaction\Factory\RedactorFactory;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\RedactorInterface;

class ConfigProvider
{
    /**
     * @return array<string, array<string, array<string, string>|string>>
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getDependencies(): array
    {
        return [
            'aliases' => [
                RedactorInterface::class => Redactor::class,
            ],
            'factories' => [
                Redactor::class => RedactorFactory::class,
            ],
        ];
    }
}
