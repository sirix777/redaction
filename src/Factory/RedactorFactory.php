<?php

declare(strict_types=1);

namespace Sirix\Redaction\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\RedactorInterface;

use function array_key_exists;
use function is_array;
use function is_callable;
use function is_numeric;
use function is_string;

class RedactorFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): RedactorInterface
    {
        $config = $container->has('config')
            ? $container->get('config')
            : [];

        $options = $config['redactor']['options'] ?? [];

        $rules = $options['rules'] ?? [];
        $useDefaultRules = $options['use_default_rules'] ?? true;

        if (! is_array($rules)) {
            $rules = [];
        }

        $redactor = new Redactor($rules, (bool) $useDefaultRules);

        if (isset($options['replacement']) && is_string($options['replacement'])) {
            $redactor->setReplacement($options['replacement']);
        }

        if (isset($options['template']) && is_string($options['template'])) {
            $redactor->setTemplate($options['template']);
        }

        if (array_key_exists('length_limit', $options)) {
            $lengthLimit = $options['length_limit'];
            if (null === $lengthLimit || is_numeric($lengthLimit)) {
                $redactor->setLengthLimit(null !== $lengthLimit ? (int) $lengthLimit : null);
            }
        }

        if (isset($options['object_view_mode']) && $options['object_view_mode'] instanceof ObjectViewModeEnum) {
            $redactor->setObjectViewMode($options['object_view_mode']);
        }

        if (array_key_exists('max_depth', $options)) {
            $maxDepth = $options['max_depth'];
            if (null === $maxDepth || is_numeric($maxDepth)) {
                $redactor->setMaxDepth(null !== $maxDepth ? (int) $maxDepth : null);
            }
        }

        if (array_key_exists('max_items_per_container', $options)) {
            $maxItemsPerContainer = $options['max_items_per_container'];
            if (null === $maxItemsPerContainer || is_numeric($maxItemsPerContainer)) {
                $redactor->setMaxItemsPerContainer(null !== $maxItemsPerContainer ? (int) $maxItemsPerContainer : null);
            }
        }

        if (array_key_exists('max_total_nodes', $options)) {
            $maxTotalNodes = $options['max_total_nodes'];
            if (null === $maxTotalNodes || is_numeric($maxTotalNodes)) {
                $redactor->setMaxTotalNodes(null !== $maxTotalNodes ? (int) $maxTotalNodes : null);
            }
        }

        if (array_key_exists('on_limit_exceeded_callback', $options)) {
            $callback = $options['on_limit_exceeded_callback'];
            if (null === $callback || is_callable($callback)) {
                $redactor->setOnLimitExceededCallback($callback);
            }
        }

        if (array_key_exists('overflow_placeholder', $options) && is_string($options['overflow_placeholder'])) {
            $redactor->setOverflowPlaceholder($options['overflow_placeholder']);
        }

        return $redactor;
    }
}
