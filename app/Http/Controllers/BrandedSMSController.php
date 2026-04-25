<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BrandedSMSController extends Controller
{
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

        $integration = Integration::where('user_id', $user->id)
            ->where('provider', 'bsp')
            ->first();

        return view('settings.bsp', compact('integration'));
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
            'email'     => 'required|email',
            'password'  => 'required|string',
            'mask_id'   => 'required|string',
            'device_id' => 'required|string',
        ]);

        try {
            Integration::updateOrCreate(
                [
                    'user_id'  => Auth::id(),
                    'provider' => 'bsp',
                ],
                [
                    'status' => $validated['status'],
                    'configurations' => [
                        'email'     => $validated['email'],
                        'password'  => $validated['password'],
                        'mask_id'   => $validated['mask_id'],
                        'device_id' => $validated['device_id'],
                    ],
                ]
            );

            return response()->json(['message' => 'Branded SMS configuration saved!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error saving configuration.'], 500);
        }
    }
}
