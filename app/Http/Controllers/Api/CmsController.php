<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use App\Models\Banner;
use App\Models\Testimonial;
use App\Models\Faq;
use App\Models\Page;
use App\Models\ContactMessage;
use App\Models\Subscriber;
use App\Models\Currency;
use App\Models\Setting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsController extends Controller
{
    use ApiResponse;

    public function sliders(): JsonResponse
    {
        $sliders = Slider::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->success($sliders);
    }

    public function topbarSettings(): JsonResponse
    {
        $defaults = [
            'topbar_phone' => '+880 1700-000000',
            'topbar_email' => 'info@shayanmart.com',
            'topbar_address' => 'Dhaka, Bangladesh',
            'topbar_show_phone' => true,
            'topbar_show_email' => true,
            'topbar_show_address' => true,
            'topbar_show_track_order' => true,
        ];

        $saved = cache()->get('admin_settings', []);
        $payload = array_merge($defaults, $saved);

        // Store display currency = the admin's default currency
        // (managed at Admin → Config → Currencies). The storefront is
        // single-currency: it renders this symbol over values that are already
        // stored in the default currency (there is no client-side conversion).
        $currency = Currency::where('is_default', true)->first();
        $payload['currency_code'] = $currency->code ?? 'BDT';
        $payload['currency_symbol'] = $currency->symbol ?? '৳';

        // Header/footer logos + site name are managed at Admin → Config → General.
        // They live in the `settings` table (group "general") and are surfaced here
        // on the public payload so the storefront (guests included) can render them.
        $general = Setting::getGroup('general');
        $payload['header_logo'] = $general['header_logo'] ?? null;
        $payload['footer_logo'] = $general['footer_logo'] ?? null;
        $payload['site_name'] = $general['site_name'] ?? 'ShayanMart';

        return $this->success($payload);
    }

    public function banners(Request $request): JsonResponse
    {
        $query = Banner::where('is_active', true);

        if ($position = $request->position) {
            $query->where('position', $position);
        }

        $banners = $query->orderBy('sort_order')->get();
        return $this->success($banners);
    }

    public function testimonials(): JsonResponse
    {
        $testimonials = Testimonial::where('is_active', true)->get();
        return $this->success($testimonials);
    }

    public function faqs(): JsonResponse
    {
        $faqs = Faq::orderBy('sort_order')->get();
        return $this->success($faqs);
    }

    public function page(string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->success($page);
    }

    public function submitContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10',
        ]);

        $contact = ContactMessage::create($validated);

        return $this->success($contact, 'Message sent successfully', 201);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $subscriber = Subscriber::firstOrCreate(
            ['email' => $validated['email']],
            [
                'name' => '',
                'status' => 'active',
                'source' => 'footer_newsletter',
                'subscribed_at' => now(),
            ]
        );

        if ($subscriber->wasRecentlyCreated) {
            return $this->success($subscriber, 'Subscribed successfully', 201);
        }

        return $this->success($subscriber, 'You are already subscribed');
    }
}
