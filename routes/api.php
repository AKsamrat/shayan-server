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

// ==================== AUTHENTICATED ROUTES ====================
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/change-password', [AuthController::class, 'changePassword']);

    // Products (auth required for reviews)
    Route::get('/products/{id}/reviews', [ProductController::class, 'reviews']);
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

    // Admin Routes
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

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
        Route::get('/products', [AdminController::class, 'getProducts']);
        Route::post('/products', [AdminController::class, 'createProduct']);
        Route::put('/products/{id}', [AdminController::class, 'updateProduct']);
        Route::delete('/products/{id}', [AdminController::class, 'deleteProduct']);

        // Admin Categories
        Route::get('/categories', [AdminController::class, 'getCategories']);
        Route::post('/categories', [AdminController::class, 'createCategory']);
        Route::put('/categories/{id}', [AdminController::class, 'updateCategory']);
        Route::patch('/categories/{id}/toggle-status', [AdminController::class, 'toggleCategoryStatus']);
        Route::delete('/categories/{id}', [AdminController::class, 'deleteCategory']);

        // Admin Brands
        Route::get('/brands', [AdminController::class, 'getBrands']);
        Route::post('/brands', [AdminController::class, 'createBrand']);
        Route::put('/brands/{id}', [AdminController::class, 'updateBrand']);
        Route::patch('/brands/{id}/toggle-status', [AdminController::class, 'toggleBrandStatus']);
        Route::delete('/brands/{id}', [AdminController::class, 'deleteBrand']);

        // Admin Orders
        Route::get('/orders', [AdminController::class, 'getOrders']);
        Route::put('/orders/{id}/status', [AdminController::class, 'updateOrderStatus']);
        Route::put('/orders/{id}/tracking', [AdminController::class, 'addTrackingNumber']);

        // Admin Customers
        Route::get('/customers', [AdminController::class, 'getCustomers']);

        // Admin Reports
        Route::get('/reports/sales', [AdminController::class, 'salesReport']);
        Route::get('/reports/{type}', [AdminController::class, 'report']);
        Route::get('/debug', [AdminController::class, 'debug']);

        // Admin Settings
        Route::get('/settings', [AdminController::class, 'getSettings']);
        Route::put('/settings', [AdminController::class, 'updateSettings']);

        // Admin Coupons
        Route::get('/coupons', [AdminController::class, 'getCoupons']);
        Route::post('/coupons', [AdminController::class, 'createCoupon']);
        Route::put('/coupons/{id}', [AdminController::class, 'updateCoupon']);
        Route::delete('/coupons/{id}', [AdminController::class, 'deleteCoupon']);

        // Admin Reviews
        Route::get('/reviews', [AdminController::class, 'getReviews']);
        Route::delete('/reviews/{id}', [AdminController::class, 'deleteReview']);

        // Admin FAQs
        Route::get('/faqs', [AdminController::class, 'getFAQs']);
        Route::post('/faqs', [AdminController::class, 'createFAQ']);
        Route::put('/faqs/{id}', [AdminController::class, 'updateFAQ']);
        Route::delete('/faqs/{id}', [AdminController::class, 'deleteFAQ']);

        // Admin Banners
        Route::get('/banners', [AdminController::class, 'getBanners']);
        Route::post('/banners', [AdminController::class, 'createBanner']);
        Route::put('/banners/{id}', [AdminController::class, 'updateBanner']);
        Route::delete('/banners/{id}', [AdminController::class, 'deleteBanner']);

        // Admin Sliders
        Route::get('/sliders', [AdminController::class, 'getSliders']);
        Route::post('/sliders', [AdminController::class, 'createSlider']);
        Route::put('/sliders/{id}', [AdminController::class, 'updateSlider']);
        Route::delete('/sliders/{id}', [AdminController::class, 'deleteSlider']);

        // Admin Blogs
        Route::get('/blogs', [AdminController::class, 'getAdminBlogs']);
        Route::post('/blogs', [AdminController::class, 'createBlog']);
        Route::put('/blogs/{id}', [AdminController::class, 'updateBlog']);
        Route::delete('/blogs/{id}', [AdminController::class, 'deleteBlog']);

        // Admin Pages
        Route::get('/pages', [AdminController::class, 'getAdminPages']);
        Route::post('/pages', [AdminController::class, 'createPage']);
        Route::put('/pages/{id}', [AdminController::class, 'updatePage']);
        Route::delete('/pages/{id}', [AdminController::class, 'deletePage']);

        // Admin Staff
        Route::get('/staff', [AdminController::class, 'getStaff']);

        // Admin Returns & Refunds
        Route::get('/returns', [AdminController::class, 'getReturns']);
        Route::get('/returns/{id}', [AdminController::class, 'getRefundDetails']);
        Route::post('/returns/{id}/approve', [AdminController::class, 'approveReturn']);
        Route::post('/returns/{id}/reject', [AdminController::class, 'rejectReturn']);
        Route::post('/returns/{id}/refund', [AdminController::class, 'processRefund']);

        // Admin POS (Point of Sale)
        Route::get('/pos/products', [AdminController::class, 'posSearchProducts']);
        Route::post('/pos/orders', [AdminController::class, 'posCreateOrder']);

        // Admin Delivery Boys
        Route::get('/delivery-boys', [AdminController::class, 'getDeliveryBoys']);
        Route::post('/delivery-boys', [AdminController::class, 'createDeliveryBoy']);
        Route::put('/delivery-boys/{id}', [AdminController::class, 'updateDeliveryBoy']);
        Route::delete('/delivery-boys/{id}', [AdminController::class, 'deleteDeliveryBoy']);

        // Admin Order Tracking
        Route::get('/orders/{id}/tracking', [AdminController::class, 'getOrderTracking']);
        Route::post('/orders/{id}/assign-delivery', [AdminController::class, 'assignDeliveryBoy']);
        Route::put('/orders/{id}/tracking', [AdminController::class, 'updateOrderTracking']);

        // Admin Notifications
        Route::get('/notifications', [AdminController::class, 'getNotifications']);
        Route::get('/notifications/stats', [AdminController::class, 'notificationStats']);
        Route::post('/notifications', [AdminController::class, 'sendNotification']);
        Route::post('/notifications/bulk', [AdminController::class, 'sendBulkNotification']);
        Route::delete('/notifications/{id}', [AdminController::class, 'deleteNotification']);

        // Config Management (Currencies, Languages, Settings)
        Route::get('/config/currencies', [ConfigController::class, 'getCurrencies']);
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

        // Payment Gateways & Transactions
        Route::get('/payments/gateways', [AdminController::class, 'getPaymentGateways']);
        Route::put('/payments/gateways/{id}', [AdminController::class, 'updatePaymentGateway']);
        Route::get('/payments/transactions', [AdminController::class, 'getPaymentTransactions']);

        // Vendor Management
        Route::get('/vendors', [AdminController::class, 'getVendors']);
        Route::get('/vendors/stats', [AdminController::class, 'vendorStats']);
        Route::get('/vendors/{id}', [AdminController::class, 'getVendor']);
        Route::put('/vendors/{id}', [AdminController::class, 'updateVendor']);
        Route::delete('/vendors/{id}', [AdminController::class, 'deleteVendor']);

        // Delivery Partners
        Route::get('/delivery-partners', [AdminController::class, 'getDeliveryPartners']);
        Route::post('/delivery-partners', [AdminController::class, 'createDeliveryPartner']);
        Route::get('/delivery-partners/{id}', [AdminController::class, 'getDeliveryPartner']);
        Route::put('/delivery-partners/{id}', [AdminController::class, 'updateDeliveryPartner']);
        Route::delete('/delivery-partners/{id}', [AdminController::class, 'deleteDeliveryPartner']);

        // Courier customer overview / fraud check (by phone)
        Route::post('/courier/customer-overview', [AdminController::class, 'getCustomerCourierOverview']);

        // Flash Sales
        Route::get('/flash-sales', [AdminController::class, 'getFlashSales']);
        Route::post('/flash-sales', [AdminController::class, 'createFlashSale']);
        Route::put('/flash-sales/{id}', [AdminController::class, 'updateFlashSale']);
        Route::delete('/flash-sales/{id}', [AdminController::class, 'deleteFlashSale']);

        // Campaigns
        Route::get('/campaigns', [AdminController::class, 'getCampaigns']);
        Route::post('/campaigns', [AdminController::class, 'createCampaign']);
        Route::put('/campaigns/{id}', [AdminController::class, 'updateCampaign']);
        Route::delete('/campaigns/{id}', [AdminController::class, 'deleteCampaign']);

        // Subscribers
        Route::get('/subscribers', [AdminController::class, 'getSubscribers']);
        Route::get('/subscribers/stats', [AdminController::class, 'getSubscriberStats']);
        Route::put('/subscribers/{id}', [AdminController::class, 'updateSubscriber']);
        Route::delete('/subscribers/{id}', [AdminController::class, 'deleteSubscriber']);
        Route::get('/subscribers/export', [AdminController::class, 'exportSubscribers']);

        // Delivery Bookings
        Route::get('/delivery-bookings', [AdminController::class, 'getDeliveryBookings']);
        Route::post('/orders/{id}/book-delivery', [AdminController::class, 'bookDelivery']);
        Route::put('/delivery-bookings/{id}', [AdminController::class, 'updateDeliveryBooking']);

        // Shipping Zones & Methods
        Route::get('/shipping/zones', [AdminController::class, 'getShippingZones']);
        Route::get('/shipping/zones/{id}', [AdminController::class, 'getShippingZone']);
        Route::post('/shipping/zones', [AdminController::class, 'createShippingZone']);
        Route::put('/shipping/zones/{id}', [AdminController::class, 'updateShippingZone']);
        Route::delete('/shipping/zones/{id}', [AdminController::class, 'deleteShippingZone']);
        Route::post('/shipping/zones/{zoneId}/methods', [AdminController::class, 'createShippingMethod']);
        Route::put('/shipping/zones/{zoneId}/methods/{methodId}', [AdminController::class, 'updateShippingMethod']);
        // Bundles
        Route::get('/bundles', [BundleController::class, 'index']);
        Route::get('/bundles/{id}', [BundleController::class, 'show']);
        Route::post('/bundles', [BundleController::class, 'store']);
        Route::put('/bundles/{id}', [BundleController::class, 'update']);
        Route::delete('/bundles/{id}', [BundleController::class, 'destroy']);
        Route::post('/bundles/reorder', [BundleController::class, 'reorder']);

        // Support Tickets
        Route::get('/support-tickets', [AdminController::class, 'supportTickets']);
        Route::get('/support-tickets/stats', [AdminController::class, 'supportTicketStats']);
        Route::get('/support-tickets/{id}', [AdminController::class, 'getSupportTicket']);
        Route::post('/support-tickets/{id}/reply', [AdminController::class, 'replyToSupportTicket']);
        Route::put('/support-tickets/{id}/status', [AdminController::class, 'updateSupportTicketStatus']);
        Route::delete('/support-tickets/{id}', [AdminController::class, 'deleteSupportTicket']);
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
