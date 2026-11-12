<?php

return [
    'api_key' => env('CES_API_KEY'),
    'database_connection' => 'ces_sources',
    'campuses' => [
        'sogod' => ['name' => 'Sogod', 'database' => 'enrolment_sogod'],
        'bontoc' => ['name' => 'Bontoc', 'database' => 'enrolment_bontoc'],
        'tomasoppus' => ['name' => 'Tomas Oppus', 'database' => 'enrolment_tomasoppus'],
        'hinunangan' => ['name' => 'Hinunangan', 'database' => 'enrolment_hinunangan'],
        'maasin' => ['name' => 'Maasin', 'database' => 'enrolment_maasin'],
        'sanjuan' => ['name' => 'San Juan', 'database' => 'enrolment_sanjuan'],
    ],
];
