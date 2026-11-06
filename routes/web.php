<?php

use App\Http\Controllers\AccountActivityController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CnsController;
use App\Http\Controllers\EmailVerificationOtpController;
use App\Http\Controllers\LoginTwoFactorOtpController;
use App\Http\Controllers\PasswordResetOtpController;
use App\Http\Controllers\SlipSubmissionController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StudentRecordController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::view('forgot-password', 'pages::auth.forgot-password')->name('password.request');
Route::post('forgot-password', [PasswordResetOtpController::class, 'send'])
    ->middleware('throttle:5,1')
    ->name('password.email');
Route::post('reset-password', [PasswordResetOtpController::class, 'reset'])
    ->middleware('throttle:5,1')
    ->name('password.update');

Route::get('registration/verify-email', [EmailVerificationOtpController::class, 'registrationNotice'])
    ->middleware('guest')
    ->name('registration.verification.notice');
Route::post('email/verification-otp', [EmailVerificationOtpController::class, 'send'])
    ->middleware('throttle:5,1')
    ->name('verification.otp.send');
Route::post('email/verification-otp/verify', [EmailVerificationOtpController::class, 'verify'])
    ->middleware('throttle:5,1')
    ->name('verification.otp.verify');

Route::middleware(['guest', 'throttle:two-factor'])->group(function () {
    Route::post('two-factor-challenge/email', [LoginTwoFactorOtpController::class, 'send'])
        ->name('two-factor.email.send');
    Route::post('two-factor-challenge/email/verify', [LoginTwoFactorOtpController::class, 'verify'])
        ->name('two-factor.email.verify');
});

Route::post('logout', [AccountActivityController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('account-activity', [AccountActivityController::class, 'touch'])
        ->name('account-activity.touch');

    Route::get('dashboard', function () {
        if (auth()->user()->role === 'student') {
            return redirect()->route('student.dashboard');
        }

        return view('dashboard');
    })->name('dashboard');

    Route::get('student-dashboard', StudentDashboardController::class)->name('student.dashboard');
    Route::get('teacher-dashboard', [SlipSubmissionController::class, 'teacherDashboard'])->name('teacher.dashboard');
    Route::get('teacher/referral-history', [SlipSubmissionController::class, 'teacherReferralHistory'])
        ->name('teacher.referral-history');
    Route::get('teacher/referrals-sent', [SlipSubmissionController::class, 'teacherReferralsSent'])
        ->name('teacher.referrals-sent');
    Route::get('teacher/students', [SlipSubmissionController::class, 'teacherStudents'])
        ->name('teacher.students');
    Route::post('teacher/referrals/{slipAssignment}/acknowledge', [SlipSubmissionController::class, 'acknowledgeTeacherReferral'])
        ->name('teacher.referrals.acknowledge');
    Route::get('guidance-dashboard', [SlipSubmissionController::class, 'guidanceDashboard'])->name('guidance.dashboard');
    Route::get('guidance/referrals', [SlipSubmissionController::class, 'guidanceReferrals'])->name('guidance.referrals');
    Route::get('guidance/cif', [SlipSubmissionController::class, 'guidanceCifIndex'])
        ->middleware('role:guidance_counselor')
        ->name('guidance.cif.index');
    Route::get('guidance/cif/{slipSubmission}/docx', [SlipSubmissionController::class, 'guidanceCifDocx'])
        ->middleware('role:guidance_counselor')
        ->name('guidance.cif.docx');
    Route::get('guidance/cif/{slipSubmission}', [SlipSubmissionController::class, 'guidanceCifShow'])
        ->middleware('role:guidance_counselor')
        ->name('guidance.cif.show');
    Route::get('guidance/referrals/sample-cif.docx', [SlipSubmissionController::class, 'sampleClientInformationDocx'])
        ->middleware('role:guidance_counselor')
        ->name('guidance.referrals.sample-cif-docx');
    Route::get('guidance/referrals/sample-cif.pdf', [SlipSubmissionController::class, 'sampleClientInformationPdf'])
        ->middleware('role:guidance_counselor')
        ->name('guidance.referrals.sample-cif-pdf');
    Route::get('guidance/referrals/sample-cif.docx/preview', [SlipSubmissionController::class, 'sampleClientInformationDocxPreview'])
        ->middleware('role:guidance_counselor')
        ->name('guidance.referrals.sample-cif-docx-preview');
    Route::get('guidance/referrals/{referral}/cif.docx', [SlipSubmissionController::class, 'clientInformationDocx'])
        ->middleware('role:guidance_counselor')
        ->name('guidance.referrals.cif-docx');
    Route::get('guidance/referrals/{referral}/cif.docx/preview', [SlipSubmissionController::class, 'clientInformationDocxPreview'])
        ->middleware('role:guidance_counselor')
        ->name('guidance.referrals.cif-docx-preview');
    Route::get('guidance/record-history/{type}', [StudentRecordController::class, 'index'])
        ->whereIn('type', ['all', 'slips', 'appointments'])
        ->name('guidance.records.index');
    Route::get('guidance/students-records', [StudentRecordController::class, 'dashboard'])
        ->name('guidance.students-records');
    Route::get('guidance/archived-files', [StudentRecordController::class, 'archived'])
        ->name('guidance.records.archived');
    Route::get('guidance/student-records/{student}', [StudentRecordController::class, 'show'])
        ->name('guidance.records.show');
    Route::get('guidance/monthly-report', [StudentRecordController::class, 'monthlyReport'])
        ->name('guidance.reports.monthly');

    Route::middleware('role:guidance_counselor')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('students/import', [UserManagementController::class, 'importStudents'])->name('students.import');
        Route::post('users/{user}/status', [UserManagementController::class, 'updateStatus'])
            ->name('users.status');
    });

    Route::middleware('role:student,guidance_counselor')->group(function () {
        Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::post('appointments/{appointment}/accept', [AppointmentController::class, 'accept'])->name('appointments.accept');
        Route::post('guidance/referrals/{referral}/appoint', [AppointmentController::class, 'appointFromReferral'])
            ->middleware('role:guidance_counselor')
            ->name('guidance.referrals.appoint');
        Route::patch('guidance/referrals/{referral}/appointments/{appointment}', [AppointmentController::class, 'updateFromReferral'])
            ->middleware('role:guidance_counselor')
            ->name('guidance.referrals.appointments.update');
        Route::post('appointments/{appointment}/complete', [AppointmentController::class, 'complete'])
            ->middleware('role:guidance_counselor')
            ->name('appointments.complete');
        Route::post('appointments/{appointment}/missed', [AppointmentController::class, 'markMissed'])
            ->middleware('role:guidance_counselor')
            ->name('appointments.missed');
        Route::patch('appointments/{appointment}/action-taken', [AppointmentController::class, 'updateActionTaken'])
            ->middleware('role:guidance_counselor')
            ->name('appointments.action-taken.update');
        Route::get('appointments/{appointment}/attachments/{attachment}', [AppointmentController::class, 'downloadAttachment'])
            ->whereNumber('attachment')
            ->name('appointments.attachments.download');
        Route::post('appointments/{appointment}/decline', [AppointmentController::class, 'decline'])->name('appointments.decline');
        Route::post('appointments/{appointment}/reappoint', [AppointmentController::class, 'reappoint'])->name('appointments.reappoint');
    });

    Route::get('guidance-slips', [SlipSubmissionController::class, 'index'])->name('slips.index');
    Route::get('guidance-slips/{slip}/fill-up', [SlipSubmissionController::class, 'create'])->name('slips.fillup');
    Route::post('guidance-slips/{slip}/assign', [SlipSubmissionController::class, 'assign'])->name('slips.assign');
    Route::post('teacher/referrals/{slipAssignment}/cancel', [SlipSubmissionController::class, 'cancelTeacherReferral'])
        ->name('teacher.referrals.cancel');
    Route::patch('teacher/referrals/{slipAssignment}/notes', [SlipSubmissionController::class, 'updateTeacherReferralNotes'])
        ->name('teacher.referrals.notes');
    Route::post('student/slip-requests/{slip}', [SlipSubmissionController::class, 'requestForm'])
        ->name('student.slip-requests.store');
    Route::post('guidance/referrals/{referral}/issue/{slip}', [SlipSubmissionController::class, 'issueFollowup'])
        ->name('guidance.slip-review.issue');
    Route::post('guidance/referrals/{referral}/document-control', [SlipSubmissionController::class, 'updateReferralDocumentControl'])
        ->middleware('role:guidance_counselor')
        ->name('guidance.referrals.document-control');

    Route::get('assigned-slips', [SlipSubmissionController::class, 'assignedIndex'])->name('assigned-slips.index');
    Route::get('assigned-slips/{slipAssignment}/fill-up', [SlipSubmissionController::class, 'assignedCreate'])
        ->name('assigned-slips.fillup');
    Route::post('assigned-slips/{slipAssignment}/cancel-request', [SlipSubmissionController::class, 'cancelStudentSlipRequest'])
        ->name('assigned-slips.cancel-request');
    Route::post('assigned-slips/{slipAssignment}/submit', [SlipSubmissionController::class, 'assignedStore'])
        ->name('assigned-slips.submit');
    Route::post('assigned-slips/{slipAssignment}/send-result-to-teacher', [SlipSubmissionController::class, 'sendClassAdmissionResultToTeacher'])
        ->name('assigned-slips.send-result-to-teacher');
    Route::get('slip-history', [SlipSubmissionController::class, 'history'])->name('slip-history.index');
    Route::get('guidance/slip-review', [SlipSubmissionController::class, 'reviewIndex'])
        ->name('guidance.slip-review');
    Route::post('guidance/slip-review/{slipAssignment}/approve', [SlipSubmissionController::class, 'approve'])
        ->middleware('role:guidance_counselor')
        ->name('guidance.slip-review.approve');

    Route::get('slip-submissions/{slipSubmission}', [SlipSubmissionController::class, 'show'])
        ->name('slips.submissions.show');
    Route::get('slip-submissions/{slipSubmission}/pdf', [SlipSubmissionController::class, 'pdf'])
        ->name('slips.submissions.pdf');
    Route::get('slip-submissions/{slipSubmission}/docx', [SlipSubmissionController::class, 'docx'])
        ->name('slips.submissions.docx');
    Route::get('slip-submissions/{slipSubmission}/preview', [SlipSubmissionController::class, 'previewPdf'])
        ->name('slips.submissions.preview-pdf');
    Route::get('slip-submissions/{slipSubmission}/attachments/{attachment}', [SlipSubmissionController::class, 'attachment'])
        ->whereNumber('attachment')
        ->name('slips.submissions.attachment');
    Route::get('cns-requests/{cnsRequest}', [CnsController::class, 'show'])->name('cns.requests.show');
    Route::get('cns-requests/{cnsRequest}/pdf', [CnsController::class, 'pdf'])->name('cns.requests.pdf');
});

require __DIR__.'/settings.php';
