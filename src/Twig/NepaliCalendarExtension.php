<?php

declare(strict_types=1);

namespace Danepliz\NepaliCalendarBundle\Twig;

use DateTime;
use Danepliz\NepaliCalendarBundle\Service\CalendarService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class NepaliCalendarExtension extends AbstractExtension
{
    public function __construct(private readonly CalendarService $calendarService) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('bs_date',      [$this, 'filterBsDate']),
            new TwigFilter('bs_datetime',  [$this, 'filterBsDateTime']),
            new TwigFilter('nepali_digits',[$this, 'filterNepaliDigits']),
            new TwigFilter('ad_to_bs',     [$this, 'filterAdToBs']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('bs_to_ad',           [$this, 'functionBsToAd']),
            new TwigFunction('nepali_month_names',  [$this, 'functionMonthNames']),
            new TwigFunction('nepali_calendar_data',[$this, 'functionCalendarData']),
        ];
    }

    // ── Filters ──────────────────────────────────────────────────

    /**
     * {{ myDate | bs_date }}
     * Renders a DateTime as Nepali date with Devanagari numerals.
     */
    public function filterBsDate(DateTime $date): string
    {
        return $this->calendarService->formatNepaliDate($date);
    }

    /**
     * {{ myDate | bs_datetime }}
     */
    public function filterBsDateTime(DateTime $date): string
    {
        return $this->calendarService->formatNepaliDateTime($date);
    }

    /**
     * {{ '2025-02-06' | ad_to_bs(separator='-', nepali=false) }}
     */
    public function filterAdToBs(string $adDate, string $separator = '-', bool $nepali = false): string
    {
        return $this->calendarService->adToBs($adDate, $separator, $nepali);
    }

    /**
     * {{ '2025' | nepali_digits }}
     */
    public function filterNepaliDigits(string $input): string
    {
        return $this->calendarService->toNepaliDigits($input);
    }

    // ── Functions ─────────────────────────────────────────────────

    /**
     * {{ bs_to_ad('2081-10-23') }}
     */
    public function functionBsToAd(string $bsDate, string $format = 'Y-m-d'): string
    {
        return $this->calendarService->bsToAd($bsDate, $format);
    }

    /**
     * {{ nepali_month_names('ne') }}  — returns array of Nepali or English BS month names
     */
    public function functionMonthNames(string $locale = 'en'): array
    {
        return $locale === 'ne'
            ? $this->calendarService->getMonthNamesNe()
            : $this->calendarService->getMonthNamesEn();
    }

    /**
     * Returns the full calendar data as a JSON-serialisable array.
     * Useful when you need to pass it to a JavaScript datepicker.
     */
    public function functionCalendarData(): array
    {
        return $this->calendarService->getCalendarData();
    }
}
