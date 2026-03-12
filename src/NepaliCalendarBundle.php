<?php

declare(strict_types=1);

namespace Danepliz\NepaliCalendarBundle;

use Danepliz\NepaliCalendarBundle\DependencyInjection\NepaliCalendarExtension;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class NepaliCalendarBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('calendar_json_path')
                    ->defaultValue('https://fitnechnepal.blr1.cdn.digitaloceanspaces.com/Calendar/nepali_calendar.json')
                    ->info('URL or absolute path to the nepali_calendar.json file')
                ->end()
                ->scalarNode('cache_ttl')
                    ->defaultValue(300)
                    ->info('Cache TTL in seconds for calendar data (default: 300s = 5 minutes)')
                ->end()
                ->scalarNode('default_locale')
                    ->defaultValue('en')
                    ->info('Default locale for date formatting: "en" or "ne" (Nepali)')
                ->end()
                ->booleanNode('register_twig_extension')
                    ->defaultTrue()
                    ->info('Whether to register the Twig extension automatically')
                ->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $container->parameters()
            ->set('nepali_calendar.calendar_json_path', $config['calendar_json_path'])
            ->set('nepali_calendar.cache_ttl', (int) $config['cache_ttl'])
            ->set('nepali_calendar.default_locale', $config['default_locale'])
            ->set('nepali_calendar.register_twig_extension', $config['register_twig_extension']);
    }
}
