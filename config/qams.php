<?php

return [
    'refresh_interval' => 30,
    'systems' => [
        'system-17' => [
            'name' => 'Comprehensive Enrolment System',
            'description' => 'University enrolment and registration services',
            'icon' => 'ri ri-user-add-line',
            'endpoint' => env('QAMS_SYSTEM_17_API_URL'),
            'token' => env('QAMS_SYSTEM_17_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'Enrolled students', 'value' => '8,420'],
                ['label' => 'Active programs', 'value' => '42'],
            ],
        ],
        'human-resources' => [
            'name' => 'Human Resource Management System',
            'description' => 'Personnel records and human resource services',
            'icon' => 'ri ri-team-line',
            'endpoint' => env('QAMS_HR_API_URL'),
            'token' => env('QAMS_HR_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'University personnel', 'value' => '1,124'],
                ['label' => 'Pending leave requests', 'value' => '23'],
            ],
        ],
        'student-information' => [
            'name' => 'Student Information System',
            'description' => 'Student records and academic information',
            'icon' => 'ri ri-graduation-cap-line',
            'endpoint' => env('QAMS_STUDENT_API_URL'),
            'token' => env('QAMS_STUDENT_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'Student records', 'value' => '8,420'],
                ['label' => 'Active programs', 'value' => '42'],
            ],
        ],
        'learning-management' => [
            'name' => 'Learning Management System',
            'description' => 'Courses, learners, and learning resources',
            'icon' => 'ri ri-book-open-line',
            'endpoint' => env('QAMS_LEARNING_API_URL'),
            'token' => env('QAMS_LEARNING_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'Active courses', 'value' => '126'],
                ['label' => 'Active learners', 'value' => '3,580'],
            ],
        ],
        'system-07' => [
            'name' => 'Online Admission System',
            'description' => 'Online university admission services',
            'icon' => 'ri ri-file-user-line',
            'endpoint' => env('QAMS_SYSTEM_07_API_URL'),
            'token' => env('QAMS_SYSTEM_07_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'Applications received', 'value' => '1,240'],
                ['label' => 'Pending applications', 'value' => '186'],
            ],
        ],
        'library-services' => [
            'name' => 'Online Clearance System',
            'description' => 'Online student clearance processing',
            'icon' => 'ri ri-task-line',
            'endpoint' => env('QAMS_LIBRARY_API_URL'),
            'token' => env('QAMS_LIBRARY_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'Clearances processed', 'value' => '2,660'],
                ['label' => 'Pending clearances', 'value' => '184'],
            ],
        ],
        'system-08' => [
            'name' => 'Procurement Management Information System',
            'description' => 'Procurement requests and processing',
            'icon' => 'ri ri-shopping-cart-2-line',
            'endpoint' => env('QAMS_SYSTEM_08_API_URL'),
            'token' => env('QAMS_SYSTEM_08_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'Procurement requests', 'value' => '860'],
                ['label' => 'Pending requests', 'value' => '12'],
            ],
        ],
        'system-09' => [
            'name' => 'Policy Management System',
            'description' => 'University policies and review workflows',
            'icon' => 'ri ri-file-list-3-line',
            'endpoint' => env('QAMS_SYSTEM_09_API_URL'),
            'token' => env('QAMS_SYSTEM_09_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'Published policies', 'value' => '2,105'],
                ['label' => 'Policies under review', 'value' => '25'],
            ],
        ],
        'system-10' => [
            'name' => 'Clinic Management Information System',
            'description' => 'University clinic visits and health services',
            'icon' => 'ri ri-heart-pulse-line',
            'endpoint' => env('QAMS_SYSTEM_10_API_URL'),
            'token' => env('QAMS_SYSTEM_10_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'Clinic visits', 'value' => '3,420'],
                ['label' => 'Pending consultations', 'value' => '31'],
            ],
        ],
        'system-11' => [
            'name' => 'Online Payslip Access System',
            'description' => 'Employee payslip access and payroll information',
            'icon' => 'ri ri-bank-card-line',
            'endpoint' => env('QAMS_SYSTEM_11_API_URL'),
            'token' => env('QAMS_SYSTEM_11_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'Payslips issued', 'value' => '1,785'],
                ['label' => 'Employee accounts', 'value' => '1,124'],
            ],
        ],
        'research-management' => [
            'name' => 'Research, Innovation, and Extension Services Management System',
            'description' => 'Research projects, innovation, and extension services',
            'icon' => 'ri ri-flask-line',
            'endpoint' => env('QAMS_RESEARCH_API_URL'),
            'token' => env('QAMS_RESEARCH_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'Active projects', 'value' => '87'],
                ['label' => 'Ongoing studies', 'value' => '29'],
            ],
        ],
        'system-12' => [
            'name' => 'Automated Faculty Evaluation System',
            'description' => 'Faculty evaluation submissions and results',
            'icon' => 'ri ri-survey-line',
            'endpoint' => env('QAMS_SYSTEM_12_API_URL'),
            'token' => env('QAMS_SYSTEM_12_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'Evaluations submitted', 'value' => '945'],
                ['label' => 'Faculty evaluated', 'value' => '186'],
            ],
        ],
        'system-13' => [
            'name' => 'Gender and Development System',
            'description' => 'Gender and development programs and activities',
            'icon' => 'ri ri-service-line',
            'endpoint' => env('QAMS_SYSTEM_13_API_URL'),
            'token' => env('QAMS_SYSTEM_13_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'GAD activities', 'value' => '267'],
                ['label' => 'Ongoing programs', 'value' => '21'],
            ],
        ],
        'finance' => [
            'name' => 'SLSU Web-based Integrated Payment Engine',
            'description' => 'University payment transactions and services',
            'icon' => 'ri ri-wallet-3-line',
            'endpoint' => env('QAMS_FINANCE_API_URL'),
            'token' => env('QAMS_FINANCE_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'Payment transactions', 'value' => '1,510'],
                ['label' => 'Pending transactions', 'value' => '14'],
            ],
        ],
        'system-14' => [
            'name' => 'Proximity Attendance Scanning System',
            'description' => 'Proximity-based attendance scanning and records',
            'icon' => 'ri ri-user-location-line',
            'endpoint' => env('QAMS_SYSTEM_14_API_URL'),
            'token' => env('QAMS_SYSTEM_14_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'Attendance scans', 'value' => '760'],
                ['label' => 'Present today', 'value' => '742'],
            ],
        ],
        'system-15' => [
            'name' => 'Designation Management Information System',
            'description' => 'University designations and assignments',
            'icon' => 'ri ri-briefcase-3-line',
            'endpoint' => env('QAMS_SYSTEM_15_API_URL'),
            'token' => env('QAMS_SYSTEM_15_API_TOKEN'),
            'sample_metrics' => [
                ['label' => 'Active designations', 'value' => '193'],
                ['label' => 'Current appointees', 'value' => '184'],
            ],
        ],
    ],
];
