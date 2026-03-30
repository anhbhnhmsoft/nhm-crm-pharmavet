<?php

namespace Database\Seeders;

use App\Common\Constants\Organization\ProductField;
use App\Common\Constants\User\UserPosition;
use App\Common\Constants\User\UserRole;
use App\Models\District;
use App\Models\Organization;
use App\Models\Province;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedAdmin();
    }


    private function seedAdmin()
    {
        DB::beginTransaction();
        try {
            $organization = Organization::query()->create([
                "name" => "PharmaVet",
                "code" => "PHARMAVET",
                "phone" => "1234567890",
                "address" => "123 Main St",
                "description" => "PharmaVet",
                "product_field" => ProductField::FASHION,
                "disable" => false,
            ]);

            User::create([
                "name" => "Super Admin",
                "username" => "superadmin",
                "email"    => "admin@admin.vn",
                "password" => bcrypt("Test12345678@"),
                "organization_id" => $organization->id,
                "role" => UserRole::SUPER_ADMIN->value,
                'disable' => false,
                'position' => UserPosition::LEADER->value,
            ]);
            DB::commit();
            return true;
        }catch (\Exception $exception){
            DB::rollBack();
            dump($exception);
            return false;
        }
    }
}
