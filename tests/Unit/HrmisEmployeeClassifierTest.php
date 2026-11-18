<?php

namespace Tests\Unit;

use App\Services\HrmisEmployeeClassifier;
use Tests\TestCase;

class HrmisEmployeeClassifierTest extends TestCase
{
    public function test_it_classifies_academic_rank_and_health_positions(): void
    {
        $classifier = new HrmisEmployeeClassifier;

        $this->assertSame('instructor', $classifier->position('Insrtructor III')['category']);
        $this->assertTrue($classifier->position('Associate Professor IV')['is_senior_academic']);
        $this->assertSame('university_professor', $classifier->position('University Professor')['category']);
        $this->assertSame('teacher', $classifier->position('Master Teacher 1')['category']);
        $this->assertTrue($classifier->position('Teachers')['is_teaching']);
        $this->assertSame('non_teaching', $classifier->position('Administrative Assistant')['category']);
        $this->assertSame('unknown_position', $classifier->position(null, 'Permanent - Staff')['category']);
        $this->assertTrue($classifier->position('Part Time Instructor')['is_part_time_teaching']);
        $this->assertTrue($classifier->position('Part Time Administrative Aide')['is_part_time']);
        $this->assertTrue($classifier->position('Administrative Assistant')['is_non_teaching']);
        $this->assertTrue($classifier->position(null, 'Permanent - Staff')['is_non_teaching']);
        $this->assertTrue($classifier->position(null, 'Part Timer')['is_part_time_teaching']);
        $this->assertFalse($classifier->position(null, 'Part Timer')['is_non_teaching']);
        $this->assertTrue($classifier->position(null, 'Job')['is_non_teaching']);
        $this->assertFalse($classifier->position(null)['is_non_teaching']);
        $this->assertTrue($classifier->position('Guidance Counselor II')['is_guidance_counselor']);
        $this->assertTrue($classifier->position('Nurse III')['is_physical_health']);
    }

    public function test_it_normalizes_employment_categories(): void
    {
        $classifier = new HrmisEmployeeClassifier;

        $this->assertSame('job_order', $classifier->employment('Job Order')['canonical_status']);
        $this->assertSame('job_order', $classifier->employment('Job')['canonical_status']);
        $this->assertTrue($classifier->employment('Part Timer')['is_part_time']);
        $this->assertTrue($classifier->employment('Permanent - Staff')['is_permanent']);
        $this->assertTrue($classifier->employment('Contract of Service')['is_contractual']);
        $this->assertSame('temporary', $classifier->employment('Temporary - Faculty')['canonical_status']);
        foreach ([null, '  ', 'Unknown', 'Permnent'] as $status) {
            $this->assertSame('unknown_emp_status', $classifier->employment($status)['canonical_status']);
        }
    }

    public function test_it_classifies_highest_education_without_treating_units_as_degrees(): void
    {
        $classifier = new HrmisEmployeeClassifier;

        $this->assertNull($classifier->educationCategory('College', 'Bachelor of Arts', '100', null));
        $this->assertSame('bachelor', $classifier->educationCategory('College', 'Bachelor of Arts', '100', '2018'));
        $this->assertSame('master_units', $classifier->educationCategory('Graduate Studies', 'Master of Arts', '30', null));
        $this->assertNull($classifier->educationCategory('Graduate Studies', 'Master of Arts', '0.00', null));
        $this->assertSame('masters_degree', $classifier->educationCategory('Graduate Studies', 'M.A.', null, '2022'));
        $this->assertSame('doctorate_degree', $classifier->highestEducation([
            ['student_level' => 'College', 'degree_course' => 'Bachelor of Arts', 'year_graduated' => '2018'],
            ['student_level' => 'Graduate Studies', 'degree_course' => 'Doctor of Philosophy', 'units_earned' => '24'],
            ['student_level' => 'Graduate Studies', 'degree_course' => 'Doctor of Philosophy', 'year_graduated' => '2024'],
        ]));
    }

    public function test_it_normalizes_encrypted_gender_output_values(): void
    {
        $classifier = new HrmisEmployeeClassifier;

        $this->assertSame('male', $classifier->gender('Male'));
        $this->assertSame('female', $classifier->gender('F'));
        $this->assertSame('unknown', $classifier->gender(null));
        $this->assertSame('unknown', $classifier->gender('  '));
        $this->assertSame('unknown', $classifier->gender('Femlae'));
        $this->assertSame('unknown', $classifier->gender('Unknown'));
    }
}
