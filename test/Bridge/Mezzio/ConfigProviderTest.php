<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Bridge\Mezzio;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Bridge\Mezzio\ConfigProvider;
use Sirix\Redaction\Factory\RedactorFactory;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\RedactorInterface;

final class ConfigProviderTest extends TestCase
{
    public function testProvidesExpectedDependencies(): void
    {
        /**
         * @var array{
         *     dependencies: array{
         *         aliases: array<class-string, class-string>,
         *         factories: array<class-string, class-string>
         *     }
         * }
         */
        $config = (new ConfigProvider())();

        $this->assertSame(
            Redactor::class,
            $config['dependencies']['aliases'][RedactorInterface::class]
        );
        $this->assertSame(
            RedactorFactory::class,
            $config['dependencies']['factories'][Redactor::class]
        );
    }
}
