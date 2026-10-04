<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAndAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed standard roles & permissions
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_audit_warehouse_role_cannot_post_sales(): void
    {
        $warehouseUser = User::factory()->create(['role' => 'WAREHOUSE_MANAGER']);
        $this->actingAs($warehouseUser, 'sanctum')->postJson('/api/sales', [])->assertForbidden();
        $this->assertDatabaseCount('sales', 0);
    }

    /**
     * TEST 1: Anonymous users are redirected to /login on all protected web routes.
     */
    public function test_anonymous_users_are_redirected_to_login(): void
    {
        $protectedRoutes = [
            '/dashboard',
            '/savdo',
            '/sotuv',
            '/ombor',
            '/qarzdorliklar',
            '/hisobotlar',
            '/kassa',
            '/admin',
        ];

        foreach ($protectedRoutes as $uri) {
            $response = $this->get($uri);
            $response->assertStatus(302);
            $response->assertRedirect('/login');
        }
    }

    /**
     * TEST 2: Anonymous users receive 401 on protected API routes.
     */
    public function test_anonymous_users_receive_401_on_api(): void
    {
        $protectedApiRoutes = [
            ['GET', '/api/products'],
            ['POST', '/api/inward'],
            ['POST', '/api/sales'],
            ['POST', '/api/calculator'],
            ['GET', '/api/auth/me'],
            ['POST', '/api/auth/logout'],
        ];

        foreach ($protectedApiRoutes as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertStatus(401);
        }
    }

    /**
     * TEST 3: Valid web login authenticates and redirects to dashboard.
     */
    public function test_valid_web_login_authenticates_user(): void
    {
        $user = User::factory()->create([
            'email' => 'seller@aquaoptom.uz',
            'password' => Hash::make('Secret123!'),
            'role' => 'SALES_MANAGER',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'seller@aquaoptom.uz',
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    /**
     * TEST 4: Invalid web login fails.
     */
    public function test_invalid_web_login_fails(): void
    {
        $user = User::factory()->create([
            'email' => 'seller@aquaoptom.uz',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->post('/login', [
            'email' => 'seller@aquaoptom.uz',
            'password' => 'WrongPassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * TEST 5: Web logout terminates session.
     */
    public function test_web_logout_terminates_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * TEST 6: Blocked user cannot log in or make API calls.
     */
    public function test_blocked_user_cannot_login_web_or_api(): void
    {
        $blockedUser = User::factory()->blocked()->create([
            'email' => 'blocked@aquaoptom.uz',
            'password' => Hash::make('Secret123!'),
        ]);

        // Web login attempt
        $webResponse = $this->post('/login', [
            'email' => 'blocked@aquaoptom.uz',
            'password' => 'Secret123!',
        ]);
        $webResponse->assertSessionHasErrors('email');
        $this->assertGuest();

        // API login attempt
        $apiResponse = $this->postJson('/api/auth/login', [
            'email' => 'blocked@aquaoptom.uz',
            'password' => 'Secret123!',
        ]);
        $apiResponse->assertStatus(403)
            ->assertJsonPath('status', 'error');

        // Existing token on blocked user
        Sanctum::actingAs($blockedUser);
        $meResponse = $this->getJson('/api/auth/me');
        $meResponse->assertStatus(403);
    }

    /**
     * TEST 7: API login, me and logout flow via Sanctum.
     */
    public function test_api_login_returns_token_and_can_logout(): void
    {
        $owner = User::factory()->owner()->create([
            'email' => 'boss@aquaoptom.uz',
            'password' => Hash::make('SuperOwnerPass123'),
        ]);

        // Login
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => 'boss@aquaoptom.uz',
            'password' => 'SuperOwnerPass123',
            'device_name' => 'test-device',
        ]);

        $loginRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'token', 'user' => ['id', 'name', 'role', 'permissions']]);

        $token = $loginRes->json('token');
        $this->assertNotEmpty($token);

        // Fetch profile
        $meRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $meRes->assertStatus(200)
            ->assertJsonPath('data.email', 'boss@aquaoptom.uz')
            ->assertJsonPath('data.role', 'OWNER');

        // Logout
        $logoutRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $logoutRes->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    /**
     * TEST 8: Role-based authorization for all 8 menus.
     * Owner has access to everything.
     * Seller receives 403 on Admin, Kassa, Reports.
     */
    public function test_role_based_access_to_menus(): void
    {
        $owner = User::factory()->owner()->create();
        $seller = User::factory()->create(['role' => 'SALES_MANAGER']);

        // 1. Owner can access all 8 sections
        $this->actingAs($owner)->get('/dashboard')->assertOk();
        $this->actingAs($owner)->get('/savdo')->assertOk();
        $this->actingAs($owner)->get('/sotuv')->assertOk();
        $this->actingAs($owner)->get('/ombor')->assertOk();
        $this->actingAs($owner)->get('/qarzdorliklar')->assertOk();
        $this->actingAs($owner)->get('/hisobotlar')->assertOk();
        $this->actingAs($owner)->get('/kassa')->assertOk();
        $this->actingAs($owner)->get('/admin')->assertOk();

        // 2. Seller can access sales, inventory, debts, dashboard
        $this->actingAs($seller)->get('/dashboard')->assertOk();
        $this->actingAs($seller)->get('/savdo')->assertOk();
        $this->actingAs($seller)->get('/sotuv')->assertOk();
        $this->actingAs($seller)->get('/ombor')->assertOk();
        $this->actingAs($seller)->get('/qarzdorliklar')->assertOk();

        // 3. Seller CANNOT access Admin, Kassa, Hisobotlar (403 Forbidden)
        $this->actingAs($seller)->get('/admin')->assertStatus(403);
        $this->actingAs($seller)->get('/kassa')->assertStatus(403);
        $this->actingAs($seller)->get('/hisobotlar')->assertStatus(403);
    }

    /**
     * TEST 9: Cost price hiding in API for unauthorized users.
     */
    public function test_cost_price_hiding_in_api_for_unauthorized_users(): void
    {
        Warehouse::create(['name' => 'Asosiy Ombor', 'is_default' => true]);
        $vol = Volume::create(['name' => '1.0 L', 'value_ml' => 1000]);
        $product = Product::create([
            'name' => 'Coca-Cola',
            'normalized_name' => 'coca-cola',
            'code' => 'CC-001',
            'status' => 'active',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'volume_id' => $vol->id,
            'sku' => 'CC-1000',
            'default_sale_price' => 12000,
            'status' => 'active',
        ]);

        $variant->balance()->create([
            'warehouse_id' => 1,
            'quantity' => 100,
            'average_cost' => 8500, // Cost price
        ]);

        $owner = User::factory()->owner()->create();
        $seller = User::factory()->create(['role' => 'SALES_MANAGER']); // No view_cost_price

        // 1. Owner sees cost_price 8500
        Sanctum::actingAs($owner);
        $ownerRes = $this->getJson('/api/products');
        $ownerRes->assertStatus(200);
        $this->assertEquals(8500.0, $ownerRes->json('data.0.variants.0.cost_price'));
        $this->assertEquals(8500, $ownerRes->json('data.0.variants.0.average_cost'));

        // 2. Seller sees NULL cost_price (MASKED on server)
        Sanctum::actingAs($seller);
        $sellerRes = $this->getJson('/api/products');
        $sellerRes->assertStatus(200);
        $this->assertNull($sellerRes->json('data.0.variants.0.cost_price'));
        $this->assertNull($sellerRes->json('data.0.variants.0.average_cost'));
        // But retail price remains visible
        $this->assertEquals(12000.0, $sellerRes->json('data.0.variants.0.retail_price'));
    }

    /**
     * TEST 10: app:bootstrap-owner command creates owner without hardcoded default password.
     */
    public function test_bootstrap_owner_command_creates_owner(): void
    {
        $exitCode = Artisan::call('app:bootstrap-owner', [
            '--name' => 'Rustam Egasi',
            '--email' => 'rustam@aquaoptom.uz',
            '--phone' => '+998901234567',
            '--password' => 'GeneratedSafePass99!',
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $created = User::where('email', 'rustam@aquaoptom.uz')->first();
        $this->assertNotNull($created);
        $this->assertEquals('OWNER', $created->role);
        $this->assertTrue($created->isOwner());
        $this->assertTrue(Hash::check('GeneratedSafePass99!', $created->password));
    }

    /**
     * TEST 11: Responsive layout rendering (desktop sidebar, mobile drawer, 360px viewport meta).
     */
    public function test_responsive_layout_rendering(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->get('/dashboard');

        $response->assertStatus(200);
        // Responsive viewport meta tag
        $response->assertSee('name="viewport" content="width=device-width, initial-scale=1.0', false);
        // Mobile drawer elements
        $response->assertSee('id="sidebar"', false);
        $response->assertSee('id="mobile-overlay"', false);
        $response->assertSee('toggleMobileSidebar', false);
        // 8 Navigation menu links
        $response->assertSee('Dashboard');
        $response->assertSee('Savdo');
        $response->assertSee('Sotuv');
        $response->assertSee('Ombor');
        $response->assertSee('Qarzdorliklar');
        $response->assertSee('Hisobotlar');
        $response->assertSee('Kassa va xarajatlar');
        $response->assertSee('Admin panel');
    }
}
