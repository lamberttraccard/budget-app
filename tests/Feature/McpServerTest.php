<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    private function initializePayload(): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'id' => 1,
            'params' => [
                'protocolVersion' => '2024-11-05',
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
                'capabilities' => (object) [],
            ],
        ];
    }

    public function test_unauthenticated_mcp_request_is_rejected(): void
    {
        $this->postJson('/mcp', $this->initializePayload())
            ->assertUnauthorized();
    }

    public function test_authenticated_mcp_request_succeeds(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-mcp', ['mcp:read']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/mcp', $this->initializePayload())
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'Budget App')
            ->assertJsonPath('result.serverInfo.version', '1.0.0');
    }

    public function test_mcp_server_returns_protocol_capabilities(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-mcp', ['mcp:read']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/mcp', $this->initializePayload())
            ->assertOk()
            ->assertJsonPath('result.capabilities.tools.listChanged', false);
    }

    public function test_mcp_token_artisan_command_creates_token(): void
    {
        User::factory()->create();

        $this->artisan('mcp:token')
            ->expectsOutput('MCP API Token generated successfully!')
            ->assertSuccessful();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_mcp_token_command_fails_without_user(): void
    {
        $this->artisan('mcp:token')
            ->expectsOutput('No user found. Register a user first.')
            ->assertFailed();
    }

    public function test_mcp_token_command_respects_custom_name(): void
    {
        User::factory()->create();

        $this->artisan('mcp:token --name=custom-token')
            ->assertSuccessful();

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'custom-token',
        ]);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('expired', ['mcp:read'], now()->subDay());

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/mcp', $this->initializePayload())
            ->assertUnauthorized();
    }

    public function test_invalid_bearer_token_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer invalid-token-value')
            ->postJson('/mcp', $this->initializePayload())
            ->assertUnauthorized();
    }

    public function test_get_request_to_mcp_returns_method_not_allowed(): void
    {
        $this->getJson('/mcp')
            ->assertStatus(405);
    }
}
