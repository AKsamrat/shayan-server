<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // ==================== CUSTOMER PROFILE TESTS ====================

    public function test_customer_can_get_own_profile()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $token = $customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->get('/api/customer/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ]
            ]);
    }

    public function test_customer_can_update_own_profile()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $token = $customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->put('/api/customer/profile', [
                'name' => 'Updated Name',
                'phone' => '+1234567890',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Updated Name',
                'phone' => '+1234567890',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'name' => 'Updated Name',
            'phone' => '+1234567890',
        ]);
    }

    public function test_customer_can_upload_avatar()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $token = $customer->createToken('test-token')->plainTextToken;

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->post('/api/customer/profile/image', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'avatar_url',
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'avatar' => 'avatars/' . $file->hashName(),
        ]);
    }

    public function test_customer_can_remove_avatar()
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'avatar' => 'avatars/old-avatar.jpg',
        ]);
        $token = $customer->createToken('test-token')->plainTextToken;

        // Fake storage to simulate file existence
        Storage::disk('public')->put('avatars/old-avatar.jpg', 'dummy content');

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->delete('/api/customer/profile/image');

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'avatar' => null,
        ]);

        Storage::disk('public')->assertMissing('avatars/old-avatar.jpg');
    }

    // ==================== ADMIN PROFILE TESTS ====================

    public function test_admin_can_get_own_profile()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->get('/api/admin/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'permissions',
                ]
            ]);
    }

    public function test_admin_can_update_own_profile()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->put('/api/admin/profile', [
                'name' => 'Updated Admin Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Admin Name']);
    }

    public function test_admin_can_upload_avatar()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $file = UploadedFile::fake()->image('admin-avatar.png');

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->post('/api/admin/profile/image', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'avatar' => 'avatars/' . $file->hashName(),
        ]);
    }

    // ==================== ADMIN MANAGE CUSTOMER PROFILES ====================

    public function test_admin_can_get_customer_profile()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->get("/api/admin/customers/{$customer->id}/profile");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $customer->id]);
    }

    public function test_admin_can_update_customer_profile()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->put("/api/admin/customers/{$customer->id}/profile", [
                'name' => 'Updated Customer Name',
                'is_active' => false,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'name' => 'Updated Customer Name',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_upload_customer_avatar()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $file = UploadedFile::fake()->image('customer-avatar.jpg');

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->post("/api/admin/customers/{$customer->id}/profile/image", [
                'avatar' => $file,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'avatar' => 'avatars/' . $file->hashName(),
        ]);
    }

    public function test_admin_can_remove_customer_avatar()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create([
            'role' => 'customer',
            'avatar' => 'avatars/customer-avatar.jpg',
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;

        Storage::disk('public')->put('avatars/customer-avatar.jpg', 'dummy content');

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->delete("/api/admin/customers/{$customer->id}/profile/image");

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'avatar' => null,
        ]);
    }

    // ==================== ADMIN MANAGE STAFF PROFILES ====================

    public function test_admin_can_get_staff_profile()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->get("/api/admin/staff/{$staff->id}/profile");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $staff->id]);
    }

    public function test_admin_can_update_staff_profile()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->put("/api/admin/staff/{$staff->id}/profile", [
                'name' => 'Updated Staff Name',
                'role_id' => 1,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'name' => 'Updated Staff Name',
        ]);
    }

    public function test_admin_can_upload_staff_avatar()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $file = UploadedFile::fake()->image('staff-avatar.png');

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->post("/api/admin/staff/{$staff->id}/profile/image", [
                'avatar' => $file,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'avatar' => 'avatars/' . $file->hashName(),
        ]);
    }

    public function test_admin_can_remove_staff_avatar()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create([
            'role' => 'staff',
            'avatar' => 'avatars/staff-avatar.jpg',
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;

        Storage::disk('public')->put('avatars/staff-avatar.jpg', 'dummy content');

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->delete("/api/admin/staff/{$staff->id}/profile/image");

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'avatar' => null,
        ]);
    }

    // ==================== ERROR HANDLING TESTS ====================

    public function test_get_nonexistent_customer_profile_returns_404()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->get('/api/admin/customers/99999/profile');

        $response->assertStatus(404);
    }

    public function test_get_nonexistent_staff_profile_returns_404()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->get('/api/admin/staff/99999/profile');

        $response->assertStatus(404);
    }

    public function test_upload_avatar_with_invalid_file_returns_422()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $token = $customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->post('/api/customer/profile/image', [
                'avatar' => 'not-a-file',
            ]);

        $response->assertStatus(422);
    }

    public function test_upload_avatar_with_file_too_large_returns_422()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $token = $customer->createToken('test-token')->plainTextToken;

        $file = UploadedFile::fake()->create('large-file.png', 3000); // 3MB

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->post('/api/customer/profile/image', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422);
    }
}
