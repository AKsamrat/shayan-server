<?php

namespace App\Services;

use App\Models\DeliveryPartner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PathaoCourierService
{
    protected $baseUrl;
    protected $clientId;
    protected $clientSecret;
    protected $username;
    protected $password;
    protected $merchantId;

    public function __construct()
    {
        $partner = DeliveryPartner::where('slug', 'pathao')->first();
        if ($partner && $partner->config) {
            $this->clientId = $partner->config['api_key'] ?? null;
            $this->clientSecret = $partner->config['api_secret'] ?? null;
            $this->username = $partner->config['username'] ?? null;
            $this->password = $partner->config['password'] ?? null;
            $this->merchantId = $partner->config['merchant_id'] ?? null;

            if (isset($partner->is_sandbox) && $partner->is_sandbox) {
                $this->baseUrl = 'https://courier-api-sandbox.pathao.com';
            } else {
                $this->baseUrl = 'https://api-hermes.pathao.com';
            }
        }
    }

    protected function getAccessToken()
    {
        // TODO: Store token in cache or database to reuse
        $response = Http::post("{$this->baseUrl}/aladdin/api/v1/issue-token", [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'password',
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if ($response->successful()) {
            return $response->json('access_token');
        }

        $errorBody = $response->json();
        $errorMessage = $errorBody['message'] ?? $errorBody['error_description'] ?? $response->body() ?? 'Unknown error';
        
        Log::error('Pathao Token Error', ['response' => $response->body()]);
        throw new \Exception('Failed to get Pathao access token: ' . $errorMessage);
    }

    public function getCities()
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/aladdin/api/v1/city-list");

        return $response->json();
    }

    public function getZones($cityId)
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/aladdin/api/v1/cities/{$cityId}/zone-list");

        return $response->json();
    }

    public function getAreas($zoneId)
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/aladdin/api/v1/zones/{$zoneId}/area-list");

        return $response->json();
    }

    public function getStores()
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/aladdin/api/v1/stores");

        return $response->json();
    }

    public function createStore($data)
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/aladdin/api/v1/stores", $data);

        return $response->json();
    }

    public function createOrder($data)
    {
        $token = $this->getAccessToken();
        
        $data['store_id'] = $data['store_id'] ?? $this->merchantId;

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/aladdin/api/v1/orders", $data);

        return $response->json();
    }

    public function calculatePrice($data)
    {
        $token = $this->getAccessToken();

        $data['store_id'] = $data['store_id'] ?? $this->merchantId;

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/aladdin/api/v1/merchant/price-plan", $data);

        return $response->json();
    }

    public function getOrderInfo($consignmentId)
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/aladdin/api/v1/orders/{$consignmentId}");

        return $response->json();
    }
}
