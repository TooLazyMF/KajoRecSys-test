# KajoRecSys System Flowchart

![KajoRecSys system flowchart](./system-flowchart.svg)

The SVG version above is suitable for viewing or presentation. The Mermaid source follows.

```mermaid
flowchart TD
    Start([Start]) --> SignIn[Register or sign in]
    SignIn --> Verify{Email verified and account active?}
    Verify -- No --> AccessHelp[Verify email or resolve account access]
    AccessHelp --> SignIn
    Verify -- Yes --> OTP[Complete email two-factor challenge if required]
    OTP --> Role{Account role}

    Role -- Student --> StudentDash[Student dashboard]
    Role -- Teacher --> TeacherDash[Teacher dashboard]
    Role -- Guidance counselor --> GuidanceDash[Guidance dashboard]

    subgraph Student["Student workflows"]
        StudentDash --> CIF{Client Information Form pending?}
        CIF -- Yes --> CIFForm[Complete Client Information Form]
        CIFForm --> StudentDash
        CIF -- No --> StudentChoice{Choose an activity}

        StudentChoice -- Request form --> RequestForm[Request an available form from Guidance]
        StudentChoice -- Complete assignment --> AssignmentType{Class Admission Slip?}
        StudentChoice -- Appointment --> AppointmentRequest[Submit counseling appointment request]
        RequestForm --> AssignedForm
        AssignedForm --> SubmitSlip[Complete or review and submit form]
        SubmitSlip --> GuidanceReview

        AssignmentType -- Yes --> ReceiveAdmission[Receive Class Admission Slip]
        AssignmentType -- No --> AssignedForm[Open another assigned form]
        IssueAdmission --> ReceiveAdmission
        ReceiveAdmission --> FillAdmission[Fill in remaining details and attach excuse documents or images]
        FillAdmission --> SubmitAdmission[Send completed Class Admission Slip to Guidance]
        ResultToStudent --> ForwardResult[Send Class Admission result to teacher]
    end

    subgraph Teacher["Teacher workflow"]
        TeacherDash --> SelectStudent[Select a student]
        SelectStudent --> AssignSlip[Assign a form or create a student referral]
        AssignSlip --> AssignedForm
        TeacherDash --> ReferralStatus[Track referrals and follow-ups]
        ForwardResult --> TeacherViewsResult[View Class Admission result]
    end

    subgraph Guidance["Guidance workflow"]
        GuidanceDash --> GuidanceChoice{Review submitted work or manage referrals?}
        GuidanceChoice -- Submitted forms --> GuidanceReview[Review submitted form]
        GuidanceReview --> Approved{Approve?}
        Approved -- No --> Corrections[Return for correction or follow-up]
        Corrections --> AssignedForm
        Approved -- Yes --> ApprovedSlip[Approve and save completed form]
        GuidanceChoice -- Referral --> ReferralReview[Review student referral]
        ReferralReview --> GuidanceReferralAction{Next action}
        GuidanceReferralAction -- Schedule counseling --> ReferralAppointment[Schedule referral appointment]
        GuidanceReferralAction -- Issue Class Admission --> IssueAdmission[Guidance counselor issues Class Admission Slip]
        SubmitAdmission --> AdmissionReview[Counselor reviews completed slip and excuse evidence]
        AdmissionReview --> Excused{Excuse accepted?}
        Excused -- Yes --> MarkExcused[Mark absence as excused]
        Excused -- No --> MarkUnexcused[Mark absence as unexcused]
        MarkExcused --> SendResult[Finalize Class Admission result]
        MarkUnexcused --> SendResult
        SendResult --> ResultToStudent[Send result to student first]
        ApprovedSlip --> StudentHistory[Student can view completed submission]
    end

    subgraph Appointments["Counseling appointments"]
        AppointmentRequest --> CounselorReview[Guidance reviews appointment request]
        CounselorReview --> AppointmentDecision{Accept or decline?}
        AppointmentDecision -- Accept --> Schedule[Set date and time]
        AppointmentDecision -- Decline --> Declined[Share decline reason]
        Schedule --> AppointmentStatus[Student views appointment status]
        Declined --> AppointmentStatus
        AppointmentStatus --> Reappoint{Request a new time?}
        Reappoint -- Yes --> AppointmentRequest
        Reappoint -- No --> AppointmentEnd([Appointment workflow ends])
        ReferralAppointment --> AppointmentStatus
    end

    ApprovedSlip --> StudentDash
    StudentHistory --> StudentDash
```

The flowchart focuses on the primary student, teacher, and Guidance Office workflows. Access to dashboards and actions is restricted by account role.
