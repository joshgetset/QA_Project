<?php

return [
    'source_connection' => 'hrmis_source',
    'sex_encryption_key' => env('HRMIS_SEX_ENCRYPTION_KEY'),
    'senior_academic_ranks' => ['associate_professor', 'professor', 'university_professor'],
    'campus_codes' => [
        1 => 'sogod',
        2 => 'maasin',
        3 => 'tomasoppus',
        4 => 'bontoc',
        5 => 'sanjuan',
        6 => 'hinunangan',
    ],
];
