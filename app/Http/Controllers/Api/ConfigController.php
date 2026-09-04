<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Setting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    use ApiResponse;

    // ==================== CURRENCIES ====================
    public function getCurrencies(): JsonResponse
    {
        $currencies = Currency::orderBy('sort_order')->orderBy('id')->get();
        return $this->success($currencies);
    }

    public function getDefaultCurrency(): JsonResponse
    {
        $currency = Currency::where('is_default', true)->first();
        if (!$currency) {
            $currency = Currency::orderBy('sort_order')->orderBy('id')->first();
        }
        return $this->success($currency);
    }

    public function createCurrency(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'            => 'required|string|max:3|unique:currencies,code',
            'name'            => 'required|string|max:100',
            'symbol'          => 'required|string|max:10',
            'native_symbol'   => 'nullable|string|max:10',
            'decimal_places'  => 'required|integer|min:0|max:4',
            'exchange_rate'   => 'required|numeric|min:0',
            'is_default'      => 'sometimes|boolean',
            'is_active'       => 'sometimes|boolean',
            'sort_order'      => 'sometimes|integer|min:0',
        ]);

        // If this currency is default, remove default from others
        if (isset($validated['is_default']) && $validated['is_default']) {
            Currency::where('is_default', true)->update(['is_default' => false]);
        }

        $currency = Currency::create($validated);
        return $this->success($currency, 'Currency created', 201);
    }

    public function updateCurrency(Request $request, int $id): JsonResponse
    {
        $currency = Currency::findOrFail($id);
        $validated = $request->validate([
            'code'            => 'sometimes|string|max:3|unique:currencies,code,' . $id,
            'name'            => 'sometimes|string|max:100',
            'symbol'          => 'sometimes|string|max:10',
            'native_symbol'   => 'nullable|string|max:10',
            'decimal_places'  => 'sometimes|integer|min:0|max:4',
            'exchange_rate'   => 'sometimes|numeric|min:0',
            'is_default'      => 'sometimes|boolean',
            'is_active'       => 'sometimes|boolean',
            'sort_order'      => 'sometimes|integer|min:0',
        ]);

        if (isset($validated['is_default']) && $validated['is_default']) {
            Currency::where('id', '!=', $id)->update(['is_default' => false]);
        }

        $currency->update($validated);
        return $this->success($currency, 'Currency updated');
    }

    public function deleteCurrency(int $id): JsonResponse
    {
        $currency = Currency::findOrFail($id);
        if ($currency->is_default) {
            return $this->error('Cannot delete the default currency', 422);
        }
        $currency->delete();
        return $this->success(null, 'Currency deleted');
    }

    // ==================== LANGUAGES ====================
    public function getLanguages(): JsonResponse
    {
        $languages = Language::orderBy('sort_order')->orderBy('id')->get();
        return $this->success($languages);
    }

    public function createLanguage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:5|unique:languages,code',
            'name'        => 'required|string|max:100',
            'native_name' => 'nullable|string|max:100',
            'is_default'  => 'sometimes|boolean',
            'is_active'   => 'sometimes|boolean',
            'sort_order'  => 'sometimes|integer|min:0',
        ]);

        if (isset($validated['is_default']) && $validated['is_default']) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }

        $language = Language::create($validated);
        return $this->success($language, 'Language created', 201);
    }

    public function updateLanguage(Request $request, int $id): JsonResponse
    {
        $language = Language::findOrFail($id);
        $validated = $request->validate([
            'code'        => 'sometimes|string|max:5|unique:languages,code,' . $id,
            'name'        => 'sometimes|string|max:100',
            'native_name' => 'nullable|string|max:100',
            'is_default'  => 'sometimes|boolean',
            'is_active'   => 'sometimes|boolean',
            'sort_order'  => 'sometimes|integer|min:0',
        ]);

        if (isset($validated['is_default']) && $validated['is_default']) {
            Language::where('id', '!=', $id)->update(['is_default' => false]);
        }

        $language->update($validated);
        return $this->success($language, 'Language updated');
    }

    public function deleteLanguage(int $id): JsonResponse
    {
        $language = Language::findOrFail($id);
        if ($language->is_default) {
            return $this->error('Cannot delete the default language', 422);
        }
        $language->delete();
        return $this->success(null, 'Language deleted');
    }

    // ==================== SETTINGS ====================
    /**
     * Get settings. Optional ?group=email to get only that group.
     */
    public function getSettings(Request $request): JsonResponse
    {
        $group = $request->query('group');

        if ($group) {
            $settings = Setting::getGroup($group);
        } else {
            // Return all settings grouped
            $all = Setting::all()->groupBy('group');
            $settings = [];
            foreach ($all as $groupName => $items) {
                $settings[$groupName] = [];
                foreach ($items as $s) {
                    $settings[$groupName][$s->key] = match ($s->type) {
                        'boolean' => (bool) $s->value,
                        'integer' => (int) $s->value,
                        'json' => json_decode($s->value, true),
                        default => $s->value,
                    };
                }
            }
        }

        return $this->success($settings);
    }

    /**
     * Update settings for a group. Body: { group: "email", settings: { key: value, ... } }
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group'    => 'required|string|max:50',
            'settings' => 'required|array',
        ]);

        Setting::setGroup($validated['group'], $validated['settings']);

        $updated = Setting::getGroup($validated['group']);
        return $this->success($updated, 'Settings updated');
    }

    /**
     * Upload a logo image for site settings.
     * Accepts a file field and returns a base64 data URI to store in the database.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|image|max:2048', // max 2MB
        ]);

        $file = $validated['file'];
        $mimeType = $file->getMimeType();
        $base64 = base64_encode(file_get_contents($file->getRealPath()));
        $dataUri = 'data:' . $mimeType . ';base64,' . $base64;

        return $this->success(['data_uri' => $dataUri], 'Logo uploaded');
    }

    // ==================== PIXELS ====================

    /**
     * Get all pixel / tracking settings.
     * Returns the 'pixels' group from the settings table.
     */
    public function getPixels(): JsonResponse
    {
        $pixels = Setting::getGroup('pixels');

        // Ensure all expected keys exist with sensible defaults
        $defaults = [
            // Facebook / Meta
            'facebook_pixel_enabled'    => false,
            'facebook_pixel_id'         => '',
            'facebook_access_token'     => '',

            // Google Analytics (GA4)
            'google_analytics_enabled'  => false,
            'google_analytics_id'       => '',

            // Google Tag Manager
            'gtm_enabled'               => false,
            'gtm_id'                    => '',

            // TikTok Pixel
            'tiktok_pixel_enabled'      => false,
            'tiktok_pixel_id'           => '',

            // Snapchat Pixel
            'snapchat_pixel_enabled'    => false,
            'snapchat_pixel_id'         => '',

            // Pinterest Tag
            'pinterest_tag_enabled'     => false,
            'pinterest_tag_id'          => '',

            // Twitter / X Pixel
            'twitter_pixel_enabled'     => false,
            'twitter_pixel_id'          => '',

            // LinkedIn Insight
            'linkedin_pixel_enabled'    => false,
            'linkedin_partner_id'       => '',

            // Custom Head / Body scripts
            'custom_head_scripts'       => '',
            'custom_body_scripts'       => '',
        ];

        return $this->success(array_merge($defaults, $pixels));
    }

    /**
     * Update pixel / tracking settings.
     * Body: flat key-value map of pixel settings (same keys as getPixels).
     */
    public function updatePixels(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'facebook_pixel_enabled'    => 'sometimes|boolean',
            'facebook_pixel_id'         => 'sometimes|nullable|string|max:50',
            'facebook_access_token'     => 'sometimes|nullable|string|max:500',

            'google_analytics_enabled'  => 'sometimes|boolean',
            'google_analytics_id'       => 'sometimes|nullable|string|max:50',

            'gtm_enabled'               => 'sometimes|boolean',
            'gtm_id'                    => 'sometimes|nullable|string|max:50',

            'tiktok_pixel_enabled'      => 'sometimes|boolean',
            'tiktok_pixel_id'           => 'sometimes|nullable|string|max:50',

            'snapchat_pixel_enabled'    => 'sometimes|boolean',
            'snapchat_pixel_id'         => 'sometimes|nullable|string|max:50',

            'pinterest_tag_enabled'     => 'sometimes|boolean',
            'pinterest_tag_id'          => 'sometimes|nullable|string|max:50',

            'twitter_pixel_enabled'     => 'sometimes|boolean',
            'twitter_pixel_id'          => 'sometimes|nullable|string|max:50',

            'linkedin_pixel_enabled'    => 'sometimes|boolean',
            'linkedin_partner_id'       => 'sometimes|nullable|string|max:50',

            'custom_head_scripts'       => 'sometimes|nullable|string|max:10000',
            'custom_body_scripts'       => 'sometimes|nullable|string|max:10000',
        ]);

        Setting::setGroup('pixels', $validated);

        $updated = Setting::getGroup('pixels');
        return $this->success($updated, 'Pixel settings updated successfully');
    }
}
