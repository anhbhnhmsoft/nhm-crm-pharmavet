<?php

namespace Database\Seeders;

use App\Common\Constants\Organization\ProductField;
use App\Common\Constants\User\UserPosition;
use App\Common\Constants\User\UserRole;
use App\Models\District;
use App\Models\Organization;
use App\Models\Province;
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
//        $this->seedProvince();
        $this->seedAdmin();
    }

    private function seedProvince()
    {
        DB::beginTransaction();
        try {
            $responseProvince = Http::get('https://provinces.open-api.vn/api/v1/p/');
            if ($responseProvince->successful()) {
                $data = $responseProvince->json();  // Lấy dữ liệu dưới dạng mảng
                // Lưu dữ liệu vào bảng provinces
                foreach ($data as $provinceData) {
                    Province::query()->updateOrCreate(
                        ['code' => $provinceData['code']],
                        [
                            'name' => $provinceData['name'],
                            'division_type' => $provinceData["division_type"],
                        ]
                    );
                }
            } else {
                DB::rollBack();
                return false;
            }

            $responseDistricts = Http::get('https://provinces.open-api.vn/api/v1/d/');
            if ($responseDistricts->successful()) {
                $data = $responseDistricts->json();  // Lấy dữ liệu dưới dạng mảng
                // Lưu dữ liệu vào bảng provinces
                foreach ($data as $district) {
                    District::query()->updateOrCreate(
                        ['code' => $district['code']],
                        [
                            'name' => $district['name'],
                            'division_type' => $district["division_type"],
                            'province_code' => $district['province_code']
                        ]
                    );
                }
            } else {
                DB::rollBack();
                return false;
            }

            $responseWards = Http::get('https://provinces.open-api.vn/api/v1/w/');
            if ($responseWards->successful()) {
                $data = $responseWards->json();  // Lấy dữ liệu dưới dạng mảng
                // Lưu dữ liệu vào bảng provinces
                foreach ($data as $ward) {
                    Ward::query()->updateOrCreate(
                        ['code' => $ward['code']],
                        [
                            'name' => $ward['name'],
                            'division_type' => $ward["division_type"],
                            'district_code' => $ward['district_code']
                        ]
                    );
                }
            } else {
                DB::rollBack();
                return false;
            }

            DB::commit();
            return true;
        } catch (\Exception $exception) {
            DB::rollBack();
            dump($exception);
            return false;
        }
    }

    private function seedAdmin()
    {
        DB::beginTransaction();
        try {
            $organization = Organization::query()->create([
                "name" => "PharmaVet",
                "code" => "PHARMAVET",
                "description" => "PharmaVet",
                "address" => "123 Main St",
                "phone" => "1234567890",
                "product_field" => ProductField::FASHION,
                "province_code" => "1",
                "district_code" => "1",
                "ward_code" => "1",
                "disable" => false,
            ]);

            $organization->users()->create([
                "name" => "Super Admin",
                "username" => "admin@admin.vn",
                "password" => bcrypt("Test12345678@"),
                "organization_code" => $organization->code,
                "role" => UserRole::SUPER_ADMIN->value,
                'disable' => false,
                'position' => UserPosition::ADMIN->value,
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
