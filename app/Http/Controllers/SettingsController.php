<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Template;
use App\Services\IntegrationService;

class SettingsController extends Controller
{
    protected $integrationService;

    public function __construct(IntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $templates = Template::where('user_id', $user->id)
            ->whereNull('source')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // call service
        $data = $this->integrationService->getApps($user->name);

        return view('settings', compact('templates', 'data'));
    }
}
