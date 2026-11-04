<?php

namespace Tests\Feature;

use App\Models\SlipAssignment;
use App\Models\SlipSubmission;
use App\Models\StudentReference;
use App\Models\User;
use App\Support\SlipCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SlipSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_information_form_prefills_and_locks_imported_student_details(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create([
            'name' => 'Ada Byron',
            'id_number' => 'CIF-1001',
            'gender' => 'female',
            'role' => 'student',
        ]);
        StudentReference::create([
            'id_number' => 'CIF-1001',
            'name' => 'Ada Lovelace Byron',
            'gender' => 'female',
            'course' => 'Computer Science',
            'year' => '3',
            'source_data' => [
                'First Name' => 'Ada',
                'Middle Name' => 'Lovelace',
                'Last Name' => 'Byron',
                'Contact Number' => '09123456789',
            ],
        ]);
        $assignment = SlipAssignment::create([
            'assigned_by' => $teacher->id,
            'student_id' => $student->id,
            'slip' => 'qf-bo-oss-04-client-information-form',
        ]);

        $response = $this->actingAs($student)
            ->get(route('assigned-slips.fillup', $assignment))
            ->assertOk()
            ->assertSee('09123456789')
            ->assertSee('Ada')
            ->assertSee('Lovelace')
            ->assertSee('Byron')
            ->assertSee('Computer Science 3');
        foreach (['last_name', 'first_name', 'middle_name', 'student_id', 'course_year', 'email', 'contact_number'] as $field) {
            $this->assertMatchesRegularExpression(
                '/<input\b(?=[^>]*\bid="'.$field.'")(?=[^>]*\breadonly\b)[^>]*>/s',
                $response->getContent(),
                "The {$field} field should be read-only when populated from the student record."
            );
        }

        $details = [];
        foreach (SlipCatalog::all()['qf-bo-oss-04-client-information-form']['groups'] as $group) {
            foreach ($group['fields'] as $field) {
                if ($field['type'] === 'checkboxes') {
                    $details[$field['key']] = $field['required']
                        ? [array_key_first($field['options'])]
                        : [];
                } elseif ($field['required']) {
                    $details[$field['key']] = $field['type'] === 'textarea' ? 'Student answer' : 'Forged value';
                }
            }
        }

        $this->actingAs($student)
            ->post(route('assigned-slips.submit', $assignment), ['details' => $details])
            ->assertSessionHasNoErrors();

        $submission = SlipSubmission::query()->where('user_id', $student->id)->firstOrFail();
        $this->assertSame('Ada', $submission->data['first_name']);
        $this->assertSame('Lovelace', $submission->data['middle_name']);
        $this->assertSame('Byron', $submission->data['last_name']);
        $this->assertSame('CIF-1001', $submission->data['student_id']);
        $this->assertSame('female', $submission->data['sex']);
        $this->assertSame('Computer Science 3', $submission->data['course_year']);
        $this->assertSame('09123456789', $submission->data['contact_number']);
        $this->assertSame('Student answer', $submission->data['address']);
    }

    public function test_client_information_form_locks_details_from_the_logged_in_account_without_a_roster_match(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create([
            'name' => 'Grace Hopper',
            'id_number' => 'CIF-1003',
            'gender' => 'female',
            'role' => 'student',
        ]);
        $assignment = SlipAssignment::create([
            'assigned_by' => $teacher->id,
            'student_id' => $student->id,
            'slip' => 'qf-bo-oss-04-client-information-form',
        ]);

        $response = $this->actingAs($student)
            ->get(route('assigned-slips.fillup', $assignment))
            ->assertOk()
            ->assertSee('Grace')
            ->assertSee('Hopper')
            ->assertSee('CIF-1003')
            ->assertSee($student->email);

        foreach (['last_name', 'first_name', 'student_id', 'email'] as $field) {
            $this->assertMatchesRegularExpression(
                '/<input\b(?=[^>]*\bid="'.$field.'")(?=[^>]*\breadonly\b)[^>]*>/s',
                $response->getContent(),
                "The {$field} field should be read-only when populated from the logged-in account."
            );
        }
    }

    public function test_client_information_form_keeps_unimported_contact_number_editable(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create([
            'name' => 'Grace Hopper',
            'id_number' => 'CIF-1002',
            'gender' => 'female',
            'role' => 'student',
        ]);
        StudentReference::create([
            'id_number' => 'CIF-1002',
            'name' => 'Grace Hopper',
            'gender' => 'female',
            'course' => 'Computer Science',
            'year' => '4',
            'source_data' => ['First Name' => 'Grace', 'Last Name' => 'Hopper'],
        ]);
        $assignment = SlipAssignment::create([
            'assigned_by' => $teacher->id,
            'student_id' => $student->id,
            'slip' => 'qf-bo-oss-04-client-information-form',
        ]);

        $response = $this->actingAs($student)
            ->get(route('assigned-slips.fillup', $assignment))
            ->assertOk();
        $this->assertMatchesRegularExpression(
            '/<input\b(?=[^>]*\bid="contact_number")(?![^>]*\breadonly\b)[^>]*>/s',
            $response->getContent()
        );

        $details = [];
        foreach (SlipCatalog::all()['qf-bo-oss-04-client-information-form']['groups'] as $group) {
            foreach ($group['fields'] as $field) {
                if ($field['type'] === 'checkboxes') {
                    $details[$field['key']] = $field['required']
                        ? [array_key_first($field['options'])]
                        : [];
                } elseif ($field['required']) {
                    $details[$field['key']] = match ($field['key']) {
                        'contact_number' => '09876543210',
                        'address' => 'Student address',
                        default => 'Student answer',
                    };
                }
            }
        }

        $this->actingAs($student)
            ->post(route('assigned-slips.submit', $assignment), ['details' => $details])
            ->assertSessionHasNoErrors();

        $submission = SlipSubmission::query()->where('user_id', $student->id)->firstOrFail();
        $this->assertSame('09876543210', $submission->data['contact_number']);
    }

    public function test_every_slip_can_be_submitted_and_its_pdf_viewed(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create([
            'id_number' => 'SLIP-1001',
            'gender' => 'female',
            'role' => 'teacher',
        ]);
        $user = User::factory()->create([
            'id_number' => 'SLIP-STUDENT-1001',
            'gender' => 'female',
            'role' => 'student',
        ]);

        $this->actingAs($teacher)
            ->get(route('slips.index'))
            ->assertOk()
            ->assertSee('Guidance and Counseling Slips');

        $this->actingAs($user)
            ->get(route('slips.index'))
            ->assertRedirect(route('student.dashboard'));
        $this->actingAs($user)
            ->get('/cns')
            ->assertNotFound();

        foreach (SlipCatalog::all() as $slug => $slip) {
            if ($slug === 'student-referral-slip') {
                continue;
            }

            $relatedAssignmentId = null;
            if ($slug === 'class-admission-slip') {
                $referralSubmission = SlipSubmission::create([
                    'user_id' => $user->id,
                    'slip' => 'student-referral-slip',
                    'data' => [
                        'student_name' => $user->name,
                        'sex' => 'Female',
                        'program_year' => 'BSIT - First Year',
                        'date_absent' => '2026-10-01',
                        'days_absent' => 1,
                    ],
                    'pdf_path' => 'slip-submissions/referral-test.pdf',
                ]);
                $relatedAssignmentId = SlipAssignment::create([
                    'assigned_by' => $teacher->id,
                    'student_id' => $user->id,
                    'slip' => 'student-referral-slip',
                    'status' => 'submitted',
                    'slip_submission_id' => $referralSubmission->id,
                ])->id;
            }

            $assignment = SlipAssignment::create([
                'assigned_by' => $teacher->id,
                'student_id' => $user->id,
                'related_assignment_id' => $relatedAssignmentId,
                'slip' => $slug,
            ]);

            $this->actingAs($user)
                ->get(route('assigned-slips.fillup', $assignment))
                ->assertOk()
                ->assertSee($slip['title']);

            $details = [];

            foreach ($slip['groups'] as $group) {
                foreach ($group['fields'] as $field) {
                    if ($field['type'] === 'checkboxes') {
                        $details[$field['key']] = $field['required']
                            ? [array_key_first($field['options'])]
                            : [];
                    } elseif ($field['required']) {
                        $details[$field['key']] = $field['type'] === 'date'
                            ? '2026-10-07'
                            : ($field['type'] === 'number' ? 1 : 'Sample answer');
                    }
                }
            }

            $response = $this->actingAs($user)->post(route('assigned-slips.submit', $assignment), [
                'details' => $details,
            ]);
            $response->assertSessionHasNoErrors();

            $submission = SlipSubmission::query()
                ->where('user_id', $user->id)
                ->where('slip', $slug)
                ->latest('id')
                ->firstOrFail();

            $response->assertRedirect(route('slips.submissions.show', $submission));
            $this->assertSame('submitted', $assignment->fresh()->status);
            Storage::disk('local')->assertExists($submission->pdf_path);
            $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($submission->pdf_path));

            $this->actingAs($user)
                ->get(route('slips.submissions.show', $submission))
                ->assertOk()
                ->assertSee('Completed slip PDF')
                ->assertSee(route('student.dashboard'), false);
            $this->actingAs($user)
                ->get(route('slips.submissions.pdf', $submission))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
            $this->actingAs($user)
                ->get(route('slip-history.index'))
                ->assertOk()
                ->assertSee('data-system-theme', false)
                ->assertSee('bg-[#f6f5f1]', false)
                ->assertSee('dark:bg-[#111712]', false)
                ->assertSee('dark:bg-zinc-900', false)
                ->assertSee('dark:text-[#91dfa6]', false)
                ->assertSee($slip['title'])
                ->assertSee('Slip History');
        }
    }

    public function test_teacher_referral_sidebar_lists_forms_and_opens_the_selected_preview(): void
    {
        $teacher = User::factory()->create([
            'id_number' => 'TEACHER-1001',
            'gender' => 'female',
            'role' => 'teacher',
        ]);

        $dashboard = $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('data-system-theme', false)
            ->assertSee('bg-[#09ba44]', false)
            ->assertSee('dark:bg-[#20783e]', false)
            ->assertSee('Teacher Dashboard')
            ->assertSee('Create Student Referral')
            ->assertSee(route('slips.fillup', 'student-referral-slip'), false)
            ->assertSee('Referrals I Sent')
            ->assertSee('#referrals')
            ->assertSee('Guidance Slips')
            ->assertSee('Referral')
            ->assertSee('Student Referral Slip')
            ->assertDontSee('Certificate of No Scholarship')
            ->assertDontSee('Client Information Form')
            ->assertDontSee('Class Admission Slip')
            ->assertDontSee('Student Leave of Absence Slip');

        $this->assertStringStartsWith('/guidance-slips?', route('slips.index', ['preview' => 'student-referral-slip'], false));

        $this->actingAs($teacher)
            ->get(route('slips.index', ['preview' => 'student-referral-slip']))
            ->assertOk()
            ->assertSee('modal-show')
            ->assertSee('student-referral-slip-modal')
            ->assertDontSee('qf-bo-oss-04-client-information-form-modal');

        $counselor = User::factory()->create([
            'id_number' => 'COUNSELOR-SLIPS-1',
            'gender' => 'male',
            'role' => 'guidance_counselor',
        ]);
        $this->actingAs($counselor)
            ->get(route('slips.index'))
            ->assertRedirect(route('guidance.dashboard'));
    }

    public function test_only_teachers_can_assign_forms_to_students(): void
    {
        $teacher = User::factory()->create([
            'id_number' => 'FLOW-TEACHER-1',
            'gender' => 'female',
            'role' => 'teacher',
        ]);
        $student = User::factory()->create([
            'id_number' => 'FLOW-STUDENT-1',
            'gender' => 'female',
            'role' => 'student',
        ]);
        $counselor = User::factory()->create([
            'id_number' => 'FLOW-COUNSELOR-1',
            'gender' => 'male',
            'role' => 'guidance_counselor',
        ]);

        $this->actingAs($student)
            ->post(route('slips.assign', 'student-referral-slip'), ['student_id' => $student->id])
            ->assertForbidden();
        $this->actingAs($counselor)
            ->post(route('slips.assign', 'student-referral-slip'), ['student_id' => $student->id])
            ->assertForbidden();
        $this->actingAs($teacher)
            ->post(route('slips.assign', 'class-admission-slip'), ['student_id' => $student->id])
            ->assertNotFound();

        $this->assertDatabaseCount('slip_assignments', 0);
    }

    public function test_counselor_issues_cif_only_once_and_class_admission_after_a_received_referral(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create([
            'id_number' => 'FLOW-TEACHER-2',
            'gender' => 'female',
            'role' => 'teacher',
        ]);
        $counselor = User::factory()->create([
            'id_number' => 'FLOW-COUNSELOR-2',
            'gender' => 'male',
            'role' => 'guidance_counselor',
        ]);
        $student = User::factory()->create([
            'name' => 'First Visit Student',
            'id_number' => 'FLOW-STUDENT-2',
            'gender' => 'female',
            'role' => 'student',
        ]);

        $referral = $this->sendTeacherReferral($teacher, $student);

        $this->actingAs($counselor)
            ->get(route('guidance.dashboard'))
            ->assertOk()
            ->assertSee('data-system-theme', false)
            ->assertSee('bg-[#f6f5f1]', false)
            ->assertSee('dark:bg-[#111712]', false)
            ->assertSee('bg-[#09ba44]', false)
            ->assertSee('dark:bg-[#20783e]', false)
            ->assertSee('Guidance Dashboard')
            ->assertSee('Review Submissions')
            ->assertSee('Received Referrals')
            ->assertSee('#received-referrals')
            ->assertSee('Received Student Referral Slips')
            ->assertSee($student->name)
            ->assertSee('Issue CIF (first-time)')
            ->assertSee('Issue Class Admission');

        $this->actingAs($counselor)
            ->post(route('guidance.slip-review.issue', [$referral, 'qf-bo-oss-04-client-information-form']))
            ->assertRedirect(route('guidance.dashboard'));
        $clientInformation = SlipAssignment::query()
            ->where('slip', 'qf-bo-oss-04-client-information-form')
            ->firstOrFail();

        $this->assertSame($referral->id, $clientInformation->related_assignment_id);

        $this->actingAs($counselor)
            ->post(route('guidance.slip-review.issue', [$referral, 'qf-bo-oss-04-client-information-form']))
            ->assertStatus(409);

        $secondReferral = $this->sendTeacherReferral($teacher, $student);
        $this->actingAs($counselor)
            ->post(route('guidance.slip-review.issue', [$secondReferral, 'qf-bo-oss-04-client-information-form']))
            ->assertStatus(409);

        $this->actingAs($counselor)
            ->post(route('guidance.slip-review.issue', [$referral, 'class-admission-slip']))
            ->assertRedirect(route('guidance.dashboard'));
        $classAdmission = SlipAssignment::query()
            ->where('slip', 'class-admission-slip')
            ->firstOrFail();

        $this->actingAs($student)
            ->get(route('assigned-slips.fillup', $classAdmission))
            ->assertOk()
            ->assertSee('Cause/s of Absences')
            ->assertSee($student->name)
            ->assertSee('2026-10-01')
            ->assertSee('1')
            ->assertDontSee('name="details[student_name]"', false)
            ->assertDontSee('name="details[date_absent]"', false)
            ->assertDontSee('name="details[days_absent]"', false)
            ->assertDontSee('name="details[referral_slip_number]"', false)
            ->assertDontSee('name="details[date_received]"', false)
            ->assertDontSee('name="details[actions_taken][]"', false)
            ->assertDontSee('name="details[excused_status]"', false);

        $this->actingAs($student)
            ->post(route('assigned-slips.submit', $classAdmission), [
                'details' => [
                    'student_id' => $student->id_number,
                    'absence_causes' => ['health_related', 'other'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'BSIT - First Year',
            $classAdmission->fresh()->slipSubmission->data['program_year']
        );
        $this->assertSame(
            ['health_related', 'other'],
            $classAdmission->fresh()->slipSubmission->data['absence_causes']
        );
        $this->assertSame('Female', $classAdmission->fresh()->slipSubmission->data['sex']);
        $this->assertSame('2026-10-01', $classAdmission->fresh()->slipSubmission->data['date_absent']);
        $this->assertSame(1, $classAdmission->fresh()->slipSubmission->data['days_absent']);

        $classAdmissionSubmission = $classAdmission->fresh()->slipSubmission;
        $this->actingAs($counselor)
            ->get(route('slips.submissions.show', $classAdmissionSubmission))
            ->assertOk()
            ->assertSee('Guidance Office Review')
            ->assertSee('Reference Referral Slip No.')
            ->assertSee('Date Received')
            ->assertSee('Action/s Taken')
            ->assertSee('Absence Decision');

        $pdfBeforeGuidanceReview = Storage::disk('local')->get($classAdmissionSubmission->pdf_path);
        $this->actingAs($counselor)
            ->post(route('guidance.slip-review.approve', $classAdmission), [])
            ->assertSessionHasErrors([
                'details.referral_slip_number',
                'details.date_received',
                'details.actions_taken',
                'details.excused_status',
            ]);

        $this->actingAs($counselor)
            ->post(route('guidance.slip-review.approve', $classAdmission), [
                'details' => [
                    'referral_slip_number' => 'SR-1001',
                    'date_received' => '2026-10-02',
                    'actions_taken' => ['verified_documents', 'interviewed_with_guidance'],
                    'excused_status' => 'excused',
                ],
            ])
            ->assertRedirect(route('guidance.slip-review'));

        $this->assertSame('approved', $classAdmission->fresh()->status);
        $this->assertSame('SR-1001', $classAdmissionSubmission->fresh()->data['referral_slip_number']);
        $this->assertSame('2026-10-02', $classAdmissionSubmission->fresh()->data['date_received']);
        $this->assertSame(
            ['verified_documents', 'interviewed_with_guidance'],
            $classAdmissionSubmission->fresh()->data['actions_taken']
        );
        $this->assertSame('excused', $classAdmissionSubmission->fresh()->data['excused_status']);
        $this->assertNotSame(
            $pdfBeforeGuidanceReview,
            Storage::disk('local')->get($classAdmissionSubmission->pdf_path)
        );
    }

    public function test_students_can_request_only_their_leave_withdrawal_and_readmission_forms(): void
    {
        $student = User::factory()->create([
            'id_number' => 'FLOW-STUDENT-3',
            'gender' => 'female',
            'role' => 'student',
        ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Request a Form from Guidance')
            ->assertSee('Student Leave of Absence Slip')
            ->assertSee('Student Withdrawal / Dropping / Shifting Counseling Slip')
            ->assertSee('Student Readmission Slip')
            ->assertDontSee('Student Referral Slip');

        $response = $this->actingAs($student)
            ->post(route('student.slip-requests.store', 'student-leaveof-absence-slip'));
        $assignment = SlipAssignment::firstOrFail();

        $response->assertRedirect(route('assigned-slips.fillup', $assignment));
        $this->assertSame($student->id, $assignment->assigned_by);
        $this->assertSame($student->id, $assignment->student_id);
        $this->assertSame('pending', $assignment->status);

        $this->actingAs($student)
            ->post(route('student.slip-requests.store', 'class-admission-slip'))
            ->assertNotFound();
        $this->actingAs($student)
            ->post(route('student.slip-requests.store', 'student-leaveof-absence-slip'))
            ->assertStatus(409);
    }

    public function test_student_dashboard_uses_only_database_assignments_and_submissions(): void
    {
        $teacher = User::factory()->create([
            'id_number' => 'DASH-TEACHER-1',
            'gender' => 'female',
            'role' => 'teacher',
        ]);
        $student = User::factory()->create([
            'id_number' => 'DASH-STUDENT-1',
            'gender' => 'male',
            'role' => 'student',
        ]);
        $pending = SlipAssignment::create([
            'assigned_by' => $teacher->id,
            'student_id' => $student->id,
            'slip' => 'student-leaveof-absence-slip',
        ]);
        SlipAssignment::create([
            'assigned_by' => $teacher->id,
            'student_id' => $student->id,
            'slip' => 'case-referral-slip',
        ]);
        $completedSubmission = SlipSubmission::create([
            'user_id' => $student->id,
            'slip' => 'student-referral-slip',
            'data' => [],
            'pdf_path' => 'slip-submissions/dashboard-test.pdf',
        ]);
        SlipAssignment::create([
            'assigned_by' => $teacher->id,
            'student_id' => $student->id,
            'slip' => 'student-referral-slip',
            'status' => 'submitted',
            'slip_submission_id' => $completedSubmission->id,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('data-system-theme', false)
            ->assertSee('bg-[#09ba44]', false)
            ->assertSee('dark:bg-[#20783e]', false)
            ->assertSee('Student Dashboard')
            ->assertSee('Assigned Slips')
            ->assertSee('Slip History')
            ->assertSee('Request a Form')
            ->assertSee('#request-forms')
            ->assertSee('Pending Assigned Slips')
            ->assertSee('Awaiting Approval')
            ->assertSee('Submitted Slips')
            ->assertSee('Student Referral Slip')
            ->assertSee('Student Leave of Absence Slip')
            ->assertDontSee('Case Referral Slip')
            ->assertDontSee('September 15, 2026')
            ->assertDontSee('Academic Concern');
        $response->assertViewHas('pendingAssignmentsCount', 1);
        $response->assertViewHas('awaitingApprovalCount', 1);
        $response->assertViewHas('submittedSlipsCount', 1);

        $this->actingAs($teacher)
            ->get(route('slip-history.index'))
            ->assertForbidden();
    }

    public function test_teacher_verifies_and_sends_a_completed_locked_referral(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create([
            'id_number' => 'TEACHER-2001',
            'gender' => 'female',
            'role' => 'teacher',
        ]);
        $student = User::factory()->create([
            'name' => 'Selected Student',
            'id_number' => 'STU-2001',
            'gender' => 'male',
            'role' => 'student',
        ]);
        $otherStudent = User::factory()->create([
            'id_number' => 'STU-2002',
            'gender' => 'female',
            'role' => 'student',
        ]);

        $this->actingAs($teacher)
            ->get(route('slips.fillup', 'student-referral-slip'))
            ->assertOk()
            ->assertSee('Verify Student Information')
            ->assertSee('Student ID No.')
            ->assertSee('Name of Student')
            ->assertSee('Date')
            ->assertSee('Sex')
            ->assertSee('Reason for Referral')
            ->assertSee('I verified the selected student')
            ->assertSee('Selected Student')
            ->assertDontSee('<option value="'.$teacher->id.'"', false);

        $this->actingAs($teacher)
            ->post(route('slips.assign', 'student-referral-slip'), [
                'student_id' => $student->id,
                'details' => $this->teacherReferralDetails($student),
            ])
            ->assertSessionHasErrors(['information_verified']);
        $this->assertSame(0, SlipAssignment::count());

        $details = $this->teacherReferralDetails($student);
        $details['student_id'] = 'FORGED-ID';
        $details['student_name'] = 'Incorrect Name';
        $details['sex'] = 'Female';

        $this->actingAs($teacher)
            ->post(route('slips.assign', 'student-referral-slip'), [
                'student_id' => $student->id,
                'information_verified' => '1',
                'details' => $details,
            ])
            ->assertRedirect(route('teacher.dashboard'));

        $assignment = SlipAssignment::firstOrFail();
        $this->assertSame($teacher->id, $assignment->assigned_by);
        $this->assertSame($student->id, $assignment->student_id);
        $this->assertSame('submitted', $assignment->status);
        $submission = $assignment->fresh()->slipSubmission;
        $this->assertNotNull($submission);
        $this->assertSame($student->id, $submission->user_id);
        $this->assertSame($student->id_number, $submission->data['student_id']);
        $this->assertSame($student->name, $submission->data['student_name']);
        $this->assertSame('Male', $submission->data['sex']);
        $this->assertSame(now()->toDateString(), $submission->data['request_date']);
        $this->assertTrue($submission->data['submitted_by_teacher']);
        $this->assertSame(['class_attendance'], $submission->data['referral_reasons']);
        Storage::disk('local')->assertExists($submission->pdf_path);

        $this->actingAs($student)
            ->get(route('assigned-slips.index'))
            ->assertOk()
            ->assertSee('data-system-theme', false)
            ->assertSee('bg-[#f6f5f1]', false)
            ->assertSee('dark:bg-[#111712]', false)
            ->assertSee('dark:bg-zinc-900', false)
            ->assertSee('bg-[#09ba44]', false)
            ->assertSee('dark:bg-[#20783e]', false)
            ->assertSee('Student Referral Slip')
            ->assertSee('View sent referral');
        $this->actingAs($student)
            ->get(route('assigned-slips.fillup', $assignment))
            ->assertOk()
            ->assertSee('Submit to Guidance Counselor')
            ->assertSee('Class Attendance')
            ->assertDontSee('name="details[referral_reasons][]"', false);
        $this->actingAs($otherStudent)
            ->get(route('assigned-slips.fillup', $assignment))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('assigned-slips.submit', $assignment), [
                'details' => [
                    'student_id' => 'FORGED',
                    'referral_reasons' => ['student_employment'],
                ],
            ])
            ->assertRedirect(route('slips.submissions.show', $submission));
        $this->assertSame(['class_attendance'], $submission->fresh()->data['referral_reasons']);
        $this->assertSame('submitted', $assignment->fresh()->status);

        $counselor = User::factory()->create([
            'id_number' => 'GUIDANCE-2001',
            'gender' => 'female',
            'role' => 'guidance_counselor',
        ]);

        $this->actingAs($counselor)
            ->get(route('guidance.slip-review'))
            ->assertOk()
            ->assertSee($student->name)
            ->assertSee('Student Referral Slip');

        $this->actingAs($counselor)
            ->post(route('guidance.slip-review.approve', $assignment))
            ->assertRedirect(route('guidance.slip-review'));
        $this->assertSame('approved', $assignment->fresh()->status);
        $this->assertSame('approved', $submission->fresh()->review_status);

        $this->actingAs($student)
            ->get(route('assigned-slips.fillup', $assignment))
            ->assertForbidden();
    }

    public function test_teacher_submitted_referral_is_locked_until_counselor_approval(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create([
            'id_number' => 'EDIT-TEACHER-1',
            'gender' => 'female',
            'role' => 'teacher',
        ]);
        $student = User::factory()->create([
            'id_number' => 'EDIT-STUDENT-1',
            'gender' => 'male',
            'role' => 'student',
        ]);
        $counselor = User::factory()->create([
            'id_number' => 'EDIT-COUNSELOR-1',
            'gender' => 'female',
            'role' => 'guidance_counselor',
        ]);

        $referral = $this->sendTeacherReferral($teacher, $student);
        $submission = $referral->fresh()->slipSubmission;

        $this->actingAs($counselor)
            ->get(route('guidance.slip-review'))
            ->assertOk()
            ->assertSee('Student Referral Slip')
            ->assertSee($student->name);

        $this->actingAs($student)
            ->get(route('assigned-slips.index'))
            ->assertOk()
            ->assertSee('Awaiting approval')
            ->assertSee('View sent referral')
            ->assertDontSee('Edit submission');
        $this->actingAs($student)
            ->get(route('assigned-slips.fillup', $referral))
            ->assertOk()
            ->assertSee('Submit to Guidance Counselor')
            ->assertDontSee('name="details[referral_reasons][]"', false);

        $this->actingAs($student)
            ->post(route('assigned-slips.submit', $referral), [
                'details' => [
                    'referral_reasons' => ['psychological_testing'],
                ],
            ])
            ->assertRedirect(route('slips.submissions.show', $submission));
        $this->assertSame(['class_attendance'], $submission->fresh()->data['referral_reasons']);
        $this->assertSame('submitted', $referral->fresh()->status);

        $this->actingAs($counselor)
            ->post(route('guidance.slip-review.approve', $referral))
            ->assertRedirect(route('guidance.slip-review'));

        $this->actingAs($student)
            ->get(route('assigned-slips.fillup', $referral))
            ->assertForbidden();
    }

    public function test_teacher_can_cancel_own_unapproved_referral_and_linked_forms(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create([
            'id_number' => 'CANCEL-TEACHER-1',
            'gender' => 'female',
            'role' => 'teacher',
        ]);
        $otherTeacher = User::factory()->create([
            'id_number' => 'CANCEL-TEACHER-2',
            'gender' => 'male',
            'role' => 'teacher',
        ]);
        $student = User::factory()->create([
            'id_number' => 'CANCEL-STUDENT-1',
            'gender' => 'male',
            'role' => 'student',
        ]);
        $counselor = User::factory()->create([
            'id_number' => 'CANCEL-COUNSELOR-1',
            'gender' => 'female',
            'role' => 'guidance_counselor',
        ]);

        $referral = $this->sendTeacherReferral($teacher, $student);
        $referralSubmission = $referral->fresh()->slipSubmission;
        $followupSubmission = SlipSubmission::create([
            'user_id' => $student->id,
            'slip' => 'class-admission-slip',
            'data' => ['student_name' => $student->name],
            'pdf_path' => 'slip-submissions/class-admission-test.pdf',
            'review_status' => 'submitted',
        ]);
        $followup = SlipAssignment::create([
            'assigned_by' => $counselor->id,
            'student_id' => $student->id,
            'related_assignment_id' => $referral->id,
            'slip' => 'class-admission-slip',
            'status' => 'submitted',
            'slip_submission_id' => $followupSubmission->id,
        ]);

        $this->actingAs($otherTeacher)
            ->post(route('teacher.referrals.cancel', $referral))
            ->assertNotFound();
        $this->assertSame('submitted', $referral->fresh()->status);

        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('Cancel referral');

        $this->actingAs($teacher)
            ->post(route('teacher.referrals.cancel', $referral))
            ->assertRedirect(route('teacher.dashboard'))
            ->assertSessionHas('status');

        $this->assertSame('cancelled', $referral->fresh()->status);
        $this->assertSame('cancelled', $referralSubmission->fresh()->review_status);
        $this->assertSame('cancelled', $followup->fresh()->status);
        $this->assertSame('cancelled', $followupSubmission->fresh()->review_status);

        $this->actingAs($counselor)
            ->get(route('guidance.slip-review'))
            ->assertOk()
            ->assertDontSee($student->name);

        $this->actingAs($student)
            ->get(route('assigned-slips.fillup', $followup))
            ->assertForbidden();
    }

    public function test_teacher_cannot_cancel_approved_referral(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create([
            'id_number' => 'CANCEL-APPROVED-TEACHER',
            'gender' => 'female',
            'role' => 'teacher',
        ]);
        $student = User::factory()->create([
            'id_number' => 'CANCEL-APPROVED-STUDENT',
            'gender' => 'male',
            'role' => 'student',
        ]);

        $referral = $this->sendTeacherReferral($teacher, $student);
        $referral->update(['status' => 'approved']);

        $this->actingAs($teacher)
            ->post(route('teacher.referrals.cancel', $referral))
            ->assertStatus(409);

        $this->assertSame('approved', $referral->fresh()->status);
    }

    public function test_student_cannot_open_another_students_slip_submission(): void
    {
        $owner = User::factory()->create([
            'id_number' => 'SLIP-1002',
            'gender' => 'female',
            'role' => 'student',
        ]);
        $otherStudent = User::factory()->create([
            'id_number' => 'SLIP-1003',
            'gender' => 'male',
            'role' => 'student',
        ]);
        $submission = SlipSubmission::create([
            'user_id' => $owner->id,
            'slip' => 'student-referral-slip',
            'data' => [],
            'pdf_path' => 'slip-submissions/test.pdf',
        ]);

        $this->actingAs($otherStudent)
            ->get(route('slips.submissions.show', $submission))
            ->assertForbidden();

        $this->actingAs($otherStudent)
            ->get(route('slip-history.index'))
            ->assertOk()
            ->assertDontSee('student-referral-slip');
    }

    /**
     * @return array<string, mixed>
     */
    private function teacherReferralDetails(User $student): array
    {
        return [
            'student_id' => $student->id_number,
            'student_name' => $student->name,
            'request_date' => now()->toDateString(),
            'program_year' => 'BSIT - First Year',
            'sex' => ucfirst($student->gender),
            'date_absent' => '2026-10-01',
            'days_absent' => 1,
            'referral_reasons' => ['class_attendance'],
        ];
    }

    private function sendTeacherReferral(User $teacher, User $student): SlipAssignment
    {
        $this->actingAs($teacher)
            ->post(route('slips.assign', 'student-referral-slip'), [
                'student_id' => $student->id,
                'information_verified' => '1',
                'details' => $this->teacherReferralDetails($student),
            ])
            ->assertRedirect(route('teacher.dashboard'));

        return SlipAssignment::query()
            ->where('slip', 'student-referral-slip')
            ->latest('id')
            ->firstOrFail();
    }
}
