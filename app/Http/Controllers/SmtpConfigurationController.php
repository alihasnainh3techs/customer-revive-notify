<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SmtpConfiguration;
use Illuminate\Support\Facades\Validator;

class SmtpConfigurationController extends Controller
{
    public function index()
    {
        $config = SmtpConfiguration::where('user_id', Auth::id())->first();
        return view('settings.smtp', compact('config'));
    }

    public function store(Request $request)
    {
        $isCustom = $request->input('service') === 'custom';

        $rules = [
            'service' => ['required', 'string', 'in:default,custom'],
            'custom_from_email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable'],
        ];

        // Required only when service = custom
        if ($isCustom) {
            $rules['smtp_host'] = ['required', 'string', 'max:255'];
            $rules['port'] = ['required', 'numeric', 'min:1', 'max:65535'];
            $rules['security_type'] = ['required', 'string', 'in:none,ssl,tls'];
            $rules['username'] = ['required', 'string', 'max:255'];
            $rules['password'] = ['required', 'string', 'max:255'];
        }

        $validator = Validator::make($request->all(), $rules, [
            'service.required' => 'Please select a service.',
            'service.in' => 'Invalid service selected.',

            'smtp_host.required' => 'SMTP host is required for custom SMTP.',
            'port.required' => 'Port is required for custom SMTP.',
            'port.numeric' => 'Port must be a valid number.',
            'port.min' => 'Port must be at least 1.',
            'port.max' => 'Port cannot exceed 65535.',

            'security_type.required' => 'Security type is required for custom SMTP.',
            'security_type.in' => 'Security type must be none, ssl, or tls.',

            'username.required' => 'Username is required for custom SMTP.',
            'password.required' => 'Password is required for custom SMTP.',

            'custom_from_email.email' => 'From email must be a valid email address.',
        ]);

        $validated = $validator->validate();

        $config = SmtpConfiguration::create([
            'user_id' => Auth::id(),
            'service' => $validated['service'],

            'smtp_host' => $isCustom ? $request->input('smtp_host') : null,
            'port' => $isCustom ? $request->input('port') : null,
            'security_type' => $isCustom ? $request->input('security_type') : null,
            'username' => $isCustom ? $request->input('username') : null,
            'password' => $isCustom ? $request->input('password') : null,

            'custom_from_email' => $request->input('custom_from_email'),
            'status' => $request->boolean('status'),
        ]);

        return response()->json([
            'message' => 'SMTP configuration saved successfully.',
            'config' => $config,
        ], 201);
    }

    public function update(Request $request, SmtpConfiguration $smtpConfiguration)
    {

        abort_if($smtpConfiguration->user_id !== Auth::id(), 403, 'Unauthorized action.');

        $isCustom = $request->input('service') === 'custom';

        $rules = [
            'service' => ['required', 'string', 'in:default,custom'],
            'custom_from_email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable'],
        ];

        if ($isCustom) {
            $rules['smtp_host'] = ['required', 'string', 'max:255'];
            $rules['port'] = ['required', 'numeric', 'min:1', 'max:65535'];
            $rules['security_type'] = ['required', 'string', 'in:none,ssl,tls'];
            $rules['username'] = ['required', 'string', 'max:255'];
            $rules['password'] = ['required', 'string', 'max:255'];
        }

        $validator = Validator::make($request->all(), $rules, [
            'service.required' => 'Please select a service.',
            'service.in' => 'Invalid service selected.',

            'smtp_host.required' => 'SMTP host is required for custom SMTP.',
            'port.required' => 'Port is required for custom SMTP.',
            'port.numeric' => 'Port must be a valid number.',
            'port.min' => 'Port must be at least 1.',
            'port.max' => 'Port cannot exceed 65535.',

            'security_type.required' => 'Security type is required for custom SMTP.',
            'security_type.in' => 'Security type must be none, ssl, or tls.',

            'username.required' => 'Username is required for custom SMTP.',
            'password.required' => 'Password is required for custom SMTP.',

            'custom_from_email.email' => 'From email must be a valid email address.',
        ]);

        $validated = $validator->validate();

        $smtpConfiguration->update([
            'service' => $validated['service'],

            'smtp_host' => $isCustom ? $request->input('smtp_host') : null,
            'port' => $isCustom ? $request->input('port') : null,
            'security_type' => $isCustom ? $request->input('security_type') : null,
            'username' => $isCustom ? $request->input('username') : null,
            'password' => $isCustom ? $request->input('password') : null,

            'custom_from_email' => $request->input('custom_from_email'),
            'status' => $request->input('status'),
        ]);

        return response()->json([
            'message' => 'SMTP configuration updated successfully.',
            'config' => $smtpConfiguration->fresh(),
        ], 200);
    }
}
