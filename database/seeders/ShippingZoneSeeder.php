<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ShippingZone;
use App\Models\ShippingMethod;

class ShippingZoneSeeder extends Seeder
{
    public function run(): void
    {
        $dhakaZone = ShippingZone::updateOrCreate(
            ['name' => 'Dhaka City'],
            [
                'description' => 'Delivery within Dhaka city',
                'countries' => ['Bangladesh'],
                'states' => ['Dhaka Division'],
                'cities' => ['Dhaka', 'Gazipur', 'Narayanganj', 'Savar'],
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['zone_id' => $dhakaZone->id, 'name' => 'Standard Delivery'],
            [
                'description' => '2-3 business days delivery',
                'rate_type' => 'flat',
                'base_rate' => 60,
                'estimated_days' => '2-3 business days',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['zone_id' => $dhakaZone->id, 'name' => 'Express Delivery'],
            [
                'description' => 'Same day or next day delivery',
                'rate_type' => 'flat',
                'base_rate' => 120,
                'estimated_days' => 'Same day / Next day',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['zone_id' => $dhakaZone->id, 'name' => 'Free Delivery'],
            [
                'description' => 'Free delivery on orders over ৳2000',
                'rate_type' => 'price_based',
                'base_rate' => 0,
                'min_order_amount' => 2000,
                'estimated_days' => '3-5 business days',
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        $outsideZone = ShippingZone::updateOrCreate(
            ['name' => 'Outside Dhaka'],
            [
                'description' => 'Delivery outside Dhaka city',
                'countries' => ['Bangladesh'],
                'states' => ['Chittagong Division', 'Sylhet Division', 'Rajshahi Division', 'Khulna Division', 'Barishal Division', 'Rangpur Division', 'Mymensingh Division'],
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['zone_id' => $outsideZone->id, 'name' => 'Standard Delivery'],
            [
                'description' => '3-5 business days delivery',
                'rate_type' => 'flat',
                'base_rate' => 120,
                'estimated_days' => '3-5 business days',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['zone_id' => $outsideZone->id, 'name' => 'Express Delivery'],
            [
                'description' => '1-2 business days delivery',
                'rate_type' => 'flat',
                'base_rate' => 180,
                'estimated_days' => '1-2 business days',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['zone_id' => $outsideZone->id, 'name' => 'Weight-Based Shipping'],
            [
                'description' => 'Shipping rate based on package weight',
                'rate_type' => 'weight_based',
                'base_rate' => 80,
                'per_kg_rate' => 20,
                'min_weight' => 0.5,
                'estimated_days' => '3-7 business days',
                'is_active' => true,
                'sort_order' => 3,
            ]
        );
    }
}
