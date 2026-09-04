<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = [
            'dashboard.view',
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'orders.view',
            'orders.edit',
            'orders.delete',
            'orders.update_status',
            'returns.view',
            'returns.edit',
            'customers.view',
            'customers.edit',
            'staff.view',
            'staff.create',
            'staff.edit',
            'staff.delete',
            'delivery_boys.view',
            'delivery_boys.create',
            'delivery_boys.edit',
            'delivery_boys.delete',
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',
            'coupons.view',
            'coupons.create',
            'coupons.edit',
            'coupons.delete',
            'flash_sales.view',
            'flash_sales.create',
            'flash_sales.edit',
            'flash_sales.delete',
            'campaigns.view',
            'campaigns.create',
            'campaigns.edit',
            'campaigns.delete',
            'subscribers.view',
            'subscribers.create',
            'subscribers.edit',
            'subscribers.delete',
            'reports.view',
            'banners.view',
            'banners.create',
            'banners.edit',
            'banners.delete',
            'blogs.view',
            'blogs.create',
            'blogs.edit',
            'blogs.delete',
            'pages.view',
            'pages.create',
            'pages.edit',
            'pages.delete',
            'faqs.view',
            'faqs.create',
            'faqs.edit',
            'faqs.delete',
            'reviews.view',
            'reviews.delete',
            'shipping.view',
            'shipping.edit',
            'payments.view',
            'payments.edit',
            'settings.view',
            'settings.edit',
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'pos.use',
            // Supplier permissions
            'suppliers.view',
            'suppliers.create',
            'suppliers.edit',
            'suppliers.delete',
            'suppliers.manage_inventory',
            'supplier_purchases.view',
            'supplier_purchases.create',
            'supplier_purchases.edit',
            'supplier_purchases.delete',
            'supplier_purchases.manage_payments',
            'supplier_accounts.view',
            'supplier_accounts.create',
            'supplier_accounts.edit',
            'supplier_accounts.delete',
            'supplier_accounts.view_transactions',
        ];

        // Super Admin gets ALL permissions including role management
        $superAdminPermissions = $allPermissions;
        
        // Admin also gets ALL permissions including role management
        $adminPermissions = $allPermissions;

        $managerPermissions = [
            'dashboard.view',
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'orders.view',
            'orders.edit',
            'orders.update_status',
            'returns.view',
            'returns.edit',
            'customers.view',
            'customers.edit',
            'categories.view',
            'categories.edit',
            'coupons.view',
            'coupons.create',
            'coupons.edit',
            'reports.view',
            'banners.view',
            'banners.edit',
            'blogs.view',
            'blogs.edit',
            'faqs.view',
            'reviews.view',
            'shipping.view',
            'pos.use',
            // Supplier permissions for managers
            'suppliers.view',
            'suppliers.edit',
            'suppliers.manage_inventory',
            'supplier_purchases.view',
            'supplier_purchases.create',
            'supplier_accounts.view',
            'supplier_accounts.view_transactions',
        ];

        $editorPermissions = [
            'dashboard.view',
            'products.view',
            'products.edit',
            'orders.view',
            'customers.view',
            'categories.view',
            'categories.create',
            'categories.edit',
            'banners.view',
            'banners.create',
            'banners.edit',
            'banners.delete',
            'blogs.view',
            'blogs.create',
            'blogs.edit',
            'blogs.delete',
            'pages.view',
            'pages.create',
            'pages.edit',
            'pages.delete',
            'faqs.view',
            'faqs.create',
            'faqs.edit',
            'faqs.delete',
            'reviews.view',
            'reviews.delete',
        ];

        $staffPermissions = [
            'dashboard.view',
            'products.view',
            'orders.view',
            'orders.edit',
            'orders.update_status',
            'returns.view',
            'customers.view',
            'reviews.view',
            'pos.use',
        ];

        $vendorPermissions = [
            'dashboard.view',
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'orders.view',
            'orders.update_status',
            'payouts.manage',
            'shop.manage',
            'reviews.view',
            'categories.view',
            'brands.view',
        ];

        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Full access to all system features and settings. Highest privilege level.',
                'permissions' => $superAdminPermissions,
                'is_system' => true,
            ],
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Full administrative access including role and user management.',
                'permissions' => $adminPermissions,
                'is_system' => true,
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Manages products, orders, and customers.',
                'permissions' => $managerPermissions,
                'is_system' => false,
            ],
            [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Manages website content, blogs, and pages.',
                'permissions' => $editorPermissions,
                'is_system' => false,
            ],
            [
                'name' => 'Staff',
                'slug' => 'staff',
                'description' => 'Basic access for processing orders and viewing products.',
                'permissions' => $staffPermissions,
                'is_system' => false,
            ],
            [
                'name' => 'Vendor',
                'slug' => 'vendor',
                'description' => 'Manages their own shop, products, orders, and payouts.',
                'permissions' => $vendorPermissions,
                'is_system' => false,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(
                ['slug' => $roleData['slug']],
                $roleData
            );
        }

        // Note: Role assignments to users are handled in DatabaseSeeder::seedUsers()
    }
}
