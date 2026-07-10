<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemSetting;
use App\Events\SettingsUpdated;
use Illuminate\Support\Facades\Cache;

class SystemSettingController extends Controller
{
    /**
     * Get all settings as a key-value pair object
     */
    public function index()
    {
        $settings = Cache::rememberForever('system_settings_cache', function () {
            return SystemSetting::all()->pluck('setting_value', 'setting_key')->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Update multiple settings at once
     */
    public function update(Request $request)
    {
        // Only Admin or SuperAdmin can update settings
        if (!$request->user() || !$request->user()->can('manage_all')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access'], 403);
        }

        $settingsToUpdate = $request->except(['_token', '_method']);

        foreach ($settingsToUpdate as $key => $value) {
            // Treat boolean-like strings as booleans if necessary, or just store as is
            SystemSetting::updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => is_bool($value) ? ($value ? 'true' : 'false') : (string)$value]
            );
        }

        // Clear cache
        Cache::forget('system_settings_cache');

        // Fetch fresh settings
        $freshSettings = SystemSetting::all()->pluck('setting_value', 'setting_key')->toArray();

        // Broadcast event
        broadcast(new SettingsUpdated($freshSettings));

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $freshSettings
        ]);
    }
}

