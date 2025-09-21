<?php

namespace App\Console\Commands;

use App\Models\SuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SetupSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'super-admin:setup
                           {--email= : Super admin email}
                           {--password= : Super admin password}
                           {--name= : Super admin name}
                           {--force : Force creation even if super admin exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup Super Admin user and configure environment';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Configurando Super Admin...');
        $this->newLine();

        // Check if super admin is enabled
        if (!config('super-admin.enabled', false)) {
            $this->warn('⚠️  Super Admin não está habilitado no .env');

            if ($this->confirm('Deseja habilitar Super Admin agora?')) {
                $this->call('super-admin:env-setup');
            } else {
                $this->error('Super Admin precisa estar habilitado. Execute: php artisan super-admin:env-setup');
                return 1;
            }
        }

        // Check if super admin already exists
        $existingSuperAdmin = SuperAdmin::first();
        if ($existingSuperAdmin && !$this->option('force')) {
            $this->warn('Super Admin já existe:');
            $this->table(
                ['ID', 'Nome', 'Email', 'Status', 'Criado em'],
                [[
                    $existingSuperAdmin->id,
                    $existingSuperAdmin->name,
                    $existingSuperAdmin->email,
                    $existingSuperAdmin->status === 'active' ? 'Ativo' : 'Inativo',
                    $existingSuperAdmin->created_at->format('d/m/Y H:i:s')
                ]]
            );

            if (!$this->confirm('Deseja criar um novo Super Admin mesmo assim?')) {
                $this->info('✅ Setup cancelado. Use --force para forçar a criação.');
                return 0;
            }
        }

        // Get super admin details
        $email = $this->option('email') ?: $this->ask('Email do Super Admin');
        $name = $this->option('name') ?: $this->ask('Nome do Super Admin');
        $password = $this->option('password') ?: $this->secret('Senha do Super Admin');

        if (empty($email) || empty($name) || empty($password)) {
            $this->error('Email, nome e senha são obrigatórios!');
            return 1;
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email inválido!');
            return 1;
        }

        // Check if email already exists
        if (SuperAdmin::where('email', $email)->exists()) {
            if (!$this->option('force') && !$this->confirm("Super Admin com email {$email} já existe. Continuar?")) {
                return 1;
            }
        }

        try {
            // Create super admin
            $superAdmin = SuperAdmin::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'status' => 'active',
                'permissions' => [
                    'tenant.list',
                    'tenant.create',
                    'tenant.read',
                    'tenant.update',
                    'tenant.delete',
                    'tenant.switch',
                    'user.impersonate',
                    'system.monitor',
                    'analytics.view',
                    'configuration.manage',
                    'billing.manage',
                    'support.access',
                ],
                'settings' => [
                    'theme' => 'dark',
                    'notifications_enabled' => true,
                    'audit_log_enabled' => true,
                ],
                'created_by' => null,
                'created_at' => now(),
            ]);

            $this->info('✅ Super Admin criado com sucesso!');
            $this->newLine();

            // Display super admin info
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['ID', $superAdmin->id],
                    ['Nome', $superAdmin->name],
                    ['Email', $superAdmin->email],
                    ['Permissões', count($superAdmin->permissions) . ' permissões'],
                    ['Status', 'Ativo'],
                    ['Criado em', $superAdmin->created_at->format('d/m/Y H:i:s')],
                ]
            );

            $this->newLine();
            $this->info('📋 Próximos passos:');
            $this->line('1. Acesse: http://localhost/super-admin');
            $this->line('2. Faça login com:');
            $this->line("   Email: {$email}");
            $this->line("   Senha: [a senha que você definiu]");
            $this->newLine();

            // Generate API token for testing
            if ($this->confirm('Deseja gerar um token de API para testes?')) {
                $token = $superAdmin->createToken('API Token')->plainTextToken;
                $this->warn('🔑 Token de API gerado:');
                $this->line($token);
                $this->newLine();
                $this->line('Use este token no header: Authorization: Bearer ' . $token);
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Erro ao criar Super Admin: ' . $e->getMessage());
            return 1;
        }
    }
}