<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Integration;
use Illuminate\Support\Facades\Auth;
use App\Services\IntegrationService;

class WhatomationController extends Controller
{
    protected $integrationService;

    public function __construct(IntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

        // call service
        $data = $this->integrationService->fetchIntegrationStatus($user->name, 'whatomation');

        $integration = Integration::where('user_id', $user->id)
            ->where('provider', 'whatomation')
            ->first();

        $deviceName = null;
        if (!empty($data['data'])) {
            $decoded = json_decode($data['data'], true);
            $deviceName = $decoded['device_name'] ?? null;
        }

        return view('settings.whatomation', compact('integration', 'deviceName'));
    }

    public function store(Request $request)
    {
        return $this->handleSave($request);
    }

    public function update(Request $request, $id)
    {
        return $this->handleSave($request);
    }

    /**
     * Shared logic for creating or updating the BSP configuration
     */
    private function handleSave(Request $request)
    {
        $validated = $request->validate([
            'status'    => 'required|boolean',
        ]);

        try {
            Integration::updateOrCreate(
                [
                    'user_id'  => Auth::id(),
                    'provider' => 'whatomation',
                ],
                [
                    'status' => $validated['status'],
                    'configurations' => null,
                ]
            );

            return response()->json(['message' => 'Whatomation configuration saved!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error saving configuration.'], 500);
        }
    }
}
