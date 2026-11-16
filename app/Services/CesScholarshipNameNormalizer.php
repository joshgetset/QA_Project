<?php

namespace App\Services;

class CesScholarshipNameNormalizer
{
    public static function canonicalName(string $name): string
    {
        $name = preg_replace('/\s*\([^)]*(?:\d|%)[^)]*\)\s*/u', ' ', trim($name)) ?? $name;
        $name = preg_replace('/\s*[-–—]?\s*(?:19|20)\d{2}\s*$/u', '', $name) ?? $name;
        $name = preg_replace('/\b(?:full|half)\s+merit\b/iu', 'Merit', $name) ?? $name;
        $name = preg_replace('/\s*[-–—]\s*[\d,]+(?:\.\d+)?\s*\/\s*(?:month|mo)\s*$/iu', '', $name) ?? $name;
        $name = preg_replace('/\s*[-–—]?\s*\d+(?:\.\d+)?\s*%\s*$/u', '', $name) ?? $name;
        $name = preg_replace('/(?:\s*[-–—]\s*|\s+)(?:full|half|partial|one[-\s]?fourth|one[-\s]?quarter|quarter)\s*$/iu', '', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = trim($name, " \t\n\r\0\x0B-–—,");

        $typeKey = strtolower(preg_replace('/[^a-z0-9]+/iu', '', $name) ?? $name);
        if (str_contains($typeKey, 'ancop')) {
            return 'ANCOP';
        }
        if (str_contains($typeKey, 'bfar')) {
            return 'BFAR Scholarship Grant';
        }
        if (str_contains($typeKey, 'dost')) {
            return 'DOST';
        }
        if (str_contains($typeKey, '4ps') || str_contains($typeKey, 'esgppa')) {
            return '4Ps';
        }
        if (
            $typeKey === 'chedtd'
            || str_contains($typeKey, 'tulongdunong')
            || str_contains($typeKey, 'tulongdulong')
            || str_contains($typeKey, 'tdptes')
        ) {
            return 'CHEDTD';
        }
        if (str_contains($typeKey, 'ched') && str_contains($typeKey, 'merit')) {
            return 'CHEDMerit';
        }
        if ($typeKey === 'tes' || str_contains($typeKey, 'tertiaryeducationsubsidy')) {
            return 'TES';
        }
        if ($typeKey === 'subsidy' || str_contains($typeKey, 'freetuition')) {
            return 'Subsidy';
        }
        if (str_contains($typeKey, 'assistance')) {
            return 'Financial Assistance';
        }

        return '';
    }
}
