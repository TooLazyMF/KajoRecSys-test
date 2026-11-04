<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Services\AccountSessionLogger;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function __construct(private readonly AccountSessionLogger $sessionLogger) {}

    public function toResponse($request)
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->sessionLogger->start($request, $user);
        }

        return match ($user->role) {
            'guidance_counselor' => redirect()->route('guidance.dashboard'),
            'student' => redirect()->route('student.dashboard'),
            'teacher' => redirect()->route('teacher.dashboard'),
            default => redirect()->route('home'),
        };
    }
}
