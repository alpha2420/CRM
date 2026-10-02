<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Support\CsvCell;
use App\Support\PhoneNumber;
use App\Support\TimeZoneName;
use App\Support\WhatsAppNumber;
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

    #[DataProvider('internationalPhones')]
    public function test_every_number_is_saved_one_way(string $input, string $countryCode, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::international($input, $countryCode));
    }

    public static function internationalPhones(): array
    {
        return [
            'mobile without country code' => ['98765 43210', '91', '+919876543210'],
            'with a trunk 0' => ['098765 43210', '91', '+919876543210'],
            'country code without plus' => ['91 98765 43210', '91', '+919876543210'],
            'international prefix' => ['0091 98765 43210', '91', '+919876543210'],
            'already international' => ['+91 98765-43210', '91', '+919876543210'],
            'landline with area code' => ['(020) 2345 6789', '91', '+912023456789'],
            'another country' => ['+44 20 7946 0958', '91', '+442079460958'],
            'workspace in Dubai' => ['050 123 4567', '+971', '+971501234567'],
            'nothing typed' => ['', '91', ''],
        ];
    }

    public function test_whatsapp_gets_the_full_number_even_with_a_trunk_zero(): void
    {
        $this->assertSame('919876543210', WhatsAppNumber::fromPhone('098765 43210', '91'));
        $this->assertSame('919876543210', WhatsAppNumber::fromPhone('+919876543210', '91'));
    }

    #[DataProvider('zones')]
    public function test_old_browser_time_zone_names_are_updated(string $input, ?string $expected): void
    {
        $this->assertSame($expected, TimeZoneName::current($input));
    }

    public static function zones(): array
    {
        return [
            'india in chrome' => ['Asia/Calcutta', 'Asia/Kolkata'],
            'already current' => ['Asia/Kolkata', 'Asia/Kolkata'],
            'renamed city' => ['Europe/Kiev', 'Europe/Kyiv'],
            'not a zone' => ['Mars/Olympus', null],
            'empty' => ['', null],
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

    #[DataProvider('names')]
    public function test_first_names_skip_titles(string $name, string $first): void
    {
        $this->assertSame($first, (new Lead)->forceFill(['name' => $name])->firstName());
    }

    public static function names(): array
    {
        return [
            'plain' => ['Priya Sharma', 'Priya'],
            'title' => ['Mrs. Priya Sharma', 'Priya'],
            'indian title' => ['Smt Kavita Rao', 'Kavita'],
            'doctor without dot' => ['Dr Anil Mehta', 'Anil'],
            'one word' => ['Ravi', 'Ravi'],
            'only a title' => ['Dr.', 'Dr.'],
        ];
    }
}
