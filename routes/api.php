<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\BlogCommentController;
use App\Http\Controllers\Api\CmsController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\AdminCategoryController;
use App\Http\Controllers\Api\AdminBrandController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\AdminCustomerController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AdminSettingController;
use App\Http\Controllers\Api\AdminCouponController;
use App\Http\Controllers\Api\AdminReviewController;
use App\Http\Controllers\Api\AdminFAQController;
use App\Http\Controllers\Api\AdminBannerController;
use App\Http\Controllers\Api\AdminSliderController;
use App\Http\Controllers\Api\AdminBlogController;
use App\Http\Controllers\Api\AdminPageController;
use App\Http\Controllers\Api\AdminStaffController;
use App\Http\Controllers\Api\AdminReturnController;
use App\Http\Controllers\Api\AdminPOSController;
use App\Http\Controllers\Api\AdminDeliveryBoyController;
use App\Http\Controllers\Api\AdminNotificationController;
use App\Http\Controllers\Api\AdminPaymentController;
use App\Http\Controllers\Api\AdminVendorController;
use App\Http\Controllers\Api\AdminDeliveryPartnerController;
use App\Http\Controllers\Api\AdminFlashSaleController;
use App\Http\Controllers\Api\AdminCampaignController;
use App\Http\Controllers\Api\AdminSubscriberController;
use App\Http\Controllers\Api\AdminDeliveryBookingController;
use App\Http\Controllers\Api\AdminShippingZoneController;
use App\Http\Controllers\Api\AdminSupportTicketController;
use App\Http\Controllers\Api\AdminRewardPointController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\DeliveryBoyController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\BundleController;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Support\Facades\Route;

// ==================== PUBLIC ROUTES ====================
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Products (public)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/new-arrivals', [ProductController::class, 'newArrivals']);
Route::get('/products/flash-sale', [ProductController::class, 'flashSale']);
Route::get('/products/best-sellers', [ProductController::class, 'bestSellers']);
Route::get('/products/{id}/related', [ProductController::class, 'related']);
Route::get('/products/{id}/frequently-bought', [ProductController::class, 'frequentlyBought']);
Route::get('/products/{id}/reviews', [ProductController::class, 'reviews']);
Route::get('/products/{slug}', [ProductController::class, 'show']);


// Cart (supports both guest and authenticated users via Sanctum stateful domains)
Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart/items', [CartController::class, 'addItem']);
Route::put('/cart/items/{itemId}', [CartController::class, 'updateItem']);
Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem']);
Route::post('/cart/coupon', [CartController::class, 'applyCoupon']);
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon']);
Route::post('/cart/shipping', [CartController::class, 'calculateShipping']);

// Categories (public)
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);
Route::get('/categories/{slug}/products', [CategoryController::class, 'products']);

// Brands (public)
Route::get('/brands', [BrandController::class, 'index']);
Route::get('/brands/{slug}', [BrandController::class, 'show']);
Route::get('/brands/{slug}/products', [BrandController::class, 'products']);

// CMS (public)
Route::get('/cms/sliders', [CmsController::class, 'sliders']);
Route::get('/cms/banners', [CmsController::class, 'banners']);
Route::get('/cms/testimonials', [CmsController::class, 'testimonials']);
Route::get('/cms/faqs', [CmsController::class, 'faqs']);
Route::get('/cms/pages/{slug}', [CmsController::class, 'page']);
Route::get('/cms/topbar', [CmsController::class, 'topbarSettings']);
Route::post('/cms/contact', [CmsController::class, 'submitContact']);
Route::post('/cms/subscribe', [CmsController::class, 'subscribe']);
Route::post('/tracking/events', [App\Http\Controllers\Api\FacebookPixelEventController::class, 'store']);

// Blogs (public)
Route::get('/blogs', [BlogController::class, 'index']);
Route::get('/blogs/{slug}', [BlogController::class, 'show']);
Route::get('/blogs/{slug}/comments', [BlogCommentController::class, 'index']);
Route::post('/blogs/{slug}/comments', [BlogCommentController::class, 'store']);

// Bundles (public)
Route::get('/bundles/active', [BundleController::class, 'getActive']);

// Shipping estimation (public - no auth required)
Route::post('/shipping/estimate', [CartController::class, 'estimateShipping']);

// Active delivery zones with a representative cost (public - no auth required)
Route::get('/shipping/zones', [CartController::class, 'publicZones']);

// Pathao Customer History / Fraud Check (public with rate limiting)
Route::post('/pathao/fraud-check', [\App\Http\Controllers\Api\PathaoFraudCheckController::class, 'check'])->middleware('throttle:30,1');

// ==================== AUTHENTICATED ROUTES ====================
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/change-password', [AuthController::class, 'changePassword']);

    // Products (auth required for submitting reviews)
    Route::post('/products/{id}/reviews', [ProductController::class, 'addReview']);

    // Blog comments (auth required for delete)
    Route::delete('/comments/{id}', [BlogCommentController::class, 'destroy']);



    // Orders
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{id}/return', [OrderController::class, 'requestReturn']);

    // Addresses
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::put('/addresses/{id}', [AddressController::class, 'update']);
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
    Route::put('/addresses/{id}/default', [AddressController::class, 'setDefault']);

    // Wishlist
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy']);
    Route::get('/wishlist/check/{productId}', [WishlistController::class, 'check']);

    // Customer Dashboard
    Route::get('/customer/dashboard', [CustomerController::class, 'dashboard']);
    Route::get('/customer/notifications', [CustomerController::class, 'notifications']);
    Route::put('/customer/notifications/{id}/read', [CustomerController::class, 'markNotificationRead']);
    Route::put('/customer/notifications/read-all', [CustomerController::class, 'markAllRead']);

    // Notification Preferences
    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'getPreferences']);
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'updatePreferences']);

    Route::get('/customer/wallet', [CustomerController::class, 'wallet']);
    Route::get('/customer/wallet/transactions', [CustomerController::class, 'walletTransactions']);
    Route::get('/customer/rewards', [CustomerController::class, 'rewardPoints']);
    Route::get('/customer/referrals', [CustomerController::class, 'referrals']);
    Route::get('/customer/support', [CustomerController::class, 'supportTickets']);
    Route::post('/customer/support', [CustomerController::class, 'createSupportTicket']);
    Route::post('/customer/support/{id}/reply', [CustomerController::class, 'replyToTicket']);
    Route::post('/customer/rewards/redeem', [CustomerController::class, 'redeemPoints']);
    Route::post('/customer/rewards/check', [CustomerController::class, 'checkRedemption']);

    Route::get('/test-modules', [RoleController::class, 'getPermissionModules']);
    
    // Admin Routes
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin,super_admin,manager,staff'])->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'dashboard']);

        // Admin Roles & Permissions
        Route::get('/permissions', [RoleController::class, 'getPermissions']);
        Route::get('/permission-modules', [RoleController::class, 'getPermissionModules']);
        Route::post('/permission-modules', [RoleController::class, 'createPermissionModule']);
        Route::put('/permission-modules/{id}', [RoleController::class, 'updatePermissionModule']);
        Route::delete('/permission-modules/{id}', [RoleController::class, 'deletePermissionModule']);
        Route::post('/permission-modules/reorder', [RoleController::class, 'reorderPermissionModules']);
        Route::post('/permission-modules/{moduleId}/actions', [RoleController::class, 'createPermissionAction']);
        Route::get('/permission-actions', [RoleController::class, 'getPermissionActions']);
        Route::get('/permission-modules/{moduleId}/actions', [RoleController::class, 'getPermissionModuleActions']);
        Route::put('/permission-actions/{id}', [RoleController::class, 'updatePermissionAction']);
        Route::delete('/permission-actions/{id}', [RoleController::class, 'deletePermissionAction']);
        Route::post('/permission-modules/{moduleId}/actions/reorder', [RoleController::class, 'reorderPermissionActions']);
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::get('/roles/{id}', [RoleController::class, 'show']);
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
        Route::post('/roles/assign', [RoleController::class, 'assignRole']);

        // Admin Products
        Route::get('/products', [AdminProductController::class, 'getProducts']);
        Route::post('/products', [AdminProductController::class, 'createProduct']);
        Route::put('/products/{id}', [AdminProductController::class, 'updateProduct']);
        Route::delete('/products/{id}', [AdminProductController::class, 'deleteProduct']);

        // Admin Categories
        Route::get('/categories', [AdminCategoryController::class, 'getCategories']);
        Route::post('/categories', [AdminCategoryController::class, 'createCategory']);
        Route::put('/categories/{id}', [AdminCategoryController::class, 'updateCategory']);
        Route::patch('/categories/{id}/toggle-status', [AdminCategoryController::class, 'toggleCategoryStatus']);
        Route::delete('/categories/{id}', [AdminCategoryController::class, 'deleteCategory']);

        // Admin Brands
        Route::get('/brands', [AdminBrandController::class, 'getBrands']);
        Route::post('/brands', [AdminBrandController::class, 'createBrand']);
        Route::put('/brands/{id}', [AdminBrandController::class, 'updateBrand']);
        Route::patch('/brands/{id}/toggle-status', [AdminBrandController::class, 'toggleBrandStatus']);
        Route::delete('/brands/{id}', [AdminBrandController::class, 'deleteBrand']);

        // Admin Orders
        Route::get('/orders', [AdminOrderController::class, 'getOrders']);
        Route::put('/orders/{id}/lock', [AdminOrderController::class, 'lockOrder']);
        Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateOrderStatus']);
        Route::put('/orders/{id}/tracking', [AdminOrderController::class, 'addTrackingNumber']);

        // Admin Customers
        Route::get('/customers', [AdminCustomerController::class, 'getCustomers']);
        Route::post('/customers/{id}/profile/password', [AdminCustomerController::class, 'updateCustomerPassword']);
        Route::post('/customers/{id}/impersonate', [AdminCustomerController::class, 'impersonateCustomer']);

        // Admin Reports
        Route::get('/reports/sales', [AdminReportController::class, 'salesReport']);
        Route::get('/reports/{type}', [AdminReportController::class, 'report']);
        Route::get('/debug', [AdminReportController::class, 'debug']);

        // Admin Settings
        Route::get('/settings', [AdminSettingController::class, 'getSettings']);
        Route::put('/settings', [AdminSettingController::class, 'updateSettings']);

        // Admin Coupons
        Route::get('/coupons', [AdminCouponController::class, 'getCoupons']);
        Route::post('/coupons', [AdminCouponController::class, 'createCoupon']);
        Route::put('/coupons/{id}', [AdminCouponController::class, 'updateCoupon']);
        Route::delete('/coupons/{id}', [AdminCouponController::class, 'deleteCoupon']);

        // Admin Reviews
        Route::get('/reviews', [AdminReviewController::class, 'getReviews']);
        Route::put('/reviews/{id}/status', [AdminReviewController::class, 'updateReviewStatus']);
        Route::delete('/reviews/{id}', [AdminReviewController::class, 'deleteReview']);

        // Admin FAQs
        Route::get('/faqs', [AdminFAQController::class, 'getFAQs']);
        Route::post('/faqs', [AdminFAQController::class, 'createFAQ']);
        Route::put('/faqs/{id}', [AdminFAQController::class, 'updateFAQ']);
        Route::delete('/faqs/{id}', [AdminFAQController::class, 'deleteFAQ']);

        // Admin Banners
        Route::get('/banners', [AdminBannerController::class, 'getBanners']);
        Route::post('/banners', [AdminBannerController::class, 'createBanner']);
        Route::put('/banners/{id}', [AdminBannerController::class, 'updateBanner']);
        Route::delete('/banners/{id}', [AdminBannerController::class, 'deleteBanner']);

        // Admin Sliders
        Route::get('/sliders', [AdminSliderController::class, 'getSliders']);
        Route::post('/sliders', [AdminSliderController::class, 'createSlider']);
        Route::put('/sliders/{id}', [AdminSliderController::class, 'updateSlider']);
        Route::delete('/sliders/{id}', [AdminSliderController::class, 'deleteSlider']);

        // Admin Blogs
        Route::get('/blogs', [AdminBlogController::class, 'getAdminBlogs']);
        Route::post('/blogs', [AdminBlogController::class, 'createBlog']);
        Route::put('/blogs/{id}', [AdminBlogController::class, 'updateBlog']);
        Route::delete('/blogs/{id}', [AdminBlogController::class, 'deleteBlog']);

        // Admin Pages
        Route::get('/pages', [AdminPageController::class, 'getAdminPages']);
        Route::post('/pages', [AdminPageController::class, 'createPage']);
        Route::put('/pages/{id}', [AdminPageController::class, 'updatePage']);
        Route::delete('/pages/{id}', [AdminPageController::class, 'deletePage']);

        // Admin Staff
        Route::get('/staff', [AdminStaffController::class, 'getStaff']);
        Route::post('/staff', [AdminStaffController::class, 'createStaff']);
        Route::put('/staff/{id}', [AdminStaffController::class, 'updateStaff']);
        Route::delete('/staff/{id}', [AdminStaffController::class, 'deleteStaff']);

        // Admin Returns & Refunds
        Route::get('/returns', [AdminReturnController::class, 'getReturns']);
        Route::get('/returns/{id}', [AdminReturnController::class, 'getRefundDetails']);
        Route::post('/returns/{id}/approve', [AdminReturnController::class, 'approveReturn']);
        Route::post('/returns/{id}/reject', [AdminReturnController::class, 'rejectReturn']);
        Route::post('/returns/{id}/refund', [AdminReturnController::class, 'processRefund']);

        // Admin POS (Point of Sale)
        Route::get('/pos/products', [AdminPOSController::class, 'posSearchProducts']);
        Route::post('/pos/orders', [AdminPOSController::class, 'posCreateOrder']);

        // Admin Delivery Boys
        Route::get('/delivery-boys', [AdminDeliveryBoyController::class, 'getDeliveryBoys']);
        Route::post('/delivery-boys', [AdminDeliveryBoyController::class, 'createDeliveryBoy']);
        Route::put('/delivery-boys/{id}', [AdminDeliveryBoyController::class, 'updateDeliveryBoy']);
        Route::delete('/delivery-boys/{id}', [AdminDeliveryBoyController::class, 'deleteDeliveryBoy']);

        // Admin Order Tracking
        Route::get('/orders/{id}/tracking', [AdminOrderController::class, 'getOrderTracking']);
        Route::post('/orders/{id}/assign-delivery', [AdminOrderController::class, 'assignDeliveryBoy']);
        Route::put('/orders/{id}/tracking', [AdminOrderController::class, 'updateOrderTracking']);

        // Admin Notifications
        Route::get('/notifications', [AdminNotificationController::class, 'getNotifications']);
        Route::get('/notifications/stats', [AdminNotificationController::class, 'notificationStats']);
        Route::put('/notifications/{id}/read', [AdminNotificationController::class, 'markNotificationRead']);
        Route::put('/notifications/read-all', [AdminNotificationController::class, 'markAllRead']);
        Route::post('/notifications', [AdminNotificationController::class, 'sendNotification']);
        Route::post('/notifications/bulk', [AdminNotificationController::class, 'sendBulkNotification']);
        Route::delete('/notifications/{id}', [AdminNotificationController::class, 'deleteNotification']);

        // Config Management (Currencies, Languages, Settings)
        Route::get('/config/currencies', [ConfigController::class, 'getCurrencies']);
        Route::get('/config/currencies/default', [ConfigController::class, 'getDefaultCurrency']);
        Route::post('/config/currencies', [ConfigController::class, 'createCurrency']);
        Route::put('/config/currencies/{id}', [ConfigController::class, 'updateCurrency']);
        Route::delete('/config/currencies/{id}', [ConfigController::class, 'deleteCurrency']);

        Route::get('/config/languages', [ConfigController::class, 'getLanguages']);
        Route::post('/config/languages', [ConfigController::class, 'createLanguage']);
        Route::put('/config/languages/{id}', [ConfigController::class, 'updateLanguage']);
        Route::delete('/config/languages/{id}', [ConfigController::class, 'deleteLanguage']);

        Route::get('/config/settings', [ConfigController::class, 'getSettings']);
        Route::put('/config/settings', [ConfigController::class, 'updateSettings']);
        Route::post('/config/upload-logo', [ConfigController::class, 'uploadLogo']);

        // Pixel / Tracking Settings
        Route::get('/config/pixels', [ConfigController::class, 'getPixels']);
        Route::put('/config/pixels', [ConfigController::class, 'updatePixels']);
        Route::get('/config/pixel-events', [App\Http\Controllers\Api\FacebookPixelEventController::class, 'index']);

        // Payment Gateways & Transactions
        Route::get('/payments/gateways', [AdminPaymentController::class, 'getPaymentGateways']);
        Route::put('/payments/gateways/{id}', [AdminPaymentController::class, 'updatePaymentGateway']);
        Route::get('/payments/transactions', [AdminPaymentController::class, 'getPaymentTransactions']);

        // Vendor Management
        Route::get('/vendors', [AdminVendorController::class, 'getVendors']);
        Route::get('/vendors/stats', [AdminVendorController::class, 'vendorStats']);
        Route::get('/vendors/{id}', [AdminVendorController::class, 'getVendor']);
        Route::put('/vendors/{id}', [AdminVendorController::class, 'updateVendor']);
        Route::delete('/vendors/{id}', [AdminVendorController::class, 'deleteVendor']);

        // Delivery Partners
        Route::get('/delivery-partners', [AdminDeliveryPartnerController::class, 'getDeliveryPartners']);
        Route::post('/delivery-partners', [AdminDeliveryPartnerController::class, 'createDeliveryPartner']);
        Route::get('/delivery-partners/{id}', [AdminDeliveryPartnerController::class, 'getDeliveryPartner']);
        Route::put('/delivery-partners/{id}', [AdminDeliveryPartnerController::class, 'updateDeliveryPartner']);
        Route::delete('/delivery-partners/{id}', [AdminDeliveryPartnerController::class, 'deleteDeliveryPartner']);

        // Courier API specific routes (Pathao)
        Route::get('/courier/pathao/cities', [\App\Http\Controllers\Api\CourierController::class, 'getPathaoCities']);
        Route::get('/courier/pathao/cities/{cityId}/zones', [\App\Http\Controllers\Api\CourierController::class, 'getPathaoZones']);
        Route::get('/courier/pathao/zones/{zoneId}/areas', [\App\Http\Controllers\Api\CourierController::class, 'getPathaoAreas']);
        Route::get('/courier/pathao/stores', [\App\Http\Controllers\Api\CourierController::class, 'getPathaoStores']);
        Route::post('/courier/pathao/stores', [\App\Http\Controllers\Api\CourierController::class, 'createPathaoStore']);
        Route::post('/courier/pathao/price-plan', [\App\Http\Controllers\Api\CourierController::class, 'calculatePathaoPrice']);
        Route::post('/courier/pathao/orders', [\App\Http\Controllers\Api\CourierController::class, 'createPathaoOrder']);
        Route::get('/courier/pathao/orders/{consignmentId}', [\App\Http\Controllers\Api\CourierController::class, 'getPathaoOrderInfo']);

        // Courier API specific routes (Steadfast)
        Route::get('/courier/steadfast/ping', [\App\Http\Controllers\Api\SteadfastController::class, 'ping']);
        Route::get('/courier/steadfast/balance', [\App\Http\Controllers\Api\SteadfastController::class, 'getBalance']);
        Route::post('/courier/steadfast/orders', [\App\Http\Controllers\Api\SteadfastController::class, 'createOrder']);
        Route::post('/courier/steadfast/orders/bulk', [\App\Http\Controllers\Api\SteadfastController::class, 'bulkOrder']);
        Route::post('/courier/steadfast/orders/bulk-extended', [\App\Http\Controllers\Api\SteadfastController::class, 'bulkOrderExtended']);
        Route::get('/courier/steadfast/status/cid/{cid}', [\App\Http\Controllers\Api\SteadfastController::class, 'statusByCid']);
        Route::get('/courier/steadfast/status/return-cid/{cid}', [\App\Http\Controllers\Api\SteadfastController::class, 'statusWithReturnByCid']);
        Route::get('/courier/steadfast/status/invoice/{invoice}', [\App\Http\Controllers\Api\SteadfastController::class, 'statusByInvoice']);
        Route::get('/courier/steadfast/status/tracking/{trackingCode}', [\App\Http\Controllers\Api\SteadfastController::class, 'statusByTrackingCode']);
        Route::get('/courier/steadfast/trackings/invoice/{invoice}', [\App\Http\Controllers\Api\SteadfastController::class, 'trackingsByInvoice']);
        Route::post('/courier/steadfast/pickup-requests', [\App\Http\Controllers\Api\SteadfastController::class, 'createPickupRequest']);
        Route::get('/courier/steadfast/return-requests', [\App\Http\Controllers\Api\SteadfastController::class, 'getReturnRequests']);
        Route::post('/courier/steadfast/return-requests', [\App\Http\Controllers\Api\SteadfastController::class, 'createReturnRequest']);
        Route::get('/courier/steadfast/return-requests/{id}', [\App\Http\Controllers\Api\SteadfastController::class, 'getReturnRequest']);
        Route::get('/courier/steadfast/payments', [\App\Http\Controllers\Api\SteadfastController::class, 'getPayments']);
        Route::get('/courier/steadfast/payments/{paymentId}', [\App\Http\Controllers\Api\SteadfastController::class, 'getPayment']);
        Route::get('/courier/steadfast/police-stations', [\App\Http\Controllers\Api\SteadfastController::class, 'getPoliceStations']);
        Route::get('/courier/steadfast/fraud-score/{phone}', [\App\Http\Controllers\Api\SteadfastController::class, 'getFraudScore']);

        // Courier customer overview / fraud check (by phone)
        Route::post('/courier/customer-overview', [AdminDashboardController::class, 'getCustomerCourierOverview']);
        Route::post('/pathao/fraud-check', [\App\Http\Controllers\Api\PathaoFraudCheckController::class, 'check']);
        Route::post('/courier/pathao/fraud-check', [\App\Http\Controllers\Api\PathaoFraudCheckController::class, 'check']);

        // Flash Sales
        Route::get('/flash-sales', [AdminFlashSaleController::class, 'getFlashSales']);
        Route::post('/flash-sales', [AdminFlashSaleController::class, 'createFlashSale']);
        Route::put('/flash-sales/{id}', [AdminFlashSaleController::class, 'updateFlashSale']);
        Route::delete('/flash-sales/{id}', [AdminFlashSaleController::class, 'deleteFlashSale']);

        // Campaigns
        Route::get('/campaigns', [AdminCampaignController::class, 'getCampaigns']);
        Route::post('/campaigns', [AdminCampaignController::class, 'createCampaign']);
        Route::put('/campaigns/{id}', [AdminCampaignController::class, 'updateCampaign']);
        Route::delete('/campaigns/{id}', [AdminCampaignController::class, 'deleteCampaign']);

        // Subscribers
        Route::get('/subscribers', [AdminSubscriberController::class, 'getSubscribers']);
        Route::get('/subscribers/stats', [AdminSubscriberController::class, 'getSubscriberStats']);
        Route::put('/subscribers/{id}', [AdminSubscriberController::class, 'updateSubscriber']);
        Route::delete('/subscribers/{id}', [AdminSubscriberController::class, 'deleteSubscriber']);
        Route::get('/subscribers/export', [AdminSubscriberController::class, 'exportSubscribers']);

        // Delivery Bookings
        Route::get('/delivery-bookings', [AdminDeliveryBookingController::class, 'getDeliveryBookings']);
        Route::post('/orders/{id}/book-delivery', [AdminOrderController::class, 'bookDelivery']);
        Route::put('/delivery-bookings/{id}', [AdminDeliveryBookingController::class, 'updateDeliveryBooking']);

        // Shipping Zones & Methods
        Route::get('/shipping/zones', [AdminShippingZoneController::class, 'getShippingZones']);
        Route::get('/shipping/zones/{id}', [AdminShippingZoneController::class, 'getShippingZone']);
        Route::post('/shipping/zones', [AdminShippingZoneController::class, 'createShippingZone']);
        Route::put('/shipping/zones/{id}', [AdminShippingZoneController::class, 'updateShippingZone']);
        Route::delete('/shipping/zones/{id}', [AdminShippingZoneController::class, 'deleteShippingZone']);
        Route::post('/shipping/zones/{zoneId}/methods', [AdminShippingZoneController::class, 'createShippingMethod']);
        Route::put('/shipping/zones/{zoneId}/methods/{methodId}', [AdminShippingZoneController::class, 'updateShippingMethod']);
        // Bundles
        Route::get('/bundles', [BundleController::class, 'index']);
        Route::get('/bundles/{id}', [BundleController::class, 'show']);
        Route::post('/bundles', [BundleController::class, 'store']);
        Route::put('/bundles/{id}', [BundleController::class, 'update']);
        Route::delete('/bundles/{id}', [BundleController::class, 'destroy']);
        Route::post('/bundles/reorder', [BundleController::class, 'reorder']);

        // Courier Panel
        Route::get('/courier-panel', [\App\Http\Controllers\Api\CourierPanelController::class, 'index']);
        Route::post('/courier-panel/sync', [\App\Http\Controllers\Api\CourierPanelController::class, 'syncStatuses']);

        // Support Tickets
        Route::get('/support-tickets', [AdminSupportTicketController::class, 'supportTickets']);
        Route::get('/support-tickets/stats', [AdminSupportTicketController::class, 'supportTicketStats']);
        Route::get('/support-tickets/{id}', [AdminSupportTicketController::class, 'getSupportTicket']);
        Route::post('/support-tickets/{id}/reply', [AdminSupportTicketController::class, 'replyToSupportTicket']);
        Route::put('/support-tickets/{id}/status', [AdminSupportTicketController::class, 'updateSupportTicketStatus']);
        Route::delete('/support-tickets/{id}', [AdminSupportTicketController::class, 'deleteSupportTicket']);

        // Reward Points
        Route::get('/reward-points', [AdminRewardPointController::class, 'getRewardPoints']);
        Route::get('/reward-points/stats', [AdminRewardPointController::class, 'getRewardPointStats']);
        Route::get('/reward-points/settings', [AdminRewardPointController::class, 'getRewardPointSettings']);
        Route::put('/reward-points/settings', [AdminRewardPointController::class, 'updateRewardPointSettings']);
        Route::post('/reward-points/adjust', [AdminRewardPointController::class, 'adjustUserPoints']);
        Route::post('/reward-points/expire-old', [AdminRewardPointController::class, 'expireOldPoints']);
    });

    // ==================== VENDOR ROUTES ====================
    Route::prefix('vendor')->middleware(['auth:sanctum', 'role:vendor'])->group(function () {
        // Shop Profile
        Route::get('/shop', [VendorController::class, 'getShop']);
        Route::put('/shop', [VendorController::class, 'updateShop']);
        Route::post('/shop/logo', [VendorController::class, 'updateShopLogo']);
        Route::post('/shop/banner', [VendorController::class, 'updateShopBanner']);

        // Dashboard
        Route::get('/dashboard', [VendorController::class, 'dashboard']);

        // Products
        Route::get('/products', [VendorController::class, 'getProducts']);
        Route::get('/products/{id}', [VendorController::class, 'getProduct']);
        Route::post('/products', [VendorController::class, 'createProduct']);
        Route::put('/products/{id}', [VendorController::class, 'updateProduct']);
        Route::delete('/products/{id}', [VendorController::class, 'deleteProduct']);

        // Orders
        Route::get('/orders', [VendorController::class, 'getOrders']);
        Route::get('/orders/{id}', [VendorController::class, 'getOrder']);
        Route::put('/orders/{id}/status', [VendorController::class, 'updateOrderStatus']);
        Route::put('/orders/{id}/tracking', [VendorController::class, 'addTrackingNumber']);

        // Payouts
        Route::get('/payouts', [VendorController::class, 'getPayouts']);
        Route::post('/payouts/request', [VendorController::class, 'requestPayout']);

        // Reviews
        Route::get('/reviews', [VendorController::class, 'getReviews']);

        // Categories & Brands (for product forms)
        Route::get('/categories', [VendorController::class, 'getCategories']);
        Route::get('/brands', [VendorController::class, 'getBrands']);

        // Vendor Notifications
        Route::get('/notifications', [VendorController::class, 'getNotifications']);
        Route::put('/notifications/{id}/read', [VendorController::class, 'markNotificationRead']);
        Route::put('/notifications/read-all', [VendorController::class, 'markAllRead']);
    });

    // ==================== DELIVERY BOY ROUTES ====================
    Route::prefix('delivery-boy')->middleware(['auth:sanctum', 'role:delivery_boy'])->group(function () {
        Route::get('/dashboard', [DeliveryBoyController::class, 'dashboard']);
        Route::get('/orders', [DeliveryBoyController::class, 'myOrders']);
        Route::get('/orders/{id}', [DeliveryBoyController::class, 'orderDetail']);
        Route::put('/orders/{id}/status', [DeliveryBoyController::class, 'updateDeliveryStatus']);
        Route::get('/profile', [DeliveryBoyController::class, 'profile']);
        Route::put('/profile', [DeliveryBoyController::class, 'updateProfile']);
    });
});
