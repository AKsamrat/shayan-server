<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DeliveryPartner;

class DeliveryPartnerSeeder extends Seeder
{
    public function run(): void
    {
        $partners = [
            [
                'name' => 'Pathao',
                'slug' => 'pathao',
                'description' => 'Pathao Courier - fast and reliable delivery across Bangladesh.',
                'config' => [
                    'api_key' => '',
                    'api_secret' => '',
                    'merchant_id' => '',
                    'base_url' => 'https://merchant-api-live.pathao.com',
                ],
                'status' => 'inactive',
                'is_sandbox' => true,
                'supported_areas' => ['Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi', 'Khulna', 'Barishal', 'Rangpur', 'Mymensingh'],
                'supported_service_types' => ['standard', 'express', 'same_day'],
                'cod_enabled' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'SteadFast',
                'slug' => 'steadfast',
                'description' => 'SteadFast Courier - e-commerce delivery specialist.',
                'config' => [
                    'api_key' => '',
                    'api_secret' => '',
                    'base_url' => 'https://portal.steadfast.com.bd/api/v1',
                ],
                'status' => 'inactive',
                'is_sandbox' => true,
                'supported_areas' => ['Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi', 'Khulna', 'Barishal', 'Rangpur', 'Mymensingh'],
                'supported_service_types' => ['standard', 'express'],
                'cod_enabled' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Paperfly',
                'slug' => 'paperfly',
                'description' => 'Paperfly Courier - nationwide delivery network.',
                'config' => [
                    'api_key' => '',
                    'api_secret' => '',
                    'base_url' => 'https://merchantapi.paperfly.com.bd/api/v1',
                ],
                'status' => 'inactive',
                'is_sandbox' => true,
                'supported_areas' => ['Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi', 'Khulna', 'Barishal', 'Rangpur', 'Mymensingh'],
                'supported_service_types' => ['standard', 'express', 'same_day'],
                'cod_enabled' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Sundarban',
                'slug' => 'sundarban',
                'description' => 'Sundarban Courier Service - trusted nationwide delivery.',
                'config' => [
                    'api_key' => '',
                    'api_secret' => '',
                    'base_url' => '',
                ],
                'status' => 'inactive',
                'is_sandbox' => true,
                'supported_areas' => ['Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi', 'Khulna', 'Barishal', 'Rangpur', 'Mymensingh'],
                'supported_service_types' => ['standard', 'express'],
                'cod_enabled' => true,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'eCourier',
                'slug' => 'ecourier',
                'description' => 'eCourier - digital logistics platform.',
                'config' => [
                    'api_key' => '',
                    'api_secret' => '',
                    'base_url' => 'https://api.ecourier.com.bd',
                ],
                'status' => 'inactive',
                'is_sandbox' => true,
                'supported_areas' => ['Dhaka', 'Chittagong'],
                'supported_service_types' => ['standard', 'express'],
                'cod_enabled' => true,
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($partners as $partner) {
            DeliveryPartner::updateOrCreate(
                ['slug' => $partner['slug']],
                $partner
            );
        }
    }
}
