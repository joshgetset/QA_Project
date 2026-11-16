<?php

namespace Tests\Unit;

use App\Services\CesScholarshipNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CesScholarshipNameNormalizerTest extends TestCase
{
    #[DataProvider('scholarshipNames')]
    public function test_it_maps_source_names_to_dashboard_categories(string $sourceName, string $expected): void
    {
        $this->assertSame($expected, CesScholarshipNameNormalizer::canonicalName($sourceName));
    }

    public static function scholarshipNames(): array
    {
        return [
            'CHED merit variant' => ['CHED Merit Full (100%)', 'CHEDMerit'],
            'Tulong Dunong' => ['Tulong Dunong Program', 'CHEDTD'],
            'free tuition subsidy' => ['Free Tuition - 2026', 'Subsidy'],
            'unsupported source entry' => ['Unmapped grant', ''],
        ];
    }
}
