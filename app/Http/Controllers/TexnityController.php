<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Integration;
use Illuminate\Support\Facades\Auth;

class TexnityController extends Controller
{
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

        $integration = Integration::where('user_id', $user->id)
            ->where('provider', 'texnity')
            ->first();

        return view('settings.texnity', compact('integration'));
    }
}
