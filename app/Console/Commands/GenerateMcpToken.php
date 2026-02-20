<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GenerateMcpToken extends Command
{
    protected $signature = 'mcp:token {--name=claude : Token name}';

    protected $description = 'Generate a Sanctum API token for MCP access';

    public function handle(): int
    {
        $user = User::first();

        if (! $user) {
            $this->error('No user found. Register a user first.');

            return self::FAILURE;
        }

        $token = $user->createToken(
            $this->option('name'),
            ['mcp:read'],
        );

        $this->info('MCP API Token generated successfully!');
        $this->newLine();
        $this->line('Token: '.$token->plainTextToken);
        $this->newLine();
        $this->info('Add this token to your Claude MCP configuration as a Bearer token.');
        $this->warn('This token will only be shown once. Store it safely.');

        return self::SUCCESS;
    }
}
