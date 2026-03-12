<?php

declare(strict_types=1);

use Danepliz\NepaliCalendarBundle\Service\CalendarService;
use Danepliz\NepaliCalendarBundle\Twig\NepaliCalendarExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // ── Core calendar service ────────────────────────────────────
    $services
        ->set(CalendarService::class)
        ->args([
            param('nepali_calendar.calendar_json_path'),
            param('nepali_calendar.cache_ttl'),
            // PSR-6 cache pool — optional; wire it only when symfony/cache is present
            service('cache.app')->nullOnInvalid(),
            // PSR-3 logger — optional
            service('logger')->nullOnInvalid(),
        ])
        ->public()
        ->alias('nepali_calendar.calendar_service', CalendarService::class);

    // ── Twig extension ───────────────────────────────────────────
    $services
        ->set(NepaliCalendarExtension::class)
        ->args([service(CalendarService::class)])
        ->tag('twig.extension');
};
