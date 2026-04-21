<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Device;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class DeviceController extends Controller
{
    public function index()
    {
        $device = Device::where('user_id', Auth::id())->first();

        // Auto-update status if device exists
        if ($device) {
            try {
                $response = Http::timeout(5)->get('http://89.250.74.194:4000/whatsapp/api/get-device-status', [
                    'apikey' => $device->session_id
                ]);

                $responseData = $response->json();

                // Strict check: Must have success=true AND status=Connected
                $isConnected = isset($responseData['success']) &&
                    $responseData['success'] === true &&
                    isset($responseData['status']) &&
                    strtolower($responseData['status']) === 'connected';

                if ($isConnected) {
                    // Only update if it wasn't already marked connected
                    if ($device->status !== 'connected') {
                        $device->update([
                            'status' => 'connected',
                            'disconnected_at' => null
                        ]);
                        $device->refresh();
                    }
                } else {
                    // Any other response (success: false, status: disconnected, status: pairing, etc.)
                    if ($device->status !== 'disconnected') {
                        $device->update([
                            'status' => 'disconnected',
                            'disconnected_at' => now()
                        ]);
                        $device->refresh();
                    }
                }
            } catch (\Exception $e) {
                // If the API call fails entirely, we also treat it as disconnected
                if ($device->status !== 'disconnected') {
                    $device->update([
                        'status' => 'disconnected',
                        'disconnected_at' => now()
                    ]);
                    $device->refresh();
                }
                Log::error('Failed to fetch device status: ' . $e->getMessage());
            }
        }

        return view('settings.whatsapp', compact('device'));
    }

    public function store(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:devices,name,NULL,id,user_id,' . Auth::id(),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Generate random unique session_id
        $sessionId = Str::random(15);

        try {
            // Call the API to get QR code
            $response = Http::timeout(30)->get('http://89.250.74.194:4000/whatsapp/api/get-qr/' . $sessionId);
            $responseData = json_decode($response->body(), true);

            if (isset($responseData['qrCode'])) {
                // Create device entry in database
                $device = Device::create([
                    'name' => $request->name,
                    'user_id' =>  Auth::id(),
                    'session_id' => $sessionId,
                    'status' => 'disconnected',
                    'disconnected_at' => null,
                ]);

                // Return success response with device data and API response
                return response()->json([
                    'success' => true,
                    'device' => $device,
                    'qr' => $responseData['qrCode'],
                    'message' => 'Device created successfully'
                ], 201);
            }

            return response()->json([
                'success' => false,
                'message' => $responseData['message'] ?? 'Failed to generate QR code'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to connect to QR service: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Find the device
            $device = Device::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$device) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device not found'
                ], 404);
            }

            // Call the external API to delete device
            Http::timeout(20)->delete('http://89.250.74.194:4000/whatsapp/api/delete-device/' . $device->session_id);

            // Delete device from database
            $device->delete();

            return response()->json([
                'success' => true,
                'message' => 'Device deleted successfully',
                'deleted_device' => $device
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete device: ' . $e->getMessage()
            ], 500);
        }
    }

    public function toggleWhatsAppNotifications(Request $request, $id)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'enable_whatsapp' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Find the device belonging to the authenticated user
        $device = Device::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found'
            ], 404);
        }

        // Update the enable_whatsapp field
        $device->update([
            'enable_whatsapp' => $request->enable_whatsapp
        ]);

        return response()->json([
            'success' => true,
            'message' => $request->enable_whatsapp
                ? 'WhatsApp notifications enabled'
                : 'WhatsApp notifications disabled',
            'enable_whatsapp' => $device->enable_whatsapp
        ], 200);
    }
}
