<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CnsRequest;
use App\Models\SlipAssignment;
use App\Models\SlipSubmission;
use App\Models\StudentReference;
use App\Models\User;
use App\Services\SlipPdfGenerator;
use App\Services\CifDocxSampleGenerator;
use App\Services\CifDocxHtmlPreview;
use App\Support\SlipCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SlipSubmissionController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->role === 'student') {
            return redirect()->route('student.dashboard');
        }

        if ($request->user()->role === 'guidance_counselor') {
            return redirect()->route('guidance.dashboard');
        }

        if ($request->user()->role === 'teacher') {
            return redirect()->route('teacher.dashboard');
        }

        $slips = SlipCatalog::forRole($request->user()->role);
        $preview = $request->query('preview');
        $previewModal = is_string($preview) && isset($slips[$preview])
            ? $preview.'-modal'
            : null;

        return view('slips.index', [
            'slips' => $slips,
            'previewModal' => $previewModal,
        ]);
    }

    public function assignedIndex(Request $request): View
    {
        abort_unless($request->user()->role === 'student', 403);

        $assignments = SlipAssignment::query()
            ->with(['assignedBy', 'slipSubmission', 'cnsRequest', 'relatedAssignment.slipSubmission'])
            ->where('student_id', $request->user()->id)
            ->whereIn('slip', SlipCatalog::workflowSlugs())
            ->latest()
            ->get();

        return view('slips.assigned', [
            'assignments' => $assignments,
            'slips' => SlipCatalog::all(),
        ]);
    }

    public function teacherDashboard(Request $request): View
    {
        abort_unless($request->user()->role === 'teacher', 403);

        $referrals = SlipAssignment::query()
            ->where('assigned_by', $request->user()->id)
            ->where('slip', 'student-referral-slip')
            ->latest()
            ->limit(5)
            ->get();

        $completedAdmissions = SlipAssignment::query()
            ->where('slip', 'class-admission-slip')
            ->where('status', 'approved')
            ->whereHas('relatedAssignment', fn ($query) => $query
                ->where('assigned_by', $request->user()->id)
                ->where('slip', 'student-referral-slip'))
            ->latest('approved_at')
            ->limit(10)
            ->get();

        $referralAppointments = Appointment::query()
            ->with('requester')
            ->whereHas('referral', fn ($query) => $query
                ->where('assigned_by', $request->user()->id)
                ->where('slip', 'student-referral-slip'))
            ->latest('scheduled_at')
            ->limit(10)
            ->get();

        return view('teacher.teacher', compact('referrals', 'completedAdmissions', 'referralAppointments'));
    }

    public function teacherReferralsSent(Request $request): View
    {
        abort_unless($request->user()->role === 'teacher', 403);

        $referrals = SlipAssignment::query()
            ->with(['student', 'assignedBy', 'slipSubmission', 'followups.student', 'followups.assignedBy', 'followups.approver', 'followups.slipSubmission'])
            ->where('assigned_by', $request->user()->id)
            ->where('slip', 'student-referral-slip')
            ->where('status', '!=', 'cancelled')
            ->whereNull('teacher_acknowledged_at')
            ->latest()
            ->get();

        return view('teacher.referrals-sent', compact('referrals'));
    }

    public function teacherStudents(Request $request): View
    {
        abort_unless($request->user()->role === 'teacher', 403);

        $students = User::query()
            ->where('role', 'student')
            ->orderBy('name')
            ->get(['id', 'name', 'id_number', 'email', 'gender']);

        $studentReferences = StudentReference::query()
            ->whereIn('id_number', $students->pluck('id_number')->filter())
            ->get(['id_number', 'course', 'year'])
            ->keyBy('id_number');

        return view('teacher.students', compact('students', 'studentReferences'));
    }

    public function acknowledgeTeacherReferral(Request $request, SlipAssignment $slipAssignment): RedirectResponse
    {
        abort_unless($request->user()->role === 'teacher', 403);
        abort_unless($slipAssignment->assigned_by === $request->user()->id && $slipAssignment->slip === 'student-referral-slip', 404);

        $admission = $slipAssignment->followups()->where('slip', 'class-admission-slip')->where('status', 'approved')->first();
        abort_unless($admission?->student_sent_to_teacher_at !== null, 409, 'The student has not sent the Class Admission result to you yet.');

        $slipAssignment->update(['teacher_acknowledged_at' => now()]);

        return redirect()->route('teacher.referral-history')->with('status', 'Referral acknowledged and moved to history.');
    }

    public function sendClassAdmissionResultToTeacher(Request $request, SlipAssignment $slipAssignment): RedirectResponse
    {
        abort_unless(
            $request->user()->role === 'student'
                && (int) $slipAssignment->student_id === (int) $request->user()->id,
            403
        );
        abort_unless($slipAssignment->slip === 'class-admission-slip' && $slipAssignment->status === 'approved', 409);

        $referral = $slipAssignment->relatedAssignment;
        abort_unless(
            $referral !== null
                && $referral->slip === 'student-referral-slip'
                && (int) $referral->student_id === (int) $request->user()->id
                && $referral->assigned_by !== null
                && (bool) ($referral->slipSubmission?->data['submitted_by_teacher'] ?? false),
            409,
            'This Class Admission result is not linked to a teacher referral.'
        );

        if ($slipAssignment->student_sent_to_teacher_at === null) {
            $slipAssignment->update(['student_sent_to_teacher_at' => now()]);
        }

        return redirect()->route('assigned-slips.index')->with('status', 'The Class Admission result was sent to your teacher.');
    }

    public function teacherReferralHistory(Request $request): View
    {
        abort_unless($request->user()->role === 'teacher', 403);

        $referrals = SlipAssignment::query()
            ->with(['student', 'assignedBy', 'slipSubmission', 'followups.student', 'followups.assignedBy', 'followups.approver', 'followups.slipSubmission'])
            ->where('assigned_by', $request->user()->id)
            ->where('slip', 'student-referral-slip')
            ->whereNotNull('teacher_acknowledged_at')
            ->whereHas('followups', fn ($query) => $query->where('slip', 'class-admission-slip'))
            ->latest()
            ->get();

        return view('teacher.referral-history', compact('referrals'));
    }

    public function guidanceDashboard(Request $request): View
    {
        abort_unless($request->user()->role === 'guidance_counselor', 403);

        $referrals = SlipAssignment::query()
            ->with(['student', 'assignedBy', 'slipSubmission', 'appointments', 'followups.assignedBy', 'followups.approver'])
            ->where('slip', 'student-referral-slip')
            ->whereIn('status', ['submitted', 'approved'])
            ->latest('completed_at')
            ->get()
            ->map(function (SlipAssignment $referral) {
                $hasClassAdmission = SlipAssignment::query()
                    ->where('related_assignment_id', $referral->id)
                    ->where('slip', 'class-admission-slip')
                    ->exists();
                $referralData = $referral->slipSubmission?->data?->getArrayCopy() ?? [];

                return [
                    'assignment' => $referral,
                    'appointment' => $referral->appointments->first(),
                    'can_issue_client_information' => false,
                    'can_issue_class_admission' => ! $hasClassAdmission,
                    'referral_pdf_ready' => $this->hasReferralDocumentControlData($referralData),
                    'missing_document_control_identifiers' => ! $this->hasReferralDocumentControlIdentifiers($referralData),
                    'document_control_data' => $referralData,
                ];
            })
            ->filter(fn (array $referral) =>
                $referral['can_issue_class_admission']
                || ! $referral['referral_pdf_ready']
                || $referral['appointment'] !== null
            )
            ->values();
        $referralsMissingDocumentControlCount = $referrals
            ->where('missing_document_control_identifiers', true)
            ->count();
        return view('admin.admin_dashboard', [
            'referrals' => $referrals,
            'totalStudentsCount' => User::query()->where('role', 'student')->where('is_active', true)->count(),
            'archivedStudentsCount' => User::query()->where('role', 'student')->where(function ($query) {
                $query->where('is_active', false)->orWhere('is_graduated', true);
            })->count(),
            'hasImportedStudentFile' => \App\Models\StudentReference::query()->exists(),
            'todayAppointmentsCount' => Appointment::query()->whereDate('scheduled_at', today())->count(),
            'todayAppointments' => Appointment::query()
                ->with('requester')
                ->whereDate('scheduled_at', today())
                ->orderBy('scheduled_at')
                ->get(),
            'todayMissedDoneAppointments' => Appointment::query()
                ->with('requester')
                ->whereDate('scheduled_at', today())
                ->whereIn('status', ['done', 'missed'])
                ->orderBy('scheduled_at')
                ->get(),
            'pendingAppointments' => Appointment::query()
                ->with('requester')
                ->where('status', 'pending')
                ->latest()
                ->get(),
            'calendarAppointments' => Appointment::query()
                ->with('requester')
                ->whereNotNull('scheduled_at')
                ->whereIn('status', ['accepted', 'done', 'missed'])
                ->orderBy('scheduled_at')
                ->get(),
            'activeReferralsCount' => $referralsMissingDocumentControlCount,
            'activeReferrals' => SlipAssignment::query()
                ->with('student')
                ->where('slip', 'student-referral-slip')
                ->where('status', 'pending')
                ->latest('completed_at')
                ->get(),
            'upcomingAppointments' => Appointment::query()
                ->with('requester')
                ->where('scheduled_at', '>=', now())
                ->whereIn('status', ['pending', 'accepted'])
                ->orderBy('scheduled_at')
                ->limit(6)
                ->get(),
            'missedDoneAppointments' => Appointment::query()
                ->with('requester')
                ->whereIn('status', ['done', 'missed'])
                ->latest('completed_at')
                ->limit(10)
                ->get(),
            'missedDoneAppointmentsCount' => Appointment::query()
                ->whereIn('status', ['done', 'missed'])
                ->count(),
            'pendingAppointmentsCount' => Appointment::query()->where('status', 'pending')->count(),
            'pendingApprovalsCount' => SlipAssignment::query()
                ->where('status', 'submitted')
                ->whereIn('slip', SlipCatalog::workflowSlugs())
                ->count(),
        ]);
    }

    public function guidanceReferrals(Request $request): View
    {
        abort_unless($request->user()->role === 'guidance_counselor', 403);

        $referrals = SlipAssignment::query()
            ->with([
                'student',
                'slipSubmission',
                'appointments' => fn ($query) => $query->latest('created_at'),
            ])
            ->where('slip', 'student-referral-slip')
            ->whereIn('status', ['submitted', 'approved'])
            ->latest('completed_at')
            ->get()
            ->map(function (SlipAssignment $referral) {
                $hasClassAdmission = SlipAssignment::query()
                    ->where('related_assignment_id', $referral->id)
                    ->where('slip', 'class-admission-slip')
                    ->exists();
                $referralData = $referral->slipSubmission?->data?->getArrayCopy() ?? [];

                return [
                    'assignment' => $referral,
                    'can_issue_class_admission' => ! $hasClassAdmission,
                    'referral_pdf_ready' => $this->hasReferralDocumentControlData($referralData),
                    'document_control_data' => $referralData,
                    'client_information_submission' => SlipSubmission::query()
                        ->where('user_id', $referral->student_id)
                        ->where('slip', 'qf-bo-oss-04-client-information-form')
                        ->latest()
                        ->first(),
                    'appointments' => Appointment::query()
                        ->with('reviewer')
                        ->where(function ($query) use ($referral) {
                            $query->where('user_id', $referral->student_id)
                                ->orWhere('referral_id', $referral->id);
                        })
                        ->where('status', 'done')
                        ->orderBy('scheduled_at')
                        ->get(),
                    'appointment' => $referral->appointments->first(),
                ];
            })
            ->filter(fn (array $referral) => $referral['can_issue_class_admission'] || ! $referral['referral_pdf_ready'])
            ->values();

        return view('admin.guidance_referrals', [
            'referrals' => $referrals,
            'cifDefinition' => SlipCatalog::all()['qf-bo-oss-04-client-information-form'],
            'cifSampleData' => $this->clientInformationSampleData(),
        ]);
    }

    public function guidanceCifIndex(Request $request): View
    {
        abort_unless($request->user()->role === 'guidance_counselor', 403);

        $submissions = SlipSubmission::query()
            ->with('user')
            ->where('slip', 'qf-bo-oss-04-client-information-form')
            ->latest()
            ->get();

        return view('admin.guidance_cif_index', compact('submissions'));
    }

    public function guidanceCifShow(
        Request $request,
        SlipSubmission $slipSubmission
    ): View {
        abort_unless($request->user()->role === 'guidance_counselor', 403);
        abort_unless($slipSubmission->slip === 'qf-bo-oss-04-client-information-form', 404);

        $appointments = Appointment::query()
            ->with('reviewer')
            ->where(function ($query) use ($slipSubmission) {
                $query->where('user_id', $slipSubmission->user_id)
                    ->orWhereHas('referral', fn ($referralQuery) => $referralQuery->where('student_id', $slipSubmission->user_id));
            })
            ->where('status', 'done')
            ->orderBy('scheduled_at')
            ->get();

        return view('admin.guidance_cif_show', [
            'submission' => $slipSubmission,
            'appointments' => $appointments,
        ]);
    }

    public function guidanceCifDocx(Request $request, SlipSubmission $slipSubmission, CifDocxSampleGenerator $docxGenerator): Response
    {
        abort_unless($request->user()->role === 'guidance_counselor', 403);
        abort_unless($slipSubmission->slip === 'qf-bo-oss-04-client-information-form', 404);

        $appointments = Appointment::query()
            ->with('reviewer')
            ->where(function ($query) use ($slipSubmission) {
                $query->where('user_id', $slipSubmission->user_id)
                    ->orWhereHas('referral', fn ($referralQuery) => $referralQuery->where('student_id', $slipSubmission->user_id));
            })
            ->where('status', 'done')
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Appointment $appointment): array => [
                'date' => ($appointment->scheduled_at ?? $appointment->completed_at)?->format('m/d/Y'),
                'concern' => $appointment->concerns ?? $appointment->purpose,
                'action' => $appointment->action_taken,
                'counselor' => $appointment->reviewer?->name ?? $request->user()->name,
            ])
            ->all();

        return response($docxGenerator->generate(
            $slipSubmission->data?->getArrayCopy() ?? [],
            $appointments,
            $request->user()->name,
        ))
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->header('Content-Disposition', 'inline; filename="cif-'.$slipSubmission->id.'-filled.docx"');
    }

    public function sampleClientInformationPdf(Request $request, SlipPdfGenerator $pdfGenerator): Response
    {
        abort_unless($request->user()->role === 'guidance_counselor', 403);

        $submission = new SlipSubmission([
            'slip' => 'qf-bo-oss-04-client-information-form',
            'data' => $this->clientInformationSampleData(),
        ]);

        return response($pdfGenerator->generate(
            $submission,
            SlipCatalog::all()['qf-bo-oss-04-client-information-form']
        ))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="sample-cif-alignment.pdf"');
    }

    public function sampleClientInformationDocx(Request $request, CifDocxSampleGenerator $docxGenerator): Response
    {
        abort_unless($request->user()->role === 'guidance_counselor', 403);

        return response($docxGenerator->generate($this->clientInformationSampleData()))
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->header('Content-Disposition', 'attachment; filename="sample-cif-filled.docx"');
    }

    public function clientInformationDocx(Request $request, SlipAssignment $referral, CifDocxSampleGenerator $docxGenerator): Response
    {
        abort_unless($request->user()->role === 'guidance_counselor', 403);
        abort_unless($referral->slip === 'student-referral-slip', 404);

        $submission = SlipSubmission::query()
            ->where('user_id', $referral->student_id)
            ->where('slip', 'qf-bo-oss-04-client-information-form')
            ->latest()
            ->firstOrFail();
        $appointments = Appointment::query()
            ->with('reviewer')
            ->where('user_id', $referral->student_id)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Appointment $appointment): array => [
                'date' => ($appointment->scheduled_at ?? $appointment->completed_at)?->format('m/d/Y'),
                'concern' => $appointment->concerns ?? $appointment->purpose,
                'action' => $appointment->action_taken,
                'counselor' => $appointment->reviewer?->name ?? $request->user()->name,
            ])
            ->all();

        return response($docxGenerator->generate(
            $submission->data?->getArrayCopy() ?? [],
            $appointments,
            $request->user()->name,
        ))
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->header('Content-Disposition', 'attachment; filename="cif-'.$referral->student_id.'-filled.docx"');
    }

    public function clientInformationDocxPreview(
        Request $request,
        SlipAssignment $referral,
        CifDocxSampleGenerator $docxGenerator,
        CifDocxHtmlPreview $htmlPreview
    ): View {
        abort_unless($request->user()->role === 'guidance_counselor', 403);
        abort_unless($referral->slip === 'student-referral-slip', 404);

        $submission = SlipSubmission::query()
            ->where('user_id', $referral->student_id)
            ->where('slip', 'qf-bo-oss-04-client-information-form')
            ->latest()
            ->firstOrFail();
        $appointments = Appointment::query()
            ->with('reviewer')
            ->where('user_id', $referral->student_id)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Appointment $appointment): array => [
                'date' => ($appointment->scheduled_at ?? $appointment->completed_at)?->format('m/d/Y'),
                'concern' => $appointment->concerns ?? $appointment->purpose,
                'action' => $appointment->action_taken,
                'counselor' => $appointment->reviewer?->name ?? $request->user()->name,
            ])
            ->all();

        return view('admin.sample_cif_docx_preview', [
            'documentHtml' => $htmlPreview->render($docxGenerator->generate(
                $submission->data?->getArrayCopy() ?? [],
                $appointments,
                $request->user()->name,
            )),
            'downloadUrl' => route('guidance.referrals.cif-docx', $referral),
        ]);
    }

    public function sampleClientInformationDocxPreview(Request $request, CifDocxSampleGenerator $docxGenerator, CifDocxHtmlPreview $htmlPreview): View
    {
        abort_unless($request->user()->role === 'guidance_counselor', 403);

        return view('admin.sample_cif_docx_preview', [
            'documentHtml' => $htmlPreview->render($docxGenerator->generate($this->clientInformationSampleData())),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function clientInformationSampleData(): array
    {
        return [
                'last_name' => 'Ixia',
                'first_name' => 'Ixia',
                'middle_name' => 'Ix.',
                'student_id' => '1503',
                'contact_number' => '0912 345 6789',
                'course_year' => 'BTLED-IA 3',
                'birth_date' => 'October 8, 2003',
                'sex' => 'Female',
                'civil_status' => 'Single',
                'nationality' => 'Filipino',
                'birth_place' => 'Baguio City, Benguet',
                'email' => 'ixia.ixia@example.com',
                'languages' => 'Filipino, English, Ilocano',
                'address' => 'Barangay Loakan, Baguio City, Benguet',
                'disability' => 'None',
                'indigenous_group' => 'None',
                'pwd' => 'No',
                'student_parent' => 'No',
                'financial_sources' => 'Parents and personal savings',
                'parent_marital_status' => 'Married',
                'father_name' => 'Juan Ixia',
                'mother_name' => 'Maria Ixia',
                'guardian_name' => 'Maria Ixia',
                'father_age' => '48',
                'mother_age' => '46',
                'guardian_relationship' => 'Mother',
                'father_occupation' => 'Civil Engineer',
                'mother_occupation' => 'Public School Teacher',
                'guardian_occupation' => 'Public School Teacher',
                'father_contact' => '0917 222 3344',
                'mother_contact' => '0918 555 6677',
                'guardian_contact' => '0918 555 6677',
                'siblings' => 'Ivy Ixia, 20, BS Information Technology, Single, Student',
                'educational_background' => "Senior High School | Benguet National High School | 2022 | With Honors\nJunior High School | Benguet National High School | 2018 | Conduct Award\nElementary | Loakan Elementary School | 2014 | Academic Excellence",
                'about_me' => "Responsible, kind, and determined\nHardworking and supportive\nCaring and patient\nIvy, because she listens to me\nSafe and grateful\nI enjoyed reading and drawing\nHelpful and inspiring\nI enjoy writing stories\nI feel hopeful and excited\nTo finish my degree and help my family",
                'consent' => ['agree'],
                'consent_name' => 'Ixia Ixia',
        ];
    }

    public function reviewIndex(Request $request): View
    {
        abort_unless($request->user()->role === 'guidance_counselor', 403);

        $assignments = SlipAssignment::query()
            ->with(['student', 'assignedBy', 'slipSubmission', 'cnsRequest'])
            ->where('status', 'submitted')
            ->whereIn('slip', SlipCatalog::workflowSlugs())
            ->latest('completed_at')
            ->get();

        return view('admin.slip-review', [
            'assignments' => $assignments,
            'slips' => SlipCatalog::all(),
        ]);
    }

    public function approve(
        Request $request,
        SlipAssignment $slipAssignment,
        SlipPdfGenerator $pdfGenerator
    ): RedirectResponse {
        abort_unless($request->user()->role === 'guidance_counselor', 403);

        $guidanceData = [];
        if ($slipAssignment->slip === 'class-admission-slip') {
            $guidanceData = $this->validatedGuidanceData($request, SlipCatalog::all()[$slipAssignment->slip]);
        }

        DB::transaction(function () use ($request, $slipAssignment, $guidanceData, $pdfGenerator) {
            $assignment = SlipAssignment::query()
                ->whereKey($slipAssignment->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($assignment->status === 'submitted', 409);

            if ($assignment->slip === 'cns') {
                $cnsRequest = $assignment->cnsRequest;
                abort_unless($cnsRequest !== null, 409);
                $cnsRequest->update([
                    'review_status' => 'approved',
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);
            } else {
                $submission = $assignment->slipSubmission;
                abort_unless($submission !== null, 409);

                if ($assignment->slip === 'class-admission-slip') {
                    $definition = SlipCatalog::all()[$assignment->slip];
                    $submission->update([
                        'data' => [
                            ...$submission->data->getArrayCopy(),
                            ...$guidanceData,
                        ],
                    ]);

                    if (! Storage::disk('local')->put(
                        $submission->pdf_path,
                        $pdfGenerator->generate($submission->fresh(), $definition)
                    )) {
                        throw new \RuntimeException('The completed Class Admission Slip PDF could not be stored.');
                    }
                }

                $submission->update([
                    'review_status' => 'approved',
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);
            }

            $assignment->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
        });

        return redirect()
            ->route('guidance.slip-review')
            ->with('status', 'The submitted form has been approved.');
    }

    public function history(Request $request): View
    {
        abort_unless($request->user()->role === 'student', 403);

        $slips = SlipCatalog::all();
        $referralAssignments = SlipAssignment::query()
            ->where('student_id', $request->user()->id)
            ->where(function ($query) {
                $query->where('slip', 'student-referral-slip')
                    ->orWhereHas('relatedAssignment', fn ($related) => $related->where('slip', 'student-referral-slip'));
            })
            ->latest()
            ->get();
        $history = SlipSubmission::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(function (SlipSubmission $submission) use ($slips) {
                return [
                    'title' => $slips[$submission->slip]['title'] ?? 'Assigned Slip',
                    'submitted_at' => $submission->created_at,
                    'status' => $submission->review_status,
                    'show_url' => route('slips.submissions.show', $submission),
                    'pdf_url' => route('slips.submissions.pdf', $submission),
                ];
            })
            ->concat(
                $referralAssignments
                    ->filter(fn (SlipAssignment $assignment) => ! $assignment->slip_submission_id)
                    ->map(fn (SlipAssignment $assignment) => [
                        'title' => $slips[$assignment->slip]['title'] ?? str($assignment->slip)->replace('-', ' ')->title(),
                        'submitted_at' => $assignment->completed_at ?? $assignment->created_at,
                        'status' => $assignment->status,
                        'show_url' => null,
                        'pdf_url' => null,
                    ])
            )
            ->concat(
                CnsRequest::query()
                    ->where('user_id', $request->user()->id)
                    ->latest()
                    ->get()
                    ->map(fn (CnsRequest $cnsRequest) => [
                        'title' => 'Certificate of No Scholarship',
                        'submitted_at' => $cnsRequest->created_at,
                        'status' => $cnsRequest->review_status,
                        'show_url' => route('cns.requests.show', $cnsRequest),
                        'pdf_url' => route('cns.requests.pdf', $cnsRequest),
                    ])
            )
            ->sortByDesc('submitted_at')
            ->values();

        return view('slips.history', compact('history'));
    }

    public function assign(
        Request $request,
        string $slip,
        SlipPdfGenerator $pdfGenerator
    ): RedirectResponse
    {
        abort_unless($request->user()->role === 'teacher', 403);
        abort_unless(isset(SlipCatalog::forRole('teacher')[$slip]), 404);

        if ($slip === 'student-referral-slip') {
            $validated = $request->validate([
                'student_id' => [
                    'required',
                    Rule::exists('users', 'id')->where('role', 'student'),
                ],
                'information_verified' => ['accepted'],
            ]);
            $request->validate([
                'details.days_absent' => ['required', 'integer', 'min:1', 'max:365'],
            ]);
            $daysAbsent = (int) $request->input('details.days_absent');
            $request->validate([
                'details.absence_dates' => ['required', 'array', 'size:'.$daysAbsent],
                'details.absence_dates.*' => ['required', 'date', 'distinct', 'before_or_equal:today'],
            ]);
            $absenceDates = $request->input('details.absence_dates');
            $request->merge([
                'details' => [
                    ...$request->input('details', []),
                    'date_absent' => $absenceDates[0],
                ],
            ]);
            $student = User::query()->findOrFail($validated['student_id']);
            $definition = SlipCatalog::all()[$slip];
            $data = $this->validatedData($request, $definition);
            $issuedAt = now()->toDateString();

            $data['student_id'] = $student->id_number;
            $data['student_name'] = $student->name;
            $data['request_date'] = $issuedAt;
            $data['sex'] = ucfirst($student->gender);
            $data['referred_by_name'] = $request->user()->name;
            $data['referred_date'] = $issuedAt;
            $data['received_by_name'] = null;
            $data['received_date'] = null;
            $data['document_code'] = null;
            $data['revision_number'] = null;
            $data['effectivity_date'] = null;
            $data['control_number'] = null;
            $data['submitted_by_teacher'] = true;
            $data['referred_student_id'] = $student->id;
            $data['referred_student_name'] = $student->name;
            $data['assigned_by'] = $request->user()->id;

            return DB::transaction(function () use ($request, $student, $slip, $data, $definition, $pdfGenerator, $absenceDates) {
                foreach ($absenceDates as $absenceDate) {
                    $referralData = [
                        ...$data,
                        'date_absent' => $absenceDate,
                        'days_absent' => 1,
                    ];
                    $assignment = SlipAssignment::create([
                        'assigned_by' => $request->user()->id,
                        'student_id' => $student->id,
                        'bundle_id' => (string) Str::uuid(),
                        'slip' => $slip,
                    ]);

                    $pdfPath = 'slip-submissions/'.Str::uuid().'.pdf';
                    $submission = SlipSubmission::create([
                        'user_id' => $student->id,
                        'slip' => $slip,
                        'data' => $referralData,
                        'pdf_path' => $pdfPath,
                        'review_status' => 'draft',
                    ]);

                    if (! Storage::disk('local')->put($pdfPath, $pdfGenerator->generate($submission, $definition))) {
                        throw new \RuntimeException('The referral draft PDF could not be stored.');
                    }

                    $assignment->update([
                        'slip_submission_id' => $submission->id,
                    ]);
                }

                return redirect()
                    ->route('teacher.dashboard')
                    ->with('status', count($absenceDates) === 1
                        ? 'The referral was sent to the student for review and submission to Guidance.'
                        : count($absenceDates).' referrals were sent to the student for review and submission to Guidance.');
            });
        }

        $validated = $request->validate([
            'student_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', 'student'),
            ],
        ]);

        SlipAssignment::create([
            'assigned_by' => $request->user()->id,
            'student_id' => $validated['student_id'],
            'slip' => $slip,
        ]);

        return redirect()
            ->route('slips.index')
            ->with('status', 'The form was assigned to the selected student.');
    }

    public function cancelTeacherReferral(Request $request, SlipAssignment $slipAssignment): RedirectResponse
    {
        abort_unless($request->user()->role === 'teacher', 403);

        DB::transaction(function () use ($request, $slipAssignment) {
            $assignment = SlipAssignment::query()
                ->whereKey($slipAssignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                (int) $assignment->assigned_by === (int) $request->user()->id
                    && $assignment->slip === 'student-referral-slip',
                404
            );
            abort_unless(
                in_array($assignment->status, ['pending', 'submitted'], true),
                409,
                'Only active referrals can be canceled.'
            );

            $submission = $assignment->slipSubmission;
            abort_unless(
                $submission !== null
                    && (bool) ($submission->data['submitted_by_teacher'] ?? false),
                409,
                'Only completed teacher-submitted referrals can be canceled.'
            );

            $submission->update([
                'review_status' => 'cancelled',
                'approved_by' => null,
                'approved_at' => null,
            ]);

            $followups = SlipAssignment::query()
                ->where('related_assignment_id', $assignment->id)
                ->lockForUpdate()
                ->get();

            foreach ($followups as $followup) {
                if ($followup->slipSubmission) {
                    $followup->slipSubmission->update([
                        'review_status' => 'cancelled',
                        'approved_by' => null,
                        'approved_at' => null,
                    ]);
                }

                $followup->update([
                    'status' => 'cancelled',
                    'approved_by' => null,
                    'approved_at' => null,
                ]);
            }

            $assignment->update(['status' => 'cancelled']);
        });

        return redirect()
            ->route('teacher.dashboard')
            ->with('status', 'The referral was canceled. Its linked forms are no longer active.');
    }

    public function updateTeacherReferralNotes(Request $request, SlipAssignment $slipAssignment): RedirectResponse
    {
        abort_unless($request->user()->role === 'teacher', 403);
        abort_unless(
            (int) $slipAssignment->assigned_by === (int) $request->user()->id
                && $slipAssignment->slip === 'student-referral-slip',
            404
        );

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000', function (string $attribute, mixed $value, \Closure $fail): void {
                $wordCount = preg_match_all('/\S+/u', (string) $value);

                if ($wordCount > 50) {
                    $fail('The comment may not exceed 50 words.');
                }
            }],
        ]);

        $slipAssignment->update(['notes' => filled($validated['notes'] ?? null) ? $validated['notes'] : null]);

        return back()->with('status', 'Referral comment saved.');
    }

    public function create(Request $request, string $slip): View
    {
        abort_unless(isset(SlipCatalog::forRole($request->user()->role)[$slip]), 403);

        $catalog = SlipCatalog::forRole($request->user()->role);
        abort_unless(isset($catalog[$slip]), 404);

        $students = User::query()
            ->where('role', 'student')
            ->orderBy('name')
            ->get(['id', 'name', 'id_number', 'email', 'gender']);

        $studentReferences = StudentReference::query()
            ->get(['id_number', 'year', 'course'])
            ->keyBy(fn (StudentReference $reference) => StudentReference::normalizeIdNumber($reference->id_number));

        if ($slip === 'student-referral-slip') {
            return view('slips.create-referral', [
                'title' => $catalog[$slip]['title'],
                'slip' => $catalog[$slip],
                'students' => $students,
                'studentReferences' => $studentReferences,
                'selectAction' => route('slips.assign', $slip),
                'cancelUrl' => route('teacher.dashboard'),
                'today' => now()->toDateString(),
            ]);
        }

        return view('slips.select-student', [
            'title' => $catalog[$slip]['title'],
            'students' => $students,
            'selectAction' => route('slips.assign', $slip),
            'cancelUrl' => route('slips.index'),
        ]);
    }

    public function requestForm(Request $request, string $slip): RedirectResponse
    {
        abort_unless($request->user()->role === 'student', 403);
        abort_unless(isset(SlipCatalog::studentRequestForms()[$slip]), 404);

        $assignment = DB::transaction(function () use ($request, $slip) {
            User::query()
                ->whereKey($request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            $activeRequestExists = SlipAssignment::query()
                ->where('student_id', $request->user()->id)
                ->where('slip', $slip)
                ->whereIn('status', ['pending', 'submitted'])
                ->exists();
            abort_unless(! $activeRequestExists, 409, 'You already have an active request for this form.');

            return SlipAssignment::create([
                'assigned_by' => $request->user()->id,
                'student_id' => $request->user()->id,
                'slip' => $slip,
            ]);
        });

        return redirect()->route('assigned-slips.fillup', $assignment);
    }

    public function cancelStudentSlipRequest(Request $request, SlipAssignment $slipAssignment): RedirectResponse
    {
        DB::transaction(function () use ($request, $slipAssignment): void {
            $assignment = SlipAssignment::query()
                ->whereKey($slipAssignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $request->user()->role === 'student'
                    && $assignment->student_id === $request->user()->id
                    && $assignment->assigned_by === $request->user()->id,
                403
            );
            abort_unless(isset(SlipCatalog::studentRequestForms()[$assignment->slip]), 404);
            abort_unless(
                $assignment->status === 'pending'
                    && $assignment->slip_submission_id === null,
                409,
                'Only unsubmitted requests can be canceled.'
            );

            $assignment->delete();
        });

        return redirect()
            ->route('student.dashboard')
            ->with('status', 'Your form request was canceled.');
    }

    public function issueFollowup(
        Request $request,
        SlipAssignment $referral,
        string $slip,
        SlipPdfGenerator $pdfGenerator
    ): RedirectResponse {
        abort_unless($request->user()->role === 'guidance_counselor', 403);
        abort_unless(in_array($slip, [
            'class-admission-slip',
        ], true), 404);

        DB::transaction(function () use ($request, $referral, $slip, $pdfGenerator) {
            $lockedReferral = SlipAssignment::query()
                ->whereKey($referral->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(
                $lockedReferral->slip === 'student-referral-slip'
                    && in_array($lockedReferral->status, ['submitted', 'approved'], true),
                409
            );

            $this->ensureFollowupFormMayBeIssued($slip, $lockedReferral);

            $referralSubmission = $lockedReferral->slipSubmission;
            abort_unless($referralSubmission !== null, 409, 'The submitted referral PDF could not be found.');
            $receivedAt = now()->toDateString();
            $referralSubmission->update([
                'data' => [
                    ...$referralSubmission->data->getArrayCopy(),
                    'received_by_name' => $request->user()->name,
                    'received_date' => $receivedAt,
                    'effectivity_date' => $receivedAt,
                ],
            ]);

            $referralPdf = $pdfGenerator->generate(
                $referralSubmission->fresh(),
                SlipCatalog::all()['student-referral-slip']
            );
            if (! Storage::disk('local')->put($referralSubmission->pdf_path, $referralPdf)) {
                throw new \RuntimeException('The updated referral slip PDF could not be stored.');
            }

            SlipAssignment::create([
                'assigned_by' => $request->user()->id,
                'student_id' => $lockedReferral->student_id,
                'bundle_id' => $lockedReferral->bundle_id,
                'related_assignment_id' => $lockedReferral->id,
                'slip' => $slip,
            ]);
        });

        return redirect()
            ->route('guidance.dashboard')
            ->with('status', 'The referral receipt was recorded and the Class Admission Slip was sent to the student. The referral PDF will be available after its document-control details are issued.');
    }

    public function updateReferralDocumentControl(
        Request $request,
        SlipAssignment $referral,
        SlipPdfGenerator $pdfGenerator
    ): RedirectResponse {
        abort_unless($request->user()->role === 'guidance_counselor', 403);
        $documentControlData = $this->validatedReferralDocumentControlData($request);

        DB::transaction(function () use ($request, $referral, $documentControlData, $pdfGenerator) {
            $lockedReferral = SlipAssignment::query()
                ->whereKey($referral->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(
                $lockedReferral->slip === 'student-referral-slip'
                    && in_array($lockedReferral->status, ['submitted', 'approved'], true),
                409,
                'Only referrals submitted by a student can receive document-control details.'
            );

            $referralSubmission = $lockedReferral->slipSubmission;
            abort_unless(
                $referralSubmission !== null && $this->isTeacherSubmittedReferral($lockedReferral),
                409,
                'The teacher-issued referral submission could not be found.'
            );
            $referralSubmission->update([
                'data' => [
                    ...$referralSubmission->data->getArrayCopy(),
                    ...$documentControlData,
                ],
            ]);

            $referralPdf = $pdfGenerator->generate(
                $referralSubmission->fresh(),
                SlipCatalog::all()['student-referral-slip']
            );
            if (! Storage::disk('local')->put($referralSubmission->pdf_path, $referralPdf)) {
                throw new \RuntimeException('The referral slip PDF could not be stored after document-control details were issued.');
            }
        });

        return redirect()
            ->route('guidance.dashboard')
            ->with('status', 'The referral document-control details were saved. The referral PDF is now available to the student and teacher.');
    }

    /**
     * @return array{document_code: string, revision_number: string, control_number: string}
     */
    private function validatedReferralDocumentControlData(Request $request): array
    {
        $validated = $request->validate([
            'details.document_code' => ['required', 'string', 'max:30'],
            'details.revision_number' => ['required', 'string', 'max:20'],
            'details.control_number' => ['required', 'string', 'max:30'],
        ]);

        return $validated['details'];
    }

    private function hasReferralDocumentControlData(array $data): bool
    {
        foreach (['document_code', 'revision_number', 'effectivity_date', 'control_number'] as $key) {
            if (! filled($data[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function hasReferralDocumentControlIdentifiers(array $data): bool
    {
        foreach (['document_code', 'revision_number', 'control_number'] as $key) {
            if (! filled($data[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function ensureFollowupFormMayBeIssued(string $slip, SlipAssignment $referral): void
    {
        abort_unless(
            ! SlipAssignment::query()
                ->where('related_assignment_id', $referral->id)
                ->where('slip', $slip)
                ->exists(),
            409,
            'This form has already been issued for the referral.'
        );
    }

    public function assignedCreate(Request $request, SlipAssignment $slipAssignment): View
    {
        abort_unless(
            $request->user()->role === 'student'
                && $slipAssignment->student_id === $request->user()->id,
            403
        );
        abort_unless(in_array($slipAssignment->status, ['pending', 'submitted'], true), 403);

        $isTeacherSubmittedReferral = $this->isTeacherSubmittedReferral($slipAssignment);

        $student = $request->user();

        $catalog = SlipCatalog::all();
        abort_unless(isset($catalog[$slipAssignment->slip]), 404);

        $clientInformationDefaults = $slipAssignment->slip === 'qf-bo-oss-04-client-information-form'
            ? $this->clientInformationDefaults($student)
            : [];
        $defaults = $slipAssignment->slip === 'class-admission-slip'
            ? [...$this->studentDefaults($student), ...$this->classAdmissionReferralData($slipAssignment)]
            : ($slipAssignment->slip === 'qf-bo-oss-04-client-information-form'
                ? [...$this->studentDefaults($student), ...$clientInformationDefaults, 'email' => $student->email]
                : $this->studentDefaults($student));

        if ($slipAssignment->slip === 'qf-bo-oss-04-client-information-form') {
            foreach ($catalog[$slipAssignment->slip]['groups'] as &$group) {
                foreach ($group['fields'] as &$field) {
                    if (filled($defaults[$field['key']] ?? null)) {
                        $field['readonly'] = true;
                    } else {
                        unset($field['readonly']);
                    }
                }
            }
            unset($group, $field);
        }

        return view('slips.fillup', [
            'slug' => $slipAssignment->slip,
            'slip' => $catalog[$slipAssignment->slip],
            'assignment' => $slipAssignment,
            'selectedStudent' => $student,
            'defaults' => $defaults,
            'existingSubmission' => $slipAssignment->slipSubmission,
            'isStudentRequest' => isset(SlipCatalog::studentRequestForms()[$slipAssignment->slip]),
            'isTeacherSubmittedReferral' => $isTeacherSubmittedReferral,
            'referralPdfReady' => $isTeacherSubmittedReferral
                ? $this->hasReferralDocumentControlData($slipAssignment->slipSubmission?->data?->getArrayCopy() ?? [])
                : true,
        ]);
    }

    public function assignedStore(
        Request $request,
        SlipAssignment $slipAssignment,
        SlipPdfGenerator $pdfGenerator,
        CifDocxSampleGenerator $docxGenerator
    ): RedirectResponse {
        abort_unless(
            $request->user()->role === 'student'
                && $slipAssignment->student_id === $request->user()->id,
            403
        );
        abort_unless(
            in_array($slipAssignment->status, ['pending', 'submitted'], true)
                && $slipAssignment->slip !== 'cns',
            403
        );

        $catalog = SlipCatalog::all();
        abort_unless(isset($catalog[$slipAssignment->slip]), 404);
        $definition = $catalog[$slipAssignment->slip];
        $student = $request->user();

        if ($this->isTeacherSubmittedReferral($slipAssignment)) {
            abort_unless($slipAssignment->status === 'pending', 403);
            abort_unless($slipAssignment->slipSubmission !== null, 409);

            // The teacher has already completed this referral. Students only acknowledge
            // and forward it to Guidance, so never accept replacement field values here.
            $data = $slipAssignment->slipSubmission->data->getArrayCopy();
        } else {
            $data = $this->validatedData($request, $definition);
        }

        if ($slipAssignment->slip === 'qf-bo-oss-04-client-information-form') {
            $data = [...$data, ...$this->clientInformationDefaults($student)];
        }

        if (! $this->isTeacherSubmittedReferral($slipAssignment)) {
            foreach ($this->studentDefaults($student) as $key => $value) {
                if ($value !== null && array_key_exists($key, $data)) {
                    $data[$key] = $value;
                }
            }
        }

        if ($slipAssignment->slip === 'class-admission-slip') {
            $data = [...$data, ...$this->classAdmissionReferralData($slipAssignment)];

            $existingData = $slipAssignment->slipSubmission?->data?->getArrayCopy() ?? [];
            $newAttachments = $this->validatedAttachments($request);
            if (($existingData['_attachments'] ?? []) !== [] || $newAttachments !== []) {
                $data['_attachments'] = [
                    ...($existingData['_attachments'] ?? []),
                    ...$newAttachments,
                ];
            }
        }

        $data['referred_student_id'] = $student->id;
        $data['referred_student_name'] = $student->name;
        $data['assigned_by'] = $slipAssignment->assigned_by;
        return $this->createSubmission(
            $request,
            $slipAssignment->slip,
            $data,
            $definition,
            $pdfGenerator,
            $docxGenerator,
            $slipAssignment,
            $slipAssignment->slipSubmission
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, array $definition): array
    {
        $rules = [];

        foreach ($definition['groups'] as $group) {
            foreach ($group['fields'] as $field) {
                $key = 'details.'.$field['key'];
                $presence = $field['required'] ? 'required' : 'nullable';

                if ($field['type'] === 'checkboxes') {
                    $arrayRules = $field['required'] ? ['min:1'] : [];
                    if ($field['key'] === 'referral_reasons') {
                        $arrayRules[] = 'max:1';
                    }
                    $rules[$key] = [$presence, 'array', ...$arrayRules];
                    $rules[$key.'.*'] = [
                        'string',
                        Rule::in(array_keys($field['options'])),
                    ];

                    continue;
                }

                $rules[$key] = match ($field['type']) {
                    'date' => [$presence, 'date'],
                    'number' => [$presence, 'integer', 'min:1', 'max:365'],
                    'select' => [$presence, 'string', Rule::in(array_keys($field['options']))],
                    default => [$presence, 'string', 'max:'.($field['type'] === 'textarea' ? 4000 : 255)],
                };
            }
        }

        $validated = $request->validate($rules);
        $input = $validated['details'] ?? [];
        $isOtherReferralReason = ($input['referral_reasons'] ?? []) === ['other'];

        if ($isOtherReferralReason && ! filled($input['other_reason'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'details.other_reason' => 'Please specify the other reason for referral.',
            ]);
        }

        if (! $isOtherReferralReason) {
            $input['other_reason'] = null;
        }

        $data = [];

        foreach ($definition['groups'] as $group) {
            foreach ($group['fields'] as $field) {
                $data[$field['key']] = $input[$field['key']] ?? null;

                if ($field['key'] === 'request_date' && $data[$field['key']] === null) {
                    $data[$field['key']] = now()->toDateString();
                }
            }
        }

        return $data;
    }

    /**
     * Store optional evidence uploaded with a Class Admission Slip.
     *
     * @return array<int, array{original_name: string, path: string, mime_type: string|null, size: int}>
     */
    private function validatedAttachments(Request $request): array
    {
        $request->validate([
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip',
            ],
        ]);

        $attachments = [];
        foreach ($request->file('attachments', []) as $file) {
            $storedName = Str::uuid().'.'.($file->extension() ?: 'bin');
            $path = $file->storeAs('slip-submissions/attachments', $storedName, 'local');

            if (! $path) {
                throw new \RuntimeException('An attachment could not be stored.');
            }

            $attachments[] = [
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        return $attachments;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function validatedGuidanceData(Request $request, array $definition): array
    {
        $rules = [];

        foreach ($definition['guidance_fields'] as $field) {
            $key = 'details.'.$field['key'];
            $presence = $field['required'] ? 'required' : 'nullable';

            if ($field['type'] === 'checkboxes') {
                $rules[$key] = [$presence, 'array', ...($field['required'] ? ['min:1'] : [])];
                $rules[$key.'.*'] = ['string', Rule::in(array_keys($field['options']))];

                continue;
            }

            $rules[$key] = match ($field['type']) {
                'date' => [$presence, 'date'],
                'select' => [$presence, 'string', Rule::in(array_keys($field['options']))],
                default => [$presence, 'string', 'max:255'],
            };
        }

        $validated = $request->validate($rules);
        $input = $validated['details'] ?? [];
        $data = [];

        foreach ($definition['guidance_fields'] as $field) {
            $data[$field['key']] = $input[$field['key']] ?? null;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function classAdmissionReferralData(SlipAssignment $assignment): array
    {
        $referral = $assignment->relatedAssignment;
        abort_unless(
            $referral !== null
                && $referral->slip === 'student-referral-slip'
                && $referral->student_id === $assignment->student_id
                && in_array($referral->status, ['submitted', 'approved'], true)
                && $referral->slipSubmission !== null,
            409,
            'The Class Admission Slip needs a completed Teacher Referral Slip.'
        );

        $referralData = $referral->slipSubmission->data->getArrayCopy();
        $student = User::query()->find($assignment->student_id);
        $reference = StudentReference::query()
            ->whereIn('id_number', StudentReference::matchingIdNumbers($student?->id_number))
            ->first();
        $absenceDates = $referralData['absence_dates'] ?? [];
        $dateAbsent = $referralData['date_absent'] ?? (is_array($absenceDates) ? implode(', ', $absenceDates) : $absenceDates);
        $daysAbsent = $referralData['days_absent'] ?? (is_array($absenceDates) ? count($absenceDates) : null);

        return [
            'student_name' => $referralData['student_name'] ?? $student?->name,
            'sex' => $reference?->gender
                ? ucfirst($reference->gender)
                : ($referralData['sex'] ?? ($student?->gender ? ucfirst($student->gender) : null)),
            'program_year' => trim(implode(' ', array_filter([$reference?->course, $reference?->year])))
                ?: ($referralData['program_year'] ?? $referralData['course_year'] ?? null),
            'date_absent' => $dateAbsent,
            'days_absent' => $daysAbsent,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $definition
     */
    private function createSubmission(
        Request $request,
        string $slip,
        array $data,
        array $definition,
        SlipPdfGenerator $pdfGenerator,
        CifDocxSampleGenerator $docxGenerator,
        ?SlipAssignment $assignment = null,
        ?SlipSubmission $existingSubmission = null,
        ?int $submissionUserId = null
    ): RedirectResponse {
        $pdfPath = $slip === 'qf-bo-oss-04-client-information-form'
            ? 'slip-submissions/'.Str::uuid().'.docx'
            : ($existingSubmission?->pdf_path ?? 'slip-submissions/'.Str::uuid().'.pdf');

        try {
            $submission = DB::transaction(function () use ($request, $slip, $data, $definition, $pdfGenerator, $docxGenerator, $pdfPath, $assignment, $existingSubmission, $submissionUserId) {
                $lockedAssignment = null;

                if ($assignment) {
                    $lockedAssignment = SlipAssignment::query()
                        ->whereKey($assignment->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    abort_unless(in_array($lockedAssignment->status, ['pending', 'submitted'], true), 409);
                }

                if ($existingSubmission) {
                    $submission = SlipSubmission::query()
                        ->whereKey($existingSubmission->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    abort_unless($submission->review_status !== 'approved', 409);
                    $submission->update([
                        'data' => $data,
                        'pdf_path' => $pdfPath,
                        'review_status' => 'submitted',
                        'approved_by' => null,
                        'approved_at' => null,
                    ]);
                } else {
                    $submission = SlipSubmission::create([
                        'user_id' => $submissionUserId ?? $request->user()->id,
                        'slip' => $slip,
                        'data' => $data,
                        'pdf_path' => $pdfPath,
                        'review_status' => 'submitted',
                    ]);
                }

                $document = $slip === 'qf-bo-oss-04-client-information-form'
                    ? $docxGenerator->generate($data, [], $request->user()->name)
                    : $pdfGenerator->generate($submission->fresh(), $definition);

                if (! Storage::disk('local')->put($pdfPath, $document)) {
                    throw new \RuntimeException('The completed slip document could not be stored.');
                }

                if ($lockedAssignment) {
                    $lockedAssignment->update([
                        'status' => 'submitted',
                        'slip_submission_id' => $submission->id,
                        'completed_at' => now(),
                        'approved_by' => null,
                        'approved_at' => null,
                    ]);
                }

                return $submission;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($pdfPath);
            throw $exception;
        }

        return redirect()->route('slips.submissions.show', $submission);
    }

    private function isTeacherSubmittedReferral(SlipAssignment $assignment): bool
    {
        return $assignment->slip === 'student-referral-slip'
            && (bool) ($assignment->slipSubmission?->data['submitted_by_teacher'] ?? false);
    }

    public function show(Request $request, SlipSubmission $slipSubmission): View
    {
        $this->authorizeSubmission($request, $slipSubmission);
        $slips = SlipCatalog::all();
        abort_unless(isset($slips[$slipSubmission->slip]), 404);

        return view('slips.submitted', [
            'submission' => $slipSubmission,
            'slip' => $slips[$slipSubmission->slip],
            'guidanceFields' => $request->user()->role === 'guidance_counselor'
                ? ($slips[$slipSubmission->slip]['guidance_fields'] ?? [])
                : [],
            'assignment' => SlipAssignment::query()
                ->where('slip_submission_id', $slipSubmission->id)
                ->first(),
            'referralPdfReady' => $slipSubmission->slip !== 'student-referral-slip'
                || $this->hasReferralDocumentControlData($slipSubmission->data?->getArrayCopy() ?? []),
        ]);
    }

    public function attachment(Request $request, SlipSubmission $slipSubmission, int $attachment): StreamedResponse
    {
        $this->authorizeSubmission($request, $slipSubmission);

        $submissionData = $slipSubmission->data?->getArrayCopy() ?? [];
        $attachments = array_values($submissionData['_attachments'] ?? []);
        abort_unless(isset($attachments[$attachment]), 404);

        $file = $attachments[$attachment];
        abort_unless(is_array($file) && Storage::disk('local')->exists($file['path'] ?? ''), 404);

        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename((string) ($file['original_name'] ?? 'attachment'))) ?: 'attachment';

        return Storage::disk('local')->response(
            $file['path'],
            $filename,
            [
                'Content-Type' => $file['mime_type'] ?? 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]
        );
    }

    public function pdf(Request $request, SlipSubmission $slipSubmission, SlipPdfGenerator $pdfGenerator): Response|StreamedResponse
    {
        $this->authorizeSubmission($request, $slipSubmission);
        abort_unless(
            $slipSubmission->slip !== 'student-referral-slip'
                || $this->hasReferralDocumentControlData($slipSubmission->data?->getArrayCopy() ?? []),
            409,
            'The referral PDF is unavailable until Guidance enters all document-control details.'
        );
        if ($slipSubmission->slip === 'qf-bo-oss-04-client-information-form') {
            return response($pdfGenerator->generate(
                $slipSubmission,
                SlipCatalog::all()['qf-bo-oss-04-client-information-form']
            ))
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        abort_unless(Storage::disk('local')->exists($slipSubmission->pdf_path), 404);

        return Storage::disk('local')->response(
            $slipSubmission->pdf_path,
            "{$slipSubmission->slip}-{$slipSubmission->id}.pdf",
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]
        );
    }

    public function docx(Request $request, SlipSubmission $slipSubmission, CifDocxSampleGenerator $docxGenerator): Response
    {
        $this->authorizeSubmission($request, $slipSubmission);
        abort_unless($slipSubmission->slip === 'qf-bo-oss-04-client-information-form', 404);

        $appointments = Appointment::query()
            ->with('reviewer')
            ->where(function ($query) use ($slipSubmission) {
                $query->where('user_id', $slipSubmission->user_id)
                    ->orWhereHas('referral', fn ($referralQuery) => $referralQuery->where('student_id', $slipSubmission->user_id));
            })
            ->where('status', 'done')
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Appointment $appointment): array => [
                'date' => ($appointment->scheduled_at ?? $appointment->completed_at)?->format('m/d/Y'),
                'concern' => $appointment->concerns ?? $appointment->purpose,
                'action' => $appointment->action_taken,
                'counselor' => $appointment->reviewer?->name ?? $request->user()->name,
            ])
            ->all();

        return response($docxGenerator->generate(
            $slipSubmission->data?->getArrayCopy() ?? [],
            $appointments,
            $request->user()->name,
        ))
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->header('Content-Disposition', 'attachment; filename="cif-'.$slipSubmission->id.'-filled.docx"');
    }

    public function previewPdf(Request $request, SlipSubmission $slipSubmission): StreamedResponse
    {
        $this->authorizeSubmission($request, $slipSubmission);
        abort_unless($slipSubmission->slip === 'student-referral-slip', 404);
        abort_unless(Storage::disk('local')->exists($slipSubmission->pdf_path), 404);

        return Storage::disk('local')->response(
            $slipSubmission->pdf_path,
            'referral-preview.pdf',
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]
        );
    }

    private function authorizeSubmission(Request $request, SlipSubmission $submission): void
    {
        $assignment = SlipAssignment::query()
            ->with('relatedAssignment')
            ->where('slip_submission_id', $submission->id)
            ->first();

        $teacherOwnsReferral = $request->user()->role === 'teacher'
            && (
                ($assignment?->slip === 'student-referral-slip'
                    && (int) $assignment->assigned_by === (int) $request->user()->id)
                || ($assignment?->slip === 'class-admission-slip'
                    && (int) $assignment->relatedAssignment?->assigned_by === (int) $request->user()->id)
            );

        abort_unless(
            $submission->user_id === $request->user()->id
                || $request->user()->role === 'guidance_counselor'
                || $teacherOwnsReferral,
            403
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function studentDefaults(User $student): array
    {
        $nameParts = preg_split('/\s+/', trim($student->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstName = array_shift($nameParts) ?? '';
        $lastName = count($nameParts) > 0 ? array_pop($nameParts) : '';
        $middleName = implode(' ', $nameParts);

        return [
            'student_id' => $student->id_number,
            'student_name' => $student->name,
            'email' => $student->email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'middle_name' => $middleName ?: null,
            'middle_initial' => $middleName !== '' ? mb_substr($middleName, 0, 1).'.' : null,
            'request_date' => now()->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function clientInformationDefaults(User $student): array
    {
        $reference = StudentReference::query()
            ->whereIn('id_number', StudentReference::matchingIdNumbers($student->id_number))
            ->first();
        if (! $reference) {
            return [];
        }
        $source = collect($reference?->source_data ?? [])->mapWithKeys(function ($value, $key) {
            $normalized = preg_replace('/[^a-z0-9]/', '', strtolower((string) $key)) ?? '';

            return [$normalized => $value];
        });
        $sourceValue = static function (array $keys) use ($source): ?string {
            foreach ($keys as $key) {
                $value = $source->get($key);

                if (filled($value)) {
                    return trim((string) $value);
                }
            }

            return null;
        };
        $nameParts = preg_split('/\s+/', trim($reference?->name ?? $student->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstName = $sourceValue(['firstname', 'givenname']) ?? (array_shift($nameParts) ?? '');
        $lastName = $sourceValue(['lastname', 'surname']) ?? (count($nameParts) ? array_pop($nameParts) : '');
        $middleName = $sourceValue(['middlename', 'middleinitial']) ?? implode(' ', $nameParts);
        $contactNumber = $sourceValue([
            'contactno',
            'contactnumber',
            'phonenumber',
            'mobilenumber',
            'mobilephonenumber',
            'mobilephone',
            'cellphonenumber',
            'cellphone',
            'cellnumber',
            'telephonenumber',
            'telephone',
            'phone',
            'mobile',
        ]);

        return array_filter([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'middle_name' => $middleName ?: null,
            'student_id' => $reference?->id_number ?? $student->id_number,
            'sex' => $reference?->gender ?? $student->gender,
            'course_year' => trim(implode(' ', array_filter([$reference?->course, $reference?->year]))),
            'contact_number' => $contactNumber,
            'email' => $student->email,
        ], fn ($value) => filled($value));
    }
}
