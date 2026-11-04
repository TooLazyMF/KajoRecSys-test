<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountSessionLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class AccountActivityController extends Controller
{
    public function touch(): Response
    {
        return response()->noContent();
    }

    public function logout(Request $request, AccountSessionLogger $sessionLogger): RedirectResponse
    {
        $user = $request->user();
        $sessionLogger->close($request, $user instanceof User ? $user : null);

        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();

        return redirect('/');
    }
}
