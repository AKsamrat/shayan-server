<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    use ApiResponse;

    /**
     * Get the authenticated user's notification preferences.
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'email_notifications' => $user->email_notifications,
            'sms_notifications' => $user->sms_notifications,
            'push_notifications' => $user->push_notifications,
            'email_order_updates' => $user->email_order_updates,
            'email_shipping_updates' => $user->email_shipping_updates,
            'email_promotions' => $user->email_promotions,
            'email_newsletter' => $user->email_newsletter,
            'sms_order_updates' => $user->sms_order_updates,
            'sms_shipping_updates' => $user->sms_shipping_updates,
            'sms_promotions' => $user->sms_promotions,
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_notifications' => 'sometimes|boolean',
            'sms_notifications' => 'sometimes|boolean',
            'push_notifications' => 'sometimes|boolean',
            'email_order_updates' => 'sometimes|boolean',
            'email_shipping_updates' => 'sometimes|boolean',
            'email_promotions' => 'sometimes|boolean',
            'email_newsletter' => 'sometimes|boolean',
            'sms_order_updates' => 'sometimes|boolean',
            'sms_shipping_updates' => 'sometimes|boolean',
            'sms_promotions' => 'sometimes|boolean',
        ]);

        // If master email toggle is off, disable all email sub-toggles
        if (isset($validated['email_notifications']) && !$validated['email_notifications']) {
            $validated['email_order_updates'] = false;
            $validated['email_shipping_updates'] = false;
            $validated['email_promotions'] = false;
            $validated['email_newsletter'] = false;
        }

        // If master SMS toggle is off, disable all SMS sub-toggles
        if (isset($validated['sms_notifications']) && !$validated['sms_notifications']) {
            $validated['sms_order_updates'] = false;
            $validated['sms_shipping_updates'] = false;
            $validated['sms_promotions'] = false;
        }

        $request->user()->update($validated);

        return $this->success($request->user()->only([
            'email_notifications',
            'sms_notifications',
            'push_notifications',
            'email_order_updates',
            'email_shipping_updates',
            'email_promotions',
            'email_newsletter',
            'sms_order_updates',
            'sms_shipping_updates',
            'sms_promotions',
        ]), 'Notification preferences updated successfully');
    }
}
