<?php
$adminControllerPath = __DIR__ . '/app/Http/Controllers/Api/AdminController.php';
$apiRoutesPath = __DIR__ . '/routes/api.php';

$content = file_get_contents($adminControllerPath);

// Extract namespace and uses
preg_match('/namespace App\\\\Http\\\\Controllers\\\\Api;\s*(.*?)\s*class AdminController/s', $content, $headerMatches);
$uses = $headerMatches[1] ?? '';

// Regex to find methods
preg_match_all('/(public|protected|private)\s+function\s+([a-zA-Z0-9_]+)\s*\((.*?)\)\s*\{/s', $content, $methodMatches, PREG_OFFSET_CAPTURE);

$methods = [];
foreach ($methodMatches[0] as $index => $match) {
    $methodName = $methodMatches[2][$index][0];
    $startOffset = $match[1];
    
    // Find matching brace
    $braceCount = 0;
    $i = $startOffset;
    $methodBody = '';
    
    // Move to the first opening brace
    while ($content[$i] !== '{') {
        $i++;
    }
    
    // Now extract until balanced
    do {
        if ($content[$i] === '{') $braceCount++;
        if ($content[$i] === '}') $braceCount--;
        $i++;
    } while ($braceCount > 0 && $i < strlen($content));
    
    $methodBody = substr($content, $startOffset, $i - $startOffset);
    $methods[$methodName] = $methodBody;
}

// Groupings based on typical Admin routes
$groups = [
    'AdminProductController' => ['getProducts', 'createProduct', 'updateProduct', 'deleteProduct'],
    'AdminCategoryController' => ['getCategories', 'createCategory', 'updateCategory', 'toggleCategoryStatus', 'deleteCategory'],
    'AdminBrandController' => ['getBrands', 'createBrand', 'updateBrand', 'toggleBrandStatus', 'deleteBrand'],
    'AdminOrderController' => ['getOrders', 'lockOrder', 'updateOrderStatus', 'addTrackingNumber', 'bookDelivery'],
    'AdminCustomerController' => ['getCustomers', 'updateCustomerPassword', 'impersonateCustomer'],
    'AdminReportController' => ['salesReport', 'report', 'debug'],
    'AdminSettingController' => ['getSettings', 'updateSettings'],
    'AdminCouponController' => ['getCoupons', 'createCoupon', 'updateCoupon', 'deleteCoupon'],
    'AdminReviewController' => ['getReviews', 'updateReviewStatus', 'deleteReview'],
    'AdminFAQController' => ['getFAQs', 'createFAQ', 'updateFAQ', 'deleteFAQ'],
    'AdminBannerController' => ['getBanners', 'createBanner', 'updateBanner', 'deleteBanner'],
    'AdminSliderController' => ['getSliders', 'createSlider', 'updateSlider', 'deleteSlider'],
    'AdminBlogController' => ['getAdminBlogs', 'createBlog', 'updateBlog', 'deleteBlog'],
    'AdminPageController' => ['getAdminPages', 'createPage', 'updatePage', 'deletePage'],
    'AdminStaffController' => ['getStaff', 'createStaff', 'updateStaff', 'deleteStaff'],
    'AdminReturnController' => ['getReturns', 'getRefundDetails', 'approveReturn', 'rejectReturn', 'processRefund'],
    'AdminPOSController' => ['posSearchProducts', 'posCreateOrder'],
    'AdminDeliveryBoyController' => ['getDeliveryBoys', 'createDeliveryBoy', 'updateDeliveryBoy', 'deleteDeliveryBoy'],
    'AdminNotificationController' => ['getNotifications', 'notificationStats', 'markNotificationRead', 'markAllRead', 'sendNotification', 'sendBulkNotification', 'deleteNotification'],
    'AdminPaymentController' => ['getPaymentGateways', 'updatePaymentGateway', 'getPaymentTransactions'],
    'AdminVendorController' => ['getVendors', 'vendorStats', 'getVendor', 'updateVendor', 'deleteVendor'],
    'AdminDeliveryPartnerController' => ['getDeliveryPartners', 'createDeliveryPartner', 'getDeliveryPartner', 'updateDeliveryPartner', 'deleteDeliveryPartner'],
    'AdminFlashSaleController' => ['getFlashSales', 'createFlashSale', 'updateFlashSale', 'deleteFlashSale'],
    'AdminCampaignController' => ['getCampaigns', 'createCampaign', 'updateCampaign', 'deleteCampaign'],
    'AdminSubscriberController' => ['getSubscribers', 'getSubscriberStats', 'updateSubscriber', 'deleteSubscriber', 'exportSubscribers'],
    'AdminDeliveryBookingController' => ['getDeliveryBookings', 'updateDeliveryBooking'],
    'AdminShippingZoneController' => ['getShippingZones', 'getShippingZone', 'createShippingZone', 'updateShippingZone', 'deleteShippingZone', 'createShippingMethod', 'updateShippingMethod'],
    'AdminSupportTicketController' => ['supportTickets', 'supportTicketStats', 'getSupportTicket', 'replyToSupportTicket', 'updateSupportTicketStatus', 'deleteSupportTicket'],
    'AdminRewardPointController' => ['getRewardPoints', 'getRewardPointStats', 'getRewardPointSettings', 'updateRewardPointSettings', 'adjustUserPoints', 'expireOldPoints'],
    'AdminDashboardController' => ['dashboard', 'getCustomerCourierOverview']
];

$missingMethods = array_keys($methods);

foreach ($groups as $controllerName => $controllerMethods) {
    $classBody = "";
    foreach ($controllerMethods as $methodName) {
        if (isset($methods[$methodName])) {
            $classBody .= "    " . $methods[$methodName] . "\n\n";
            $missingMethods = array_diff($missingMethods, [$methodName]);
        }
    }
    
    // Only create controller if it has methods
    if (!empty($classBody)) {
        $controllerTemplate = "<?php\n\nnamespace App\Http\Controllers\Api;\n\n" . $uses . "\nclass $controllerName extends Controller\n{\n$classBody}\n";
        file_put_contents(__DIR__ . "/app/Http/Controllers/Api/$controllerName.php", $controllerTemplate);
    }
}

echo "Controllers generated. Methods not grouped:\n";
print_r($missingMethods);
