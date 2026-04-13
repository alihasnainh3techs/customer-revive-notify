<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'min:3',
                'max:255',
                Rule::unique('templates')->where(fn($query) => $query->where('user_id', Auth::id())),
            ],
            'type'    => 'required|in:email,message',
            'subject' => 'nullable|string|max:255|required_if:type,email',
            'body'    => 'required|string|min:1',
            'status'  => 'sometimes|boolean',
        ], [
            'name.required' => 'Template name is required.',
            'name.min'      => 'Template name must be at least 3 characters.',
            'name.max'      => 'Template name cannot exceed 255 characters.',
            'name.unique'   => 'A template with this name already exists.',

            'type.required' => 'Template type is required.',
            'type.in'       => 'Template type must be either email or message.',

            'subject.required_if' => 'Subject is required.',
            'subject.max'         => 'Subject cannot exceed 255 characters.',

            'body.required' => 'Template body is required.',
            'body.min'      => 'Template body cannot be empty.',

            'status.boolean' => 'Status must be true or false.',
        ]);

        $template = Auth::user()->templates()->create([
            'name'    => $request->name,
            'type'    => $request->type,
            'subject' => $request->type === 'email' ? $request->subject : null,
            'body'    => $request->body,
            'status'  => $request->input('status', $request->status),
        ]);

        return response()->json([
            'message'  => 'Template created successfully.',
            'template' => $template,
        ], 201);
    }

    public function destroy(Template $template)
    {
        $template->delete();

        return response()->json([
            'message' => 'Template deleted successfully.'
        ]);
    }

    public function update(Request $request, Template $template)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'min:3',
                'max:255',
                Rule::unique('templates')
                    ->where(fn($query) => $query->where('user_id', Auth::id()))
                    ->ignore($template->id)
            ],
            'type'    => 'required|in:email,message',
            'subject' => 'nullable|string|max:255|required_if:type,email',
            'body'    => 'required|string|min:1',
            'status'  => 'sometimes|boolean',
        ], [
            'name.required' => 'Template name is required.',
            'name.min'      => 'Template name must be at least 3 characters.',
            'name.max'      => 'Template name cannot exceed 255 characters.',
            'name.unique'   => 'A template with this name already exists.',

            'type.required' => 'Template type is required.',
            'type.in'       => 'Template type must be either email or message.',

            'subject.required_if' => 'Subject is required.',
            'subject.max'         => 'Subject cannot exceed 255 characters.',

            'body.required' => 'Template body is required.',
            'body.min'      => 'Template body cannot be empty.',

            'status.boolean' => 'Status must be true or false.',
        ]);

        $template->update([
            'name'    => $request->name,
            'type'    => $request->type,
            'subject' => $request->type === 'email' ? $request->subject : null,
            'body'    => $request->body,
            'status'  => $request->input('status', $template->status),
        ]);

        return response()->json([
            'message'  => 'Template updated successfully.',
            'template' => $template->fresh(),
        ]);
    }
}
