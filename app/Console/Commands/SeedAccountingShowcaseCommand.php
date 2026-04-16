<?php

namespace App\Console\Commands;

use Database\Seeders\AccountingShowcaseSeeder;
use Illuminate\Console\Command;

class SeedAccountingShowcaseCommand extends Command
{
    protected $signature = 'app:seed-accounting-showcase {--user-email= : Seed theo email user thuoc to chuc can test} {--organization-id= : Seed theo organization_id}';

    protected $description = 'Seed du lieu showcase ke toan cho 1 to chuc cu the';

    public function handle(AccountingShowcaseSeeder $seeder): int
    {
        $userEmail = $this->option('user-email');
        $organizationId = $this->option('organization-id');

        if (is_string($userEmail) && $userEmail !== '') {
            $seeder->seedForUserEmail($userEmail);
            $this->info('Seeded accounting showcase for user email: ' . $userEmail);

            return self::SUCCESS;
        }

        if (is_string($organizationId) && $organizationId !== '') {
            $seeder->seedForOrganizationId((int) $organizationId);
            $this->info('Seeded accounting showcase for organization_id: ' . $organizationId);

            return self::SUCCESS;
        }

        $seeder->run();
        $this->info('Seeded accounting showcase for default organization.');

        return self::SUCCESS;
    }
}
