<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('db:secure')]
#[Description('Enable row level security on every public table and block Supabase API roles from reading app data')]
class SecureDatabase extends Command
{
    /**
     * Roles Supabase uses for its auto-generated REST/GraphQL APIs.
     */
    private const API_ROLES = ['anon', 'authenticated'];

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            return self::SUCCESS;
        }

        $tables = DB::table('pg_tables')->where('schemaname', 'public')->pluck('tablename');

        // Laravel connects as the table owner, which bypasses RLS, so the app keeps full access.
        foreach ($tables as $table) {
            DB::statement(sprintf('ALTER TABLE public.%s ENABLE ROW LEVEL SECURITY', $this->quote($table)));
        }

        $roles = DB::table('pg_roles')->whereIn('rolname', self::API_ROLES)->pluck('rolname');

        foreach ($roles as $role) {
            $quoted = $this->quote($role);
            DB::statement("REVOKE ALL ON ALL TABLES IN SCHEMA public FROM {$quoted}");
            DB::statement("REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM {$quoted}");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON TABLES FROM {$quoted}");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON SEQUENCES FROM {$quoted}");
        }

        $this->info("Secured {$tables->count()} tables".($roles->isNotEmpty() ? ' and revoked API role access.' : '.'));

        return self::SUCCESS;
    }

    private function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
