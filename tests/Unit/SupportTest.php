<?php

namespace Tests\Unit;

use App\Support\CsvCell;
use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    #[DataProvider('phones')]
    public function test_phone_numbers_are_normalised(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    public static function phones(): array
    {
        return [
            'spaces and dashes' => ['+91 98765-43210', '+919876543210'],
            'brackets' => ['(011) 2345 6789', '01123456789'],
            'plus only at start' => ['98+765', '98765'],
        ];
    }

    #[DataProvider('cells')]
    public function test_csv_cells_are_safe_for_spreadsheets(string $input, string $expected): void
    {
        $this->assertSame($expected, CsvCell::safe($input));
    }

    public static function cells(): array
    {
        return [
            'formula' => ['=1+1', "'=1+1"],
            'at sign' => ['@SUM(A1)', "'@SUM(A1)"],
            'phone number' => ['+919876543210', '+919876543210'],
            'negative number' => ['-12.5', '-12.5'],
            'plain text' => ['Jane', 'Jane'],
        ];
    }
}
