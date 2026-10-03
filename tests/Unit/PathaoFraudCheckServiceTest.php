<?php

namespace Tests\Unit;

use App\Services\Courier\PathaoFraudCheckService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PathaoFraudCheckServiceTest extends TestCase
{
    protected PathaoFraudCheckService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'fraud_check.pathao.username' => 'test_merchant_user',
            'fraud_check.pathao.password' => 'test_merchant_pass',
        ]);
        $this->service = new PathaoFraudCheckService();
    }

    /** @test */
    public function it_normalizes_bangladeshi_phone_numbers_correctly()
    {
        $this->assertEquals('01712345678', $this->service->normalizePhone('01712345678'));
        $this->assertEquals('01712345678', $this->service->normalizePhone('+8801712345678'));
        $this->assertEquals('01712345678', $this->service->normalizePhone('8801712345678'));
        $this->assertEquals('01712345678', $this->service->normalizePhone('017-1234-5678'));
        $this->assertEquals('01712345678', $this->service->normalizePhone(' 017 1234 5678 '));
        $this->assertEquals('01812345678', $this->service->normalizePhone('1812345678'));

        // Invalid formats
        $this->assertNull($this->service->normalizePhone('01212345678')); // Invalid operator code
        $this->assertNull($this->service->normalizePhone('12345'));
        $this->assertNull($this->service->normalizePhone('abcdefghijk'));
        $this->assertNull($this->service->normalizePhone('+12025550143'));
    }

    /** @test */
    public function it_evaluates_risk_level_based_on_configurable_rules()
    {
        // 0 orders or unknown
        $this->assertEquals('unknown', $this->service->evaluateRiskLevel(0, 0, null));
        $this->assertEquals('unknown', $this->service->evaluateRiskLevel(null, null, null));

        // Less than minimum orders (default 3)
        $this->assertEquals('low', $this->service->evaluateRiskLevel(2, 0, 100.0));
        $this->assertEquals('medium', $this->service->evaluateRiskLevel(2, 1, 50.0));

        // >= 3 orders with high cancellation rate (>= 50%)
        $this->assertEquals('high', $this->service->evaluateRiskLevel(10, 6, 40.0)); // 60% cancel rate

        // >= 3 orders with medium cancellation rate (25% - 49%)
        $this->assertEquals('medium', $this->service->evaluateRiskLevel(10, 3, 70.0)); // 30% cancel rate

        // >= 3 orders with low cancellation rate (< 25%)
        $this->assertEquals('low', $this->service->evaluateRiskLevel(20, 2, 90.0)); // 10% cancel rate
    }

    /** @test */
    public function it_evaluates_customer_type_correctly()
    {
        $this->assertEquals('new_customer', $this->service->evaluateCustomerType(0, null));
        $this->assertEquals('excellent_customer', $this->service->evaluateCustomerType(20, 90.0));
        $this->assertEquals('good_customer', $this->service->evaluateCustomerType(10, 75.0));
        $this->assertEquals('moderate_customer', $this->service->evaluateCustomerType(10, 55.0));
        $this->assertEquals('risky_customer', $this->service->evaluateCustomerType(10, 30.0));
    }

    /** @test */
    public function it_returns_invalid_phone_error_for_invalid_numbers()
    {
        $response = $this->service->checkCustomer('12345');

        $this->assertFalse($response['success']);
        $this->assertEquals('INVALID_PHONE', $response['error']);
    }

    /** @test */
    public function it_checks_customer_history_successfully_and_returns_clean_response()
    {
        Http::fake([
            'https://merchant.pathao.com/api/v1/login' => Http::response([
                'access_token' => 'mock_merchant_token_123',
                'token_type'   => 'bearer',
                'expires_in'   => 86400,
            ], 200),

            'https://merchant.pathao.com/api/v1/user/success' => Http::response([
                'data' => [
                    'customer' => [
                        'successful_delivery' => 17,
                        'total_delivery'      => 20,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->service->checkCustomer('01712345678');

        $this->assertTrue($response['success']);
        $this->assertEquals('01712345678', $response['phone']);
        $this->assertEquals('pathao', $response['courier']);
        $this->assertEquals(20, $response['total_orders']);
        $this->assertEquals(17, $response['successful_orders']);
        $this->assertEquals(3, $response['cancelled_orders']);
        $this->assertEquals(85.0, $response['success_rate']);
        $this->assertEquals('excellent_customer', $response['customer_type']);
        $this->assertEquals('low', $response['risk_level']);
        $this->assertTrue($response['raw_available']);

        // Security check: Verify sensitive credentials never leak
        $this->assertArrayNotHasKey('password', $response);
        $this->assertArrayNotHasKey('client_secret', $response);
        $this->assertArrayNotHasKey('access_token', $response);
    }

    /** @test */
    public function it_handles_customer_with_high_cancellations()
    {
        Http::fake([
            'https://merchant.pathao.com/api/v1/login' => Http::response([
                'access_token' => 'mock_merchant_token_123',
                'expires_in'   => 86400,
            ], 200),

            'https://merchant.pathao.com/api/v1/user/success' => Http::response([
                'data' => [
                    'customer' => [
                        'successful_delivery' => 3,
                        'total_delivery'      => 10,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->service->checkCustomer('01812345678');

        $this->assertTrue($response['success']);
        $this->assertEquals(10, $response['total_orders']);
        $this->assertEquals(3, $response['successful_orders']);
        $this->assertEquals(7, $response['cancelled_orders']);
        $this->assertEquals(30.0, $response['success_rate']);
        $this->assertEquals('high', $response['risk_level']);
        $this->assertEquals('risky_customer', $response['customer_type']);
    }

    /** @test */
    public function it_handles_new_customer_with_no_history()
    {
        Http::fake([
            'https://merchant.pathao.com/api/v1/login' => Http::response([
                'access_token' => 'mock_merchant_token_123',
                'expires_in'   => 86400,
            ], 200),

            'https://merchant.pathao.com/api/v1/user/success' => Http::response([
                'data' => [
                    'customer' => [
                        'successful_delivery' => 0,
                        'total_delivery'      => 0,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->service->checkCustomer('01912345678');

        $this->assertTrue($response['success']);
        $this->assertEquals(0, $response['total_orders']);
        $this->assertEquals(0, $response['successful_orders']);
        $this->assertEquals(0, $response['cancelled_orders']);
        $this->assertEquals('new_customer', $response['customer_type']);
        $this->assertEquals('unknown', $response['risk_level']);
    }

    /** @test */
    public function it_caches_customer_history_result()
    {
        Http::fake([
            'https://merchant.pathao.com/api/v1/login' => Http::response([
                'access_token' => 'mock_token',
                'expires_in'   => 3600,
            ], 200),

            'https://merchant.pathao.com/api/v1/user/success' => Http::response([
                'data' => [
                    'customer' => [
                        'successful_delivery' => 8,
                        'total_delivery'      => 10,
                    ],
                ],
            ], 200),
        ]);

        // First call
        $first = $this->service->checkCustomer('01712345678');
        $this->assertTrue($first['success']);

        // Second call should return from cache
        $second = $this->service->checkCustomer('01712345678');
        $this->assertTrue($second['success']);
        $this->assertTrue($second['from_cache'] ?? false);
    }

    /** @test */
    public function it_handles_pathao_rate_limiting_429()
    {
        Http::fake([
            'https://merchant.pathao.com/api/v1/login' => Http::response([
                'access_token' => 'mock_token',
            ], 200),

            'https://merchant.pathao.com/api/v1/user/success' => Http::response([
                'message' => 'Too Many Requests',
            ], 429),
        ]);

        $response = $this->service->checkCustomer('01712345678');

        $this->assertFalse($response['success']);
        $this->assertEquals('PATHAO_RATE_LIMITED', $response['error']);
    }

    /** @test */
    public function it_handles_pathao_server_error_500()
    {
        Http::fake([
            'https://merchant.pathao.com/api/v1/login' => Http::response([
                'access_token' => 'mock_token',
            ], 200),

            'https://merchant.pathao.com/api/v1/user/success' => Http::response([
                'message' => 'Internal Server Error',
            ], 500),
        ]);

        $response = $this->service->checkCustomer('01712345678');

        $this->assertFalse($response['success']);
        $this->assertEquals('PATHAO_UNAVAILABLE', $response['error']);
    }

    /** @test */
    public function it_handles_pathao_v2_response_with_customer_rating()
    {
        Http::fake([
            'https://merchant.pathao.com/api/v1/login' => Http::response([
                'access_token' => 'mock_token',
                'expires_in'   => 3600,
            ], 200),

            'https://merchant.pathao.com/api/v1/user/success' => Http::response([
                'message' => 'user success rate',
                'type'    => 'success',
                'code'    => 200,
                'data'    => [
                    'version'         => 'v2',
                    'address_book'    => [],
                    'show_count'      => false,
                    'customer_rating' => 'excellent_customer',
                ],
            ], 200),
        ]);

        $response = $this->service->checkCustomer('01615489252');

        $this->assertTrue($response['success']);
        $this->assertEquals('01615489252', $response['phone']);
        $this->assertEquals('pathao', $response['courier']);
        $this->assertEquals('excellent_customer', $response['customer_type']);
        $this->assertEquals('low', $response['risk_level']);
        $this->assertEquals('excellent_customer', $response['customer_rating']);
        $this->assertFalse($response['show_count']);
        $this->assertNull($response['total_orders']);
        $this->assertStringContainsString('Excellent Customer', $response['message']);
    }
}
