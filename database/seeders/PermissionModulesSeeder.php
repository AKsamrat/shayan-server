<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PermissionModule;
use App\Models\PermissionAction;

class PermissionModulesSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data - delete in reverse order to respect foreign keys
        PermissionAction::query()->delete();
        PermissionModule::query()->delete();

        // Admin Modules
        $modules = [
            // Dashboard
            [
                'name' => 'dashboard',
                'display_name' => 'Dashboard',
                'description' => 'Access to the admin dashboard',
                'sort_order' => 1,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                ]
            ],
            // Products
            [
                'name' => 'products',
                'display_name' => 'Products',
                'description' => 'Manage store products',
                'sort_order' => 2,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Orders
            [
                'name' => 'orders',
                'display_name' => 'Orders',
                'description' => 'Manage customer orders',
                'sort_order' => 3,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 2],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 3],
                    ['name' => 'update_status', 'display_name' => 'Update Status', 'sort_order' => 4],
                ]
            ],
            // Returns
            [
                'name' => 'returns',
                'display_name' => 'Returns',
                'description' => 'Manage product returns and refunds',
                'sort_order' => 4,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 2],
                ]
            ],
            // Customers
            [
                'name' => 'customers',
                'display_name' => 'Customers',
                'description' => 'View and manage customers',
                'sort_order' => 5,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 2],
                ]
            ],
            // Staff
            [
                'name' => 'staff',
                'display_name' => 'Staff',
                'description' => 'Manage admin staff members',
                'sort_order' => 6,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Delivery Boys
            [
                'name' => 'delivery_boys',
                'display_name' => 'Delivery Boys',
                'description' => 'Manage delivery personnel',
                'sort_order' => 7,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Vendors
            [
                'name' => 'vendors',
                'display_name' => 'Vendors',
                'description' => 'Manage vendors and their shops',
                'sort_order' => 8,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                    ['name' => 'update_status', 'display_name' => 'Update Status', 'sort_order' => 5],
                ]
            ],
            // Delivery Partners
            [
                'name' => 'delivery_partners',
                'display_name' => 'Delivery Partners',
                'description' => 'Manage third-party delivery partners',
                'sort_order' => 9,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Delivery Bookings
            [
                'name' => 'delivery_bookings',
                'display_name' => 'Delivery Bookings',
                'description' => 'Manage delivery bookings',
                'sort_order' => 10,
                'is_system' => false,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                    ['name' => 'update_status', 'display_name' => 'Update Status', 'sort_order' => 5],
                ]
            ],
            // Categories
            [
                'name' => 'categories',
                'display_name' => 'Categories',
                'description' => 'Manage product categories',
                'sort_order' => 11,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Brands
            [
                'name' => 'brands',
                'display_name' => 'Brands',
                'description' => 'Manage product brands',
                'sort_order' => 12,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Coupons
            [
                'name' => 'coupons',
                'display_name' => 'Coupons',
                'description' => 'Manage discount coupons',
                'sort_order' => 13,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Bundles
            [
                'name' => 'bundles',
                'display_name' => 'Bundles',
                'description' => 'Manage product bundles',
                'sort_order' => 14,
                'is_system' => false,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                    ['name' => 'reorder', 'display_name' => 'Reorder', 'sort_order' => 5],
                ]
            ],
            // Flash Sales
            [
                'name' => 'flash_sales',
                'display_name' => 'Flash Sales',
                'description' => 'Manage flash sale campaigns',
                'sort_order' => 15,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Campaigns
            [
                'name' => 'campaigns',
                'display_name' => 'Campaigns',
                'description' => 'Manage marketing campaigns',
                'sort_order' => 16,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Subscribers
            [
                'name' => 'subscribers',
                'display_name' => 'Subscribers',
                'description' => 'Manage newsletter subscribers',
                'sort_order' => 17,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Reports
            [
                'name' => 'reports',
                'display_name' => 'Reports',
                'description' => 'View sales and other reports',
                'sort_order' => 18,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                ]
            ],
            // Sliders
            [
                'name' => 'sliders',
                'display_name' => 'Sliders',
                'description' => 'Manage homepage sliders',
                'sort_order' => 19,
                'is_system' => false,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Banners
            [
                'name' => 'banners',
                'display_name' => 'Banners',
                'description' => 'Manage promotional banners',
                'sort_order' => 20,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Testimonials
            [
                'name' => 'testimonials',
                'display_name' => 'Testimonials',
                'description' => 'Manage customer testimonials',
                'sort_order' => 21,
                'is_system' => false,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Blogs
            [
                'name' => 'blogs',
                'display_name' => 'Blogs',
                'description' => 'Manage blog posts',
                'sort_order' => 22,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Pages
            [
                'name' => 'pages',
                'display_name' => 'Pages',
                'description' => 'Manage static pages',
                'sort_order' => 23,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // FAQs
            [
                'name' => 'faqs',
                'display_name' => 'FAQs',
                'description' => 'Manage frequently asked questions',
                'sort_order' => 24,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Reviews
            [
                'name' => 'reviews',
                'display_name' => 'Reviews',
                'description' => 'Manage product reviews',
                'sort_order' => 25,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 2],
                ]
            ],
            // Shipping
            [
                'name' => 'shipping',
                'display_name' => 'Shipping',
                'description' => 'Manage shipping zones and methods',
                'sort_order' => 26,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 2],
                ]
            ],
            // Payments
            [
                'name' => 'payments',
                'display_name' => 'Payments',
                'description' => 'Manage payment gateways',
                'sort_order' => 27,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 2],
                ]
            ],
            // Settings
            [
                'name' => 'settings',
                'display_name' => 'Settings',
                'description' => 'Manage store settings',
                'sort_order' => 28,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 2],
                ]
            ],
            // Roles
            [
                'name' => 'roles',
                'display_name' => 'Roles & Permissions',
                'description' => 'Manage user roles and permissions',
                'sort_order' => 29,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Notifications
            [
                'name' => 'notifications',
                'display_name' => 'Notifications',
                'description' => 'Manage system notifications',
                'sort_order' => 30,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Pixels
            [
                'name' => 'pixels',
                'display_name' => 'Tracking Pixels',
                'description' => 'Manage tracking pixels for analytics',
                'sort_order' => 31,
                'is_system' => false,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 2],
                ]
            ],
            // Currencies
            [
                'name' => 'currencies',
                'display_name' => 'Currencies',
                'description' => 'Manage currency settings',
                'sort_order' => 32,
                'is_system' => false,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Languages
            [
                'name' => 'languages',
                'display_name' => 'Languages',
                'description' => 'Manage language settings',
                'sort_order' => 33,
                'is_system' => false,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // Wallet
            [
                'name' => 'wallet',
                'display_name' => 'Wallet',
                'description' => 'Manage wallet system',
                'sort_order' => 34,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 2],
                ]
            ],
            // Rewards
            [
                'name' => 'rewards',
                'display_name' => 'Rewards',
                'description' => 'Manage reward points system',
                'sort_order' => 35,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 2],
                ]
            ],
            // Support
            [
                'name' => 'support',
                'display_name' => 'Support Tickets',
                'description' => 'Manage customer support tickets',
                'sort_order' => 36,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            // POS
            [
                'name' => 'pos',
                'display_name' => 'Point of Sale',
                'description' => 'Access to POS terminal',
                'sort_order' => 37,
                'is_system' => true,
                'actions' => [
                    ['name' => 'use', 'display_name' => 'Use', 'sort_order' => 1],
                ]
            ],
            // Customer Account Modules
            [
                'name' => 'account_profile',
                'display_name' => 'Account Profile',
                'description' => 'Manage customer profile information',
                'sort_order' => 38,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 2],
                ]
            ],
            [
                'name' => 'account_addresses',
                'display_name' => 'Account Addresses',
                'description' => 'Manage customer shipping and billing addresses',
                'sort_order' => 39,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Create', 'sort_order' => 2],
                    ['name' => 'edit', 'display_name' => 'Edit', 'sort_order' => 3],
                    ['name' => 'delete', 'display_name' => 'Delete', 'sort_order' => 4],
                ]
            ],
            [
                'name' => 'account_orders',
                'display_name' => 'Account Orders',
                'description' => 'View customer order history',
                'sort_order' => 40,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'track', 'display_name' => 'Track Order', 'sort_order' => 2],
                    ['name' => 'cancel', 'display_name' => 'Cancel Order', 'sort_order' => 3],
                ]
            ],
            [
                'name' => 'account_notifications',
                'display_name' => 'Account Notifications',
                'description' => 'Manage customer notification preferences',
                'sort_order' => 41,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'edit', 'display_name' => 'Edit Preferences', 'sort_order' => 2],
                ]
            ],
            [
                'name' => 'account_wishlist',
                'display_name' => 'Account Wishlist',
                'description' => 'Manage customer wishlist',
                'sort_order' => 42,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'create', 'display_name' => 'Add to Wishlist', 'sort_order' => 2],
                    ['name' => 'delete', 'display_name' => 'Remove from Wishlist', 'sort_order' => 3],
                ]
            ],
            [
                'name' => 'account_downloads',
                'display_name' => 'Account Downloads',
                'description' => 'Access customer downloadable files',
                'sort_order' => 43,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'download', 'display_name' => 'Download', 'sort_order' => 2],
                ]
            ],
            [
                'name' => 'account_referrals',
                'display_name' => 'Account Referrals',
                'description' => 'Manage customer referral program',
                'sort_order' => 44,
                'is_system' => true,
                'actions' => [
                    ['name' => 'view', 'display_name' => 'View', 'sort_order' => 1],
                    ['name' => 'share', 'display_name' => 'Share Referral Link', 'sort_order' => 2],
                ]
            ],
        ];

        foreach ($modules as $moduleData) {
            $module = PermissionModule::create([
                'name' => $moduleData['name'],
                'display_name' => $moduleData['display_name'],
                'description' => $moduleData['description'],
                'sort_order' => $moduleData['sort_order'],
                'is_system' => $moduleData['is_system'],
                'is_active' => true,
            ]);

            foreach ($moduleData['actions'] as $actionData) {
                PermissionAction::create([
                    'module_id' => $module->id,
                    'name' => $actionData['name'],
                    'display_name' => $actionData['display_name'],
                    'sort_order' => $actionData['sort_order'],
                    'is_active' => true,
                ]);
            }
        }
    }
}
