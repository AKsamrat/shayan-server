<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use App\Models\Role;
use App\Models\Blog;
use App\Models\Slider;
use App\Models\Banner;
use App\Models\Testimonial;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Coupon;
use App\Models\Review;
use App\Models\OrderItem;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\AdminAccount;
use App\Models\AdminAccountTransaction;
use App\Traits\ApiResponse;
use App\Models\Notification as NotificationModel;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\VendorShop;
use App\Models\DeliveryPartner;
use App\Models\DeliveryBooking;
use App\Models\ShippingZone;
use App\Models\ShippingMethod;
use App\Models\Brand;
use App\Models\FlashSale;
use App\Models\Campaign;
use App\Models\Subscriber;
use App\Models\ProductSpecification;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\RewardPoint;
use App\Services\Courier\CourierFraudChecker;
use App\Services\NotificationService;
use App\Services\RewardPointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
class AdminReviewController extends AdminController
{
    public function getReviews(Request $request): JsonResponse
    {
        $this->ensurePermission('reviews.view');
        
        $query = Review::with(['user', 'product']);
        
        if ($request->has('status')) {
            $status = $request->status;
            if ($status === 'approved') {
                $query->where('is_approved', true);
            } elseif ($status === 'pending') {
                $query->where('is_approved', false);
            }
        }
        
        $result = $this->paginated($query->latest());
        return $this->success($result);
    }

    public function updateReviewStatus(Request $request, int $id): JsonResponse
    {
        $this->ensurePermission('reviews.edit');
        
        $validated = $request->validate([
            'is_approved' => 'required|boolean',
        ]);

        $review = Review::findOrFail($id);
        $review->update(['is_approved' => $validated['is_approved']]);
        
        // Update product average rating
        $product = Product::findOrFail($review->product_id);
        $approvedReviews = $product->reviews()->where('is_approved', true);
        $avgRating = $approvedReviews->avg('rating') ?: 0;
        $product->update([
            'average_rating' => round($avgRating, 1),
            'reviews_count' => $approvedReviews->count(),
        ]);

        return $this->success($review->fresh()->load(['user', 'product']), 'Review status updated');
    }

    public function deleteReview(int $id): JsonResponse
    {
        $this->ensurePermission('reviews.delete');
        $review = Review::findOrFail($id);
        $productId = $review->product_id;
        $review->delete();
        
        // Update product average rating
        $product = Product::findOrFail($productId);
        $approvedReviews = $product->reviews()->where('is_approved', true);
        $avgRating = $approvedReviews->avg('rating') ?: 0;
        $product->update([
            'average_rating' => round($avgRating, 1),
            'reviews_count' => $approvedReviews->count(),
        ]);

        return $this->success(null, 'Review deleted');
    }
}
