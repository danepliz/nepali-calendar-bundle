<?php

declare(strict_types=1);

namespace Danepliz\NepaliCalendarBundle\Service;

use DateTime;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CalendarService
{
    private array $calendarData = [];

    private array $monthNamesEn = [
        'Baisakh', 'Jestha', 'Ashadh', 'Shrawan',
        'Bhadra', 'Ashwin', 'Kartik', 'Mangsir',
        'Poush', 'Magh', 'Falgun', 'Chaitra',
    ];

    private array $monthNamesNe = [
        'बैशाख', 'जेठ', 'असार', 'श्रावण',
        'भाद्र', 'आश्विन', 'कार्तिक', 'मंसिर',
        'पौष', 'माघ', 'फाल्गुन', 'चैत्र',
    ];

    private array $fiscalMonthPrefixes = [
        1 => 'SHR', 2 => 'BDH', 3 => 'ASW',  4 => 'KAR',
        5 => 'MAN', 6 => 'PUS', 7 => 'MAG',  8 => 'FAL',
        9 => 'CHA', 10 => 'BAI', 11 => 'JES', 12 => 'ASR',
    ];

    public function __construct(
        private readonly string $calendarJsonPath,
        private readonly int $cacheTtl = 300,
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->loadCalendarData();
    }

    // ─────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────

    /**
     * Convert an AD (Gregorian) date string to a BS (Bikram Sambat) date string.
     *
     * @param string $adDate     Any date string parseable by DateTime (e.g. "2025-02-06")
     * @param string $separator  Character used between year, month and day parts
     * @param bool   $nepali     Return Nepali (Devanagari) digits
     *
     * @throws Exception
     */
    public function adToBs(string $adDate, string $separator = '-', bool $nepali = false): string
    {
        $result = $this->adToBsArray($adDate);

        $formatted = sprintf(
            '%s%s%s%s%s',
            $result['year'],
            $separator,
            str_pad((string) $result['month'], 2, '0', STR_PAD_LEFT),
            $separator,
            str_pad((string) $result['day'],   2, '0', STR_PAD_LEFT),
        );

        // Preserve time portion if present in the original string
        if (str_contains($adDate, ':')) {
            $time = (new DateTime($adDate))->format('H:i:s');
            $formatted .= ' ' . $time;
        }

        return $nepali ? $this->toNepaliDigits($formatted) : $formatted;
    }

    /**
     * Convert an AD date string to a BS date array.
     *
     * @return array{year:string,month:int,day:int,month_name:string,month_name_ne:string}
     * @throws Exception
     */
    public function adToBsArray(string $adDate): array
    {
        $target = new DateTime($adDate);
        $target->setTime(0, 0, 0);

        foreach ($this->calendarData as $bsYear => $months) {
            foreach ($months as [$bsMonth, $startDateStr, $daysInMonth]) {
                $startDate = new DateTime($startDateStr);
                $startDate->setTime(0, 0, 0);

                $endDate = (clone $startDate)->modify("+$daysInMonth days")->modify('-1 day');
                $endDate->setTime(0, 0, 0);

                if ($target >= $startDate && $target <= $endDate) {
                    $day = $target->diff($startDate)->days + 1;

                    return [
                        'year'         => $bsYear,
                        'month'        => $bsMonth,
                        'day'          => $day,
                        'month_name'   => $this->monthNamesEn[$bsMonth - 1],
                        'month_name_ne' => $this->monthNamesNe[$bsMonth - 1],
                    ];
                }
            }
        }

        throw new Exception("Unable to convert AD date '{$adDate}' to BS.");
    }

    /**
     * Convert a BS date string (YYYY-MM-DD) to an AD date string.
     *
     * @throws Exception
     */
    public function bsToAd(string $bsDate, string $format = 'Y-m-d'): string
    {
        [$bsYear, $bsMonth, $bsDay] = array_map('intval', explode('-', $bsDate));

        if (!isset($this->calendarData[(string) $bsYear])) {
            throw new Exception("Unsupported BS year: {$bsYear}");
        }

        $months = $this->calendarData[(string) $bsYear];

        if (!isset($months[$bsMonth - 1])) {
            throw new Exception("Invalid BS month: {$bsMonth}");
        }

        [, $startDateStr, $daysInMonth] = $months[$bsMonth - 1];

        if ($bsDay < 1 || $bsDay > $daysInMonth) {
            throw new Exception("Invalid day {$bsDay} for BS {$bsYear}-{$bsMonth}");
        }

        $startDate = new DateTime($startDateStr);
        $startDate->modify(sprintf('+%d days', $bsDay - 1));

        return $startDate->format($format);
    }

    /**
     * Return the number of days in a given BS month.
     *
     * @throws Exception
     */
    public function daysInBsMonth(int $bsYear, int $bsMonth): int
    {
        $this->assertValidBsYearMonth($bsYear, $bsMonth);
        [,, $days] = $this->calendarData[(string) $bsYear][$bsMonth - 1];
        return $days;
    }

    /**
     * Return the weekday (0 = Sunday … 6 = Saturday) of the first day of a BS month.
     *
     * @throws Exception
     */
    public function firstWeekdayOfBsMonth(int $bsYear, int $bsMonth): int
    {
        $this->assertValidBsYearMonth($bsYear, $bsMonth);
        [, $startDateStr] = $this->calendarData[(string) $bsYear][$bsMonth - 1];
        return (int) (new DateTime($startDateStr))->format('w');
    }

    /**
     * Format an AD date using Nepali (Devanagari) numerals and month name.
     *
     * @throws Exception
     */
    public function formatNepaliDate(DateTime $date): string
    {
        $bs = $this->adToBsArray($date->format('Y-m-d'));
        return $this->toNepaliDigits(
            "{$bs['day']} {$bs['month_name_ne']} {$bs['year']}"
        );
    }

    /**
     * Format an AD date + time using Nepali numerals.
     *
     * @throws Exception
     */
    public function formatNepaliDateTime(DateTime $date): string
    {
        $bs = $this->adToBsArray($date->format('Y-m-d'));
        $time = $date->format('H:i:s');
        return $this->toNepaliDigits(
            "{$bs['day']} {$bs['month_name_ne']} {$bs['year']} {$time}"
        );
    }

    /**
     * Convert ASCII digits inside a string to Devanagari digits.
     */
    public function toNepaliDigits(string $input): string
    {
        $map = ['०','१','२','३','४','५','६','७','८','९'];
        return preg_replace_callback('/\d/', static fn ($m) => $map[(int) $m[0]], $input);
    }

    /**
     * Return the three-letter fiscal-month prefix for a given fiscal month number (1–12).
     *
     * @throws Exception
     */
    public function getFiscalMonthPrefix(int $fiscalMonth): string
    {
        if (!isset($this->fiscalMonthPrefixes[$fiscalMonth])) {
            throw new Exception("Invalid fiscal month: {$fiscalMonth}");
        }
        return $this->fiscalMonthPrefixes[$fiscalMonth];
    }

    /** @return string[] English month names (Baisakh … Chaitra) */
    public function getMonthNamesEn(): array { return $this->monthNamesEn; }

    /** @return string[] Nepali month names */
    public function getMonthNamesNe(): array { return $this->monthNamesNe; }

    /** Return the full calendar data array (keyed by BS year string). */
    public function getCalendarData(): array { return $this->calendarData; }

    // ─────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────

    private function loadCalendarData(): void
    {
        $cacheKey = 'nepali_calendar_' . md5($this->calendarJsonPath);

        // Try PSR-6 cache first
        if ($this->cache !== null) {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                $this->calendarData = $item->get();
                return;
            }
        }

        // Fetch from URL or filesystem
        if (filter_var($this->calendarJsonPath, FILTER_VALIDATE_URL)) {
            $raw = @file_get_contents($this->calendarJsonPath);
        } else {
            $raw = is_file($this->calendarJsonPath) ? file_get_contents($this->calendarJsonPath) : false;
        }

        if ($raw === false) {
            $this->logger->error('NepaliCalendarBundle: could not load calendar data from {path}', [
                'path' => $this->calendarJsonPath,
            ]);
            throw new Exception("Could not load calendar data from: {$this->calendarJsonPath}");
        }

        $this->calendarData = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if ($this->cache !== null) {
            $item = $this->cache->getItem($cacheKey);
            $item->set($this->calendarData)->expiresAfter($this->cacheTtl);
            $this->cache->save($item);
        }
    }

    /**
     * @throws Exception
     */
    private function assertValidBsYearMonth(int $year, int $month): void
    {
        if (!isset($this->calendarData[(string) $year])) {
            throw new Exception("Unsupported BS year: {$year}");
        }
        if ($month < 1 || $month > 12) {
            throw new Exception("Invalid BS month: {$month}");
        }
    }
}
