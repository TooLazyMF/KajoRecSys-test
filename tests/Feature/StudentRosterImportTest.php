<?php

namespace Tests\Feature;

use App\Models\SlipAssignment;
use App\Models\SlipSubmission;
use App\Models\StudentReference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentRosterImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_imported_roster_updates_student_groups_and_form_year_levels(): void
    {
        $counselor = User::factory()->create(['role' => 'guidance_counselor']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create([
            'name' => 'Roderick Rod. Roderick',
            'id_number' => '1494',
            'gender' => 'male',
            'role' => 'student',
        ]);
        $removedStudent = User::factory()->create([
            'id_number' => '2000',
            'role' => 'student',
        ]);

        $this->actingAs($counselor)->post(route('admin.students.import'), [
            'student_file' => UploadedFile::fake()->createWithContent(
                'first-roster.csv',
                "Code,Name,Sex,Year,Course\n1494.0,Roderick Rod. Roderick,Male,1st Year,BSIT\n2000,Removed Student,Female,2nd Year,BSCS\n",
            ),
        ])->assertRedirect(route('guidance.dashboard'));

        $this->actingAs($counselor)->post(route('admin.students.import'), [
            'student_file' => UploadedFile::fake()->createWithContent(
                'latest-roster.csv',
                "Code,Name,Sex,Year,Course\n1494,Roderick Rod. Roderick,Male,2nd Year,BSCS\n",
            ),
        ])->assertRedirect(route('guidance.dashboard'));

        $this->assertSame(1, StudentReference::query()->count());
        $this->assertSame('2nd Year', StudentReference::query()->where('id_number', '1494')->value('year'));
        $this->assertSame('BSCS', StudentReference::query()->where('id_number', '1494')->value('course'));
        $this->assertTrue($student->fresh()->is_active);
        $this->assertFalse($removedStudent->fresh()->is_active);

        $this->actingAs($counselor)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSeeInOrder(['BSCS', '2nd Year', 'Roderick Rod. Roderick'])
            ->assertDontSee('Removed Student')
            ->assertDontSee('Course not listed')
            ->assertDontSee('Year level not listed');

        $cifAssignment = SlipAssignment::create([
            'assigned_by' => $teacher->id,
            'student_id' => $student->id,
            'slip' => 'qf-bo-oss-04-client-information-form',
        ]);
        $this->actingAs($student)
            ->get(route('assigned-slips.fillup', $cifAssignment))
            ->assertOk()
            ->assertSee('BSCS 2nd Year');

        $referralSubmission = SlipSubmission::create([
            'user_id' => $student->id,
            'slip' => 'student-referral-slip',
            'data' => [
                'student_name' => $student->name,
                'program_year' => 'BSIT 1st Year',
                'absence_dates' => ['2026-11-01'],
            ],
            'pdf_path' => 'student-referral.pdf',
            'review_status' => 'approved',
        ]);
        $referral = SlipAssignment::create([
            'assigned_by' => $teacher->id,
            'student_id' => $student->id,
            'slip' => 'student-referral-slip',
            'status' => 'approved',
            'slip_submission_id' => $referralSubmission->id,
            'completed_at' => now(),
        ]);
        $admission = SlipAssignment::create([
            'assigned_by' => $counselor->id,
            'student_id' => $student->id,
            'related_assignment_id' => $referral->id,
            'slip' => 'class-admission-slip',
        ]);

        $this->actingAs($student)
            ->get(route('assigned-slips.fillup', $admission))
            ->assertOk()
            ->assertSee('BSCS 2nd Year')
            ->assertDontSee('BSIT 1st Year');
    }

    public function test_roster_import_detects_varied_headers_and_converts_numeric_year_levels(): void
    {
        $counselor = User::factory()->create(['role' => 'guidance_counselor']);
        User::factory()->create([
            'name' => 'Jane A Doe',
            'id_number' => '1234',
            'gender' => 'female',
            'role' => 'student',
        ]);

        $this->actingAs($counselor)->post(route('admin.students.import'), [
            'student_file' => UploadedFile::fake()->createWithContent(
                'roster.csv',
                "Enrollment List for 2026\nNo.,Student No.,Last Name,First Name,Middle Name,Sex,Course Name,Year Level (Current),Units\n1,1234,Doe,Jane,A,F,BCAED,1,26\n",
            ),
        ])->assertRedirect(route('guidance.dashboard'));

        $this->assertSame('1st Year', StudentReference::query()->where('id_number', '1234')->value('year'));
        $this->assertSame('BCAED', StudentReference::query()->where('id_number', '1234')->value('course'));
    }

    public function test_roster_import_rejects_student_rows_without_a_year_level(): void
    {
        $counselor = User::factory()->create(['role' => 'guidance_counselor']);

        $this->actingAs($counselor)->from(route('guidance.dashboard'))->post(route('admin.students.import'), [
            'student_file' => UploadedFile::fake()->createWithContent(
                'roster.csv',
                "Code,Name,Sex,Course,Year Level\n1234,Jane Doe,Female,BCAED,\n",
            ),
        ])->assertRedirect(route('guidance.dashboard'))
            ->assertSessionHasErrors('student_file');

        $this->assertSame(0, StudentReference::query()->count());
    }
}
