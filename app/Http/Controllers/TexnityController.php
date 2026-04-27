<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Integration;
use Illuminate\Support\Facades\Auth;
use App\Services\IntegrationService;

class TexnityController extends Controller
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

        $integration = Integration::where('user_id', $user->id)
            ->where('provider', 'texnity')
            ->first();

        return view('settings.texnity', compact('integration'));
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
            'password'  => 'required|string',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $APIKEY = $validated['password'];

        try {
            // call service
            $data = $this->integrationService->validateAndFetchTemplates($user->name, $APIKEY);

            if (!$data['success']) {
                return response()->json([
                    'message' => $data['message']
                ], 400);
            }

            Integration::updateOrCreate(
                [
                    'user_id'  => Auth::id(),
                    'provider' => 'texnity',
                ],
                [
                    'status' => $validated['status'],
                    'configurations' => [
                        'password'  => $validated['password'],
                    ],
                ]
            );

            return response()->json(['message' => 'Texnity configuration saved!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error saving configuration.'], 500);
        }
    }
}
