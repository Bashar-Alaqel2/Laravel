<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DefaultContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DefaultContentController extends Controller
{
    public function index(Request $request)
    {
        $contents = DefaultContent::with('screen')->orderBy('created_at', 'desc')->get();
        return response()->json([
            'success' => true,
            'data' => $contents
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:mp4,mov,avi,jpeg,png,jpg|max:51200',
            'duration' => 'nullable|integer|min:1',
            'screen_id' => 'nullable|exists:screens,screen_id'
        ]);

        $disk = env('FILESYSTEM_DISK', 'public');
        try {
            $path = $request->file('file')->store('default_contents', $disk);
            $fileUrl = Storage::disk($disk)->url($path);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to upload file: ' . $e->getMessage()], 500);
        }

        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        $type = in_array($extension, ['mp4', 'avi', 'mov']) ? 'video' : 'image';

        // Optional logic: if this is going to be active, deactivate others for the same screen target
        $isActive = $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : false;

        if ($isActive) {
            DefaultContent::where('screen_id', $request->screen_id)->update(['is_active' => false]);
        }

        $content = DefaultContent::create([
            'title' => $request->title,
            'file_path' => $fileUrl,
            'file_type' => $type,
            'duration' => $request->duration ?? 15,
            'is_active' => $isActive,
            'screen_id' => $request->screen_id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة المحتوى الافتراضي بنجاح',
            'data' => $content
        ]);
    }

    public function update(Request $request, $id)
    {
        $content = DefaultContent::find($id);
        if (!$content) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'duration' => 'sometimes|integer|min:1',
            'screen_id' => 'nullable|exists:screens,screen_id'
        ]);

        $content->update($request->only(['title', 'duration', 'screen_id']));

        return response()->json([
            'success' => true,
            'message' => 'تم التحديث بنجاح',
            'data' => $content
        ]);
    }

    public function activate($id)
    {
        $content = DefaultContent::find($id);
        if (!$content) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Deactivate all others for the same target (global or specific screen)
        DefaultContent::where('screen_id', $content->screen_id)->update(['is_active' => false]);

        $content->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'تم تفعيل المحتوى الافتراضي'
        ]);
    }

    public function destroy($id)
    {
        $content = DefaultContent::find($id);
        if (!$content) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // We could also delete the file from storage if we want
        $content->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم الحذف بنجاح'
        ]);
    }
}
