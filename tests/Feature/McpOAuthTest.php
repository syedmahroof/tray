<?php

use App\Models\Branch;
use App\Models\Contact;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->configurePassportTestKeys();
    $this->withoutVite();
});

function registerMcpClient(): array
{
    return test()->postJson('/oauth/register', [
        'client_name' => 'Test Assistant',
        'redirect_uris' => ['https://client.example/callback'],
    ])->assertCreated()->assertJsonPath('token_endpoint_auth_method', 'none')->json();
}

function authorizeMcpClient(User $user, array $client, string $scope = 'mcp:use'): array
{
    $verifier = str_repeat('a', 64);
    $query = [
        'client_id' => $client['client_id'], 'redirect_uri' => $client['redirect_uris'][0],
        'response_type' => 'code', 'scope' => $scope, 'state' => 'test-state',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ];
    test()->actingAs($user, 'web')->get('/oauth/authorize?'.http_build_query($query))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/Authorize')->where('client.name', 'Test Assistant')->where('email', $user->email));
    $response = test()->post('/oauth/authorize', [
        'client_id' => $client['client_id'], 'auth_token' => session('authToken'), 'state' => 'test-state',
    ])->assertRedirect();
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $callback);
    expect($callback['state'])->toBe('test-state');

    return [
        'grant_type' => 'authorization_code', 'client_id' => $client['client_id'],
        'redirect_uri' => $client['redirect_uris'][0], 'code' => $callback['code'], 'code_verifier' => $verifier,
    ];
}

test('OAuth discovery advertises authorization token registration and PKCE endpoints', function () {
    $this->getJson('/.well-known/oauth-protected-resource/api/mcp')->assertOk()
        ->assertJsonPath('scopes_supported', ['mcp:use']);
    $this->getJson('/.well-known/oauth-authorization-server')->assertOk()
        ->assertJsonPath('code_challenge_methods_supported', ['S256'])
        ->assertJsonStructure(['authorization_endpoint', 'token_endpoint', 'registration_endpoint']);
    $response = $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    expect($response->headers->get('WWW-Authenticate'))->toContain('/.well-known/oauth-protected-resource/api/mcp');
});

test('OAuth PKCE grants access with user permissions and branch isolation and supports refresh and revocation', function () {
    $user = User::factory()->create(['branch_id' => Branch::factory()->create()->id]);
    $user->givePermissionTo('contacts.view');
    Contact::factory()->create(['branch_id' => $user->branch_id]);
    Contact::factory()->create();
    $client = registerMcpClient();
    $grant = authorizeMcpClient($user, $client);
    $tokens = $this->postJson('/oauth/token', $grant)->assertOk()->assertJsonStructure(['access_token', 'refresh_token'])->json();
    Auth::forgetGuards();
    $this->withToken($tokens['access_token']);
    $response = $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'list_contacts']])->assertOk()->assertJsonPath('result.isError', false);
    expect(json_decode($response->json('result.content.0.text'), true)['total'])->toBe(1);
    $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'create_quotation']])->assertOk()->assertJsonPath('result.isError', true);
    $refreshed = $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $client['client_id'], 'refresh_token' => $tokens['refresh_token']])->assertOk()->json();
    Token::query()->update(['revoked' => true]);
    Auth::forgetGuards();
    $this->withToken($refreshed['access_token'])->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list'])->assertUnauthorized();
});

test('OAuth rejects a wrong PKCE verifier', function () {
    $grant = authorizeMcpClient(User::factory()->create(), registerMcpClient());
    $grant['code_verifier'] = str_repeat('b', 64);
    $this->postJson('/oauth/token', $grant)->assertClientError();
    expect(Token::count())->toBe(0);
});

test('OAuth requires the MCP scope', function () {
    $client = registerMcpClient();
    $grant = authorizeMcpClient(User::factory()->create(), $client, '');
    $tokens = $this->postJson('/oauth/token', $grant)->assertOk()->json();
    Auth::forgetGuards();
    $this->withToken($tokens['access_token'])->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertForbidden();
});

test('OAuth requires login and allows rejecting a client', function () {
    $client = registerMcpClient();
    $query = ['client_id' => $client['client_id'], 'redirect_uri' => $client['redirect_uris'][0], 'response_type' => 'code', 'scope' => 'mcp:use', 'state' => 'denied-state', 'code_challenge' => str_repeat('a', 43), 'code_challenge_method' => 'S256'];
    $url = '/oauth/authorize?'.http_build_query($query);
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(), 'web')->get($url)->assertOk();
    $response = $this->delete('/oauth/authorize', ['client_id' => $client['client_id'], 'auth_token' => session('authToken'), 'state' => 'denied-state'])->assertRedirect();
    expect($response->headers->get('Location'))->toContain('error=access_denied', 'state=denied-state');
    expect(Token::count())->toBe(0);
});

test('existing Sanctum API login and logout continue working', function () {
    $user = User::factory()->create();
    $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'test'])->assertOk()->json('token');
    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('user.id', $user->id);
    $this->postJson('/api/logout')->assertOk();
    expect($user->tokens()->count())->toBe(0);
});

test('the MCP scope is registered independently of route loading', function () {
    Passport::$scopes = [];
    app(AppServiceProvider::class, ['app' => app()])->boot();
    expect(Passport::hasScope('mcp:use'))->toBeTrue();
});
