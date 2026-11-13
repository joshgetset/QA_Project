<?php

namespace Tests\Feature;

use App\Exceptions\CesAnalyticsSnapshotUnavailable;
use App\Models\User;
use App\Services\CesAnalyticsSnapshotStore;
use App\Services\CesAnalyticsSnapshotSynchronizer;
use App\Services\CesEnrollmentAnalytics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CesAnalyticsTest extends TestCase
{
    public function test_analytics_endpoint_requires_authentication(): void
    {
        $this->getJson(route('qams.ces.analytics'))->assertUnauthorized();
    }

    public function test_analytics_endpoint_passes_filters_to_the_analytics_service(): void
    {
        $payload = [
            'summary' => [],
            'demographics' => [],
            'filters' => [],
            'selected' => [],
            'campuses' => [],
            'partial' => false,
            'updated_at' => '2026-11-12T00:00:00+00:00',
        ];
        $analytics = Mockery::mock(CesEnrollmentAnalytics::class);
        $analytics->shouldReceive('get')
            ->once()
            ->with([
                'campus' => 'sogod',
                'college' => 'Education',
                'program' => 'Bachelor of Education',
                'year' => '2026',
                'semester' => '1',
            ])
            ->andReturn($payload);
        $this->app->instance(CesEnrollmentAnalytics::class, $analytics);

        $this->actingAs(User::factory()->make())
            ->getJson(route('qams.ces.analytics', [
                'campus' => 'sogod',
                'college' => 'Education',
                'program' => 'Bachelor of Education',
                'year' => '2026',
                'semester' => '1',
            ]))
            ->assertOk()
            ->assertExactJson($payload);
    }

    public function test_analytics_endpoint_rejects_unknown_campuses_and_semesters(): void
    {
        $this->actingAs(User::factory()->make())
            ->getJson(route('qams.ces.analytics', ['campus' => 'unknown']))
            ->assertUnprocessable();

        $this->actingAs(User::factory()->make())
            ->getJson(route('qams.ces.analytics', ['semester' => '3']))
            ->assertUnprocessable();
    }

    public function test_endpoint_reports_missing_snapshot_as_service_unavailable(): void
    {
        $analytics = Mockery::mock(CesEnrollmentAnalytics::class);
        $analytics->shouldReceive('get')
            ->once()
            ->andThrow(new CesAnalyticsSnapshotUnavailable('Snapshot has not been synced.'));
        $this->app->instance(CesEnrollmentAnalytics::class, $analytics);

        $this->actingAs(User::factory()->make())
            ->getJson(route('qams.ces.analytics'))
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Snapshot has not been synced.');
    }

    public function test_analytics_filters_and_aggregates_stored_snapshot_rows(): void
    {
        config([
            'ces.campuses' => [
                'sogod' => ['name' => 'Sogod', 'database' => 'enrolment_sogod'],
                'maasin' => ['name' => 'Maasin', 'database' => 'enrolment_maasin'],
            ],
        ]);
        $snapshot = [
            'synced_at' => '2026-11-13T04:00:00+00:00',
            'campuses' => [
                'sogod' => [
                    'name' => 'Sogod',
                    'status' => 'available',
                    'terms' => ['2026' => ['1' => true]],
                    'grade_terms' => ['2025' => ['2' => true], '2026' => ['1' => true, '2' => true, '9' => true]],
                    'graduate_terms' => [
                        ['year' => '2025', 'semester' => '2'],
                        ['year' => '2026', 'semester' => '2'],
                    ],
                    'income_available' => true,
                ],
                'maasin' => [
                    'name' => 'Maasin',
                    'status' => 'available',
                    'terms' => [],
                    'grade_terms' => [],
                    'graduate_terms' => [],
                    'income_available' => false,
                ],
            ],
            'graduates' => [
                [
                    'campus' => 'sogod',
                    'college' => 'Education',
                    'program' => 'Bachelor of Education',
                    'year' => '2026',
                    'already_graduated' => 7,
                ],
                [
                    'campus' => 'maasin',
                    'college' => 'Education',
                    'program' => 'Bachelor of Education',
                    'year' => '2026',
                    'already_graduated' => 3,
                ],
            ],
            'catalog' => [
                ['campus' => 'sogod', 'college' => 'Education', 'program' => 'Bachelor of Education'],
                ['campus' => 'sogod', 'college' => 'Education', 'program' => 'Bachelor of Arts'],
            ],
            'rows' => [
                [
                    'campus' => 'sogod',
                    'college' => 'Education',
                    'program' => 'Bachelor of Education',
                    'year' => '2026',
                    'semester' => '1',
                    'summary' => [
                        'total_enrolled_students' => 12,
                        'graduating_enrollees' => 2,
                        'new_freshman' => 4,
                    ],
                    'demographics' => [
                        'international' => ['total' => 3, 'male' => 1, 'female' => 2],
                        'four_ps' => ['total' => 5, 'male' => 2, 'female' => 3],
                        'disability' => ['total' => 1, 'male' => 0, 'female' => 1],
                        'first_generation' => ['total' => 6, 'male' => 3, 'female' => 3],
                        'financial_aid' => [
                            'total' => 4,
                            'scholarships' => [
                                ['name' => 'Academic Scholar', 'total' => 3],
                                ['name' => 'academic scholar', 'total' => 1],
                                ['name' => 'CHED', 'total' => 2],
                            ],
                        ],
                        'household_income' => ['total' => 5],
                    ],
                ],
                [
                    'campus' => 'sogod',
                    'college' => 'Education',
                    'program' => 'Bachelor of Arts',
                    'year' => '2026',
                    'semester' => '1',
                    'summary' => [
                        'total_enrolled_students' => 8,
                        'graduating_enrollees' => 1,
                        'new_freshman' => 2,
                    ],
                    'demographics' => [
                        'international' => ['total' => 0, 'male' => 0, 'female' => 0],
                        'four_ps' => ['total' => 0, 'male' => 0, 'female' => 0],
                        'disability' => ['total' => 0, 'male' => 0, 'female' => 0],
                        'first_generation' => ['total' => 3, 'male' => 1, 'female' => 2],
                        'financial_aid' => ['total' => 0],
                        'household_income' => ['total' => 0],
                    ],
                ],
                [
                    'campus' => 'maasin',
                    'college' => 'Education',
                    'program' => 'Bachelor of Education',
                    'year' => '2026',
                    'semester' => '1',
                    'summary' => [
                        'total_enrolled_students' => 10,
                        'graduating_enrollees' => null,
                        'new_freshman' => 3,
                    ],
                    'demographics' => [
                        'international' => ['total' => 1, 'male' => 0, 'female' => 1],
                        'four_ps' => ['total' => 2, 'male' => 1, 'female' => 1],
                        'disability' => ['total' => 1, 'male' => 1, 'female' => 0],
                        'first_generation' => ['total' => 4, 'male' => 2, 'female' => 2],
                        'financial_aid' => ['total' => 8],
                        'household_income' => ['total' => 9],
                    ],
                ],
            ],
        ];
        $store = Mockery::mock(CesAnalyticsSnapshotStore::class);
        $store->shouldReceive('read')->times(3)->andReturn($snapshot);
        $analytics = new CesEnrollmentAnalytics($store);

        $payload = $analytics->get([
            'campus' => 'sogod',
            'college' => 'Education',
            'program' => 'Bachelor of Education',
            'year' => '2026',
            'semester' => '1',
        ]);
        $summary = collect($payload['summary'])->keyBy('key');
        $demographics = collect($payload['demographics'])->keyBy('key');

        $this->assertSame(12, $summary['total-enrolled-students']['value']);
        $this->assertSame(2, $summary['graduating-enrollees']['value']);
        $this->assertSame(7, $summary['already-graduated-students']['value']);
        $this->assertSame(4, $summary['new-freshman']['value']);
        $this->assertSame(6, $demographics['first-generation-learners']['total']);
        $this->assertTrue($demographics['first-generation-learners']['available']);
        $this->assertSame(4, $demographics['financial-aid-recipients']['total']);
        $this->assertSame('count', $demographics['financial-aid-recipients']['display']);
        $this->assertSame([
            ['name' => 'Academic Scholar', 'total' => 4],
            ['name' => 'CHED', 'total' => 2],
        ], $payload['scholarships']);
        $this->assertSame(5, $demographics['household-income']['total']);
        $this->assertSame('count', $demographics['household-income']['display']);
        $this->assertTrue($demographics['household-income']['available']);
        $this->assertStringContainsString('Sogod', $demographics['household-income']['note']);
        $this->assertSame('2026', $payload['selected']['year']);
        $this->assertSame('1', $payload['selected']['semester']);
        $this->assertContains(['value' => '2025', 'label' => '2025–2026'], $payload['filters']['year']);

        $secondSemesterPayload = $analytics->get([
            'campus' => 'sogod',
            'college' => 'Education',
            'program' => 'Bachelor of Education',
            'year' => '2026',
            'semester' => '2',
        ]);
        $secondSemesterSummary = collect($secondSemesterPayload['summary'])->keyBy('key');
        $this->assertSame(7, $secondSemesterSummary['already-graduated-students']['value']);
        $this->assertNull($secondSemesterSummary['total-enrolled-students']['value']);

        $defaultPayload = $analytics->get();
        $this->assertSame('2026', $defaultPayload['selected']['year']);
        $this->assertSame('1', $defaultPayload['selected']['semester']);
    }

    public function test_snapshot_sync_persists_aggregates_without_student_identifiers(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The CES snapshot fixture requires the SQLite PDO driver.');
        }

        config([
            'database.connections.ces_sources' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'database.connections.ces_reporting' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'ces.campuses' => [
                'sogod' => ['name' => 'Sogod', 'database' => 'enrolment_sogod'],
            ],
        ]);
        DB::purge('ces_sources');
        DB::purge('ces_reporting');
        $connection = DB::connection('ces_sources');
        $connection->statement("ATTACH DATABASE ':memory:' AS information_schema");
        $connection->statement('CREATE TABLE information_schema.tables (table_schema TEXT, table_name TEXT)');
        $connection->statement('CREATE TABLE information_schema.columns (table_schema TEXT, table_name TEXT, column_name TEXT)');
        $connection->statement("ATTACH DATABASE ':memory:' AS enrolment_sogod");
        $connection->statement('CREATE TABLE enrolment_sogod.students (StudentNo TEXT, Course INTEGER, nationality TEXT, Sex TEXT, Disability TEXT, anual_income INTEGER)');
        $connection->statement('CREATE TABLE enrolment_sogod.students2 (StudentNo TEXT, Family4Ps TEXT)');
        $connection->statement('CREATE TABLE enrolment_sogod.guaranteedinfo (StudentNo TEXT, isFirstGeneration INTEGER, deleted_at TEXT)');
        $connection->statement('CREATE TABLE enrolment_sogod.course (id INTEGER, course_title TEXT, Department INTEGER)');
        $connection->statement('CREATE TABLE enrolment_sogod.department (id INTEGER, CollegeName TEXT, DepartmentName TEXT)');
        $connection->statement('CREATE TABLE enrolment_sogod.registration (StudentNo TEXT, Course INTEGER, finalize INTEGER, SchoolYear TEXT, Semester INTEGER, StudentStatus TEXT, Scholar TEXT, TES INTEGER, isUnifastPaid INTEGER)');
        $connection->statement('CREATE TABLE enrolment_sogod.scholar (id TEXT, scholar_name TEXT, amount TEXT, typ TEXT)');
        $connection->statement('CREATE TABLE enrolment_sogod.grades20261 (StudentNo TEXT)');
        $connection->statement('CREATE TABLE enrolment_sogod.grades20252 (StudentNo TEXT)');
        $connection->statement('CREATE TABLE enrolment_sogod.stage20261 (StudentNo TEXT, deleted_at TEXT)');
        $connection->statement('CREATE TABLE enrolment_sogod.listgradmain (id INTEGER, SchoolYear INTEGER, Semester INTEGER)');
        $connection->statement('CREATE TABLE enrolment_sogod.listgradsub (id INTEGER, StudentNo TEXT, MainID INTEGER)');
        $connection->statement("INSERT INTO enrolment_sogod.students VALUES ('PRIVATE-STUDENT-ID', 1, 'Filipino', 'F', '', 1)");
        $connection->statement("INSERT INTO enrolment_sogod.students2 VALUES ('PRIVATE-STUDENT-ID', 'No')");
        $connection->statement("INSERT INTO enrolment_sogod.guaranteedinfo VALUES ('PRIVATE-STUDENT-ID', 1, NULL)");
        $connection->statement("INSERT INTO enrolment_sogod.course VALUES (1, 'Bachelor of Education', 1)");
        $connection->statement("INSERT INTO enrolment_sogod.department VALUES (1, 'Education', 'Education Department')");
        $connection->statement("INSERT INTO enrolment_sogod.scholar VALUES ('SCHOLAR-1', 'Academic Scholar-Full', '100', '1')");
        $connection->statement("INSERT INTO enrolment_sogod.scholar VALUES ('SCHOLAR-2', 'Academic Scholar-Half', '50', '1')");
        $connection->statement("INSERT INTO enrolment_sogod.registration VALUES ('PRIVATE-STUDENT-ID', 1, 1, '2026', 1, 'New', 'SCHOLAR-1', 0, 0)");
        $connection->statement("INSERT INTO enrolment_sogod.registration VALUES ('PRIVATE-SECOND-STUDENT-ID', 1, 1, '2026', 1, 'Continuing', 'SCHOLAR-2', 0, 0)");
        $connection->statement("INSERT INTO enrolment_sogod.grades20261 VALUES ('PRIVATE-STUDENT-ID')");
        $connection->statement("INSERT INTO enrolment_sogod.grades20252 VALUES ('PRIVATE-STUDENT-ID')");
        $connection->statement("INSERT INTO enrolment_sogod.grades20252 VALUES ('PRIVATE-SECOND-STUDENT-ID')");
        $connection->statement("INSERT INTO enrolment_sogod.stage20261 VALUES ('PRIVATE-STUDENT-ID', NULL)");
        $connection->statement('INSERT INTO enrolment_sogod.listgradmain VALUES (1, 2026, 1)');
        $connection->statement("INSERT INTO enrolment_sogod.listgradsub VALUES (1, 'PRIVATE-STUDENT-ID', 1)");
        $connection->table('information_schema.tables')->insert([
            ['table_schema' => 'enrolment_sogod', 'table_name' => 'stage20261'],
            ['table_schema' => 'enrolment_sogod', 'table_name' => 'grades20261'],
            ['table_schema' => 'enrolment_sogod', 'table_name' => 'grades20252'],
        ]);
        $connection->table('information_schema.columns')->insert([
            'table_schema' => 'enrolment_sogod',
            'table_name' => 'students',
            'column_name' => 'anual_income',
        ]);
        Schema::connection('ces_reporting')->create('ces_analytics_snapshots', function ($table) {
            $table->string('snapshot_key', 40)->primary();
            $table->longText('payload');
            $table->timestamp('synced_at');
        });

        $store = new CesAnalyticsSnapshotStore;
        (new CesAnalyticsSnapshotSynchronizer($store))->sync();
        $snapshot = $store->read();
        $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('PRIVATE-STUDENT-ID', $encoded);
        $this->assertStringNotContainsString('PRIVATE-SECOND-STUDENT-ID', $encoded);
        $this->assertCount(1, $snapshot['rows']);
        $this->assertSame(['1' => true], $snapshot['campuses']['sogod']['terms']['2026']);
        $this->assertSame([['year' => '2026', 'semester' => '1']], $snapshot['campuses']['sogod']['graduate_terms']);
        $this->assertTrue($snapshot['campuses']['sogod']['income_available']);
        $this->assertSame(1, $snapshot['rows'][0]['summary']['total_enrolled_students']);
        $this->assertSame(1, $snapshot['rows'][0]['demographics']['first_generation']['total']);
        $this->assertSame(2, $snapshot['rows'][0]['demographics']['financial_aid']['total']);
        $this->assertSame([
            ['name' => 'Academic Scholar', 'total' => 2],
        ], $snapshot['rows'][0]['demographics']['financial_aid']['scholarships']);
        $this->assertSame(1, $snapshot['rows'][0]['demographics']['household_income']['total']);
        $this->assertSame(1, $snapshot['graduates'][0]['already_graduated']);

        config(['ces.database_connection' => 'unavailable_live_database']);
        $payload = (new CesEnrollmentAnalytics($store))->get();
        $this->assertSame('2026', $payload['selected']['year']);
        $this->assertSame('1', $payload['selected']['semester']);
        $this->assertSame(1, collect($payload['summary'])->firstWhere('key', 'total-enrolled-students')['value']);
        $this->assertSame(1, collect($payload['summary'])->firstWhere('key', 'already-graduated-students')['value']);

        $historicalPayload = (new CesEnrollmentAnalytics($store))->get([
            'year' => '2025',
            'semester' => '2',
        ]);
        $this->assertSame('2025', $historicalPayload['selected']['year']);
        $this->assertSame('2', $historicalPayload['selected']['semester']);
        $this->assertSame(2, collect($historicalPayload['summary'])->firstWhere('key', 'total-enrolled-students')['value']);
    }
}
