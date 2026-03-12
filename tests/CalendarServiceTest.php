<?php

declare(strict_types=1);

namespace Danepliz\NepaliCalendarBundle\Tests\Service;

use Danepliz\NepaliCalendarBundle\Service\CalendarService;
use PHPUnit\Framework\TestCase;

class CalendarServiceTest extends TestCase
{
    private CalendarService $service;

    protected function setUp(): void
    {
        // Minimal inline calendar data covering 2081 Baisakh (2024-04-13 … 2024-05-12)
        // and Jestha (2024-05-14 …) — enough to run the conversion tests.
        $data = [
            '2080' => [
                [1,  '2023-04-14', 31],
                [2,  '2023-05-15', 31],
                [3,  '2023-06-15', 32],
                [4,  '2023-07-17', 31],
                [5,  '2023-08-17', 31],
                [6,  '2023-09-17', 30],
                [7,  '2023-10-17', 30],
                [8,  '2023-11-16', 29],
                [9,  '2023-12-15', 30],
                [10, '2024-01-14', 29],
                [11, '2024-02-12', 30],
                [12, '2024-03-13', 30],
            ],
            '2081' => [
                [1,  '2024-04-13', 31],
                [2,  '2024-05-14', 31],
                [3,  '2024-06-14', 32],
                [4,  '2024-07-16', 31],
                [5,  '2024-08-16', 31],
                [6,  '2024-09-16', 30],
                [7,  '2024-10-16', 30],
                [8,  '2024-11-15', 29],
                [9,  '2024-12-14', 30],
                [10, '2025-01-13', 29],
                [11, '2025-02-11', 30],
                [12, '2025-03-13', 30],
            ],
        ];

        // Write to a temp file so CalendarService can load it
        $tmpFile = tempnam(sys_get_temp_dir(), 'nepali_cal_') . '.json';
        file_put_contents($tmpFile, json_encode($data));

        $this->service = new CalendarService($tmpFile);
    }

    // ── AD → BS ─────────────────────────────────────────────────

    public function testAdToBsFirstDayOfMonth(): void
    {
        // 2024-04-13 is 2081-01-01 (Baisakh 1)
        $result = $this->service->adToBsArray('2024-04-13');
        $this->assertSame('2081', $result['year']);
        $this->assertSame(1,      $result['month']);
        $this->assertSame(1,      $result['day']);
    }

    public function testAdToBsLastDayOfMonth(): void
    {
        // Baisakh has 31 days → last day is 2024-05-13
        $result = $this->service->adToBsArray('2024-05-13');
        $this->assertSame('2081', $result['year']);
        $this->assertSame(1,      $result['month']);
        $this->assertSame(31,     $result['day']);
    }

    public function testAdToBsReturnString(): void
    {
        $result = $this->service->adToBs('2024-04-13');
        $this->assertSame('2081-01-01', $result);
    }

    public function testAdToBsWithSeparator(): void
    {
        $result = $this->service->adToBs('2024-04-13', '/');
        $this->assertSame('2081/01/01', $result);
    }

    public function testAdToBsNepaliDigits(): void
    {
        $result = $this->service->adToBs('2024-04-13', '-', true);
        // 2081-01-01 in Devanagari
        $this->assertSame('२०८१-०१-०१', $result);
    }

    // ── BS → AD ─────────────────────────────────────────────────

    public function testBsToAdFirstDayOfMonth(): void
    {
        $result = $this->service->bsToAd('2081-01-01');
        $this->assertSame('2024-04-13', $result);
    }

    public function testBsToAdLastDayOfMonth(): void
    {
        $result = $this->service->bsToAd('2081-01-31');
        $this->assertSame('2024-05-13', $result);
    }

    public function testBsToAdCustomFormat(): void
    {
        $result = $this->service->bsToAd('2081-01-01', 'd/m/Y');
        $this->assertSame('13/04/2024', $result);
    }

    // ── Helper methods ──────────────────────────────────────────

    public function testDaysInBsMonth(): void
    {
        $this->assertSame(31, $this->service->daysInBsMonth(2081, 1)); // Baisakh 2081
        $this->assertSame(31, $this->service->daysInBsMonth(2081, 2)); // Jestha 2081
        $this->assertSame(32, $this->service->daysInBsMonth(2081, 3)); // Ashadh 2081
    }

    public function testToNepaliDigits(): void
    {
        $this->assertSame('२०२५', $this->service->toNepaliDigits('2025'));
        $this->assertSame('०१-०२', $this->service->toNepaliDigits('01-02'));
    }

    public function testGetFiscalMonthPrefix(): void
    {
        $this->assertSame('SHR', $this->service->getFiscalMonthPrefix(1));
        $this->assertSame('ASR', $this->service->getFiscalMonthPrefix(12));
    }

    // ── Error cases ─────────────────────────────────────────────

    public function testBsToAdThrowsOnInvalidYear(): void
    {
        $this->expectException(\Exception::class);
        $this->service->bsToAd('1999-01-01');
    }

    public function testBsToAdThrowsOnInvalidDay(): void
    {
        $this->expectException(\Exception::class);
        $this->service->bsToAd('2081-01-32');
    }

    public function testAdToBsThrowsOnOutOfRangeDate(): void
    {
        $this->expectException(\Exception::class);
        $this->service->adToBsArray('1900-01-01');
    }
}
