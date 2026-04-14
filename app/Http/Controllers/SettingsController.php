<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $templates = $user->templates()
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('settings', compact('templates'));
    }
}
