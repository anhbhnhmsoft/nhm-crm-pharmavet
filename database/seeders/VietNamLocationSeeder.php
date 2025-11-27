<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class VietNamLocationSeeder extends Seeder
{
    // API V1 - Sử dụng duy nhất API V1
    private $apiV1 = 'https://provinces.open-api.vn/api/';

    public function run(): void
    {
        echo "=== Starting Seeding Vietnam Locations (API V1 Only) ===\n";

        // Truncate các bảng theo thứ tự khóa ngoại ngược
        DB::table('wards')->truncate();
        DB::table('districts')->truncate();
        DB::table('provinces')->truncate();

        $provinceCount = 0;
        $districtCount = 0;
        $wardCount = 0;

        // Maps: code -> id
        $provinceMap = []; // province_code => province_id
        $districtMap = []; // district_code => district_id

        // =======================================================
        // 1. Insert Provinces (API V1: /api/p/)
        // =======================================================
        echo "Fetching and Inserting Provinces...\n";
        $provinces = $this->fetch($this->apiV1 . 'p/');

        if (empty($provinces)) {
            echo "ERROR: Failed to fetch provinces\n";
            return;
        }

        foreach ($provinces as $province) {
            if (!isset($province['code'], $province['name'])) {
                continue;
            }

            $provinceCode = (string) $province['code'];

            // Insert và lấy ID
            $id = DB::table('provinces')->insertGetId([
                'code'          => $provinceCode,
                'name'          => $province['name'],
                'code_name'     => $province['codename'] ?? '',
                'division_type' => $province['division_type'] ?? 'Tỉnh',
                'metadata'      => json_encode($province),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $provinceMap[$provinceCode] = $id;
            $provinceCount++;
        }
        echo "✓ Inserted $provinceCount provinces.\n\n";

        // =======================================================
        // 2. Insert Districts (API V1: /api/d/)
        // =======================================================
        echo "Fetching and Inserting Districts...\n";
        $districts = $this->fetch($this->apiV1 . 'd/');

        if (empty($districts)) {
            echo "ERROR: Failed to fetch districts\n";
            return;
        }
        echo "Found " . count($districts) . " districts.\n";

        foreach ($districts as $district) {
            if (!isset($district['code'], $district['name'], $district['province_code'])) {
                continue;
            }

            $districtCode = (string) $district['code'];
            $provinceCode = (string) $district['province_code'];
            $provinceId = $provinceMap[$provinceCode] ?? null;

            if (!$provinceId) {
                // Nếu tỉnh cha không tồn tại, bỏ qua
                continue;
            }

            // Insert và lấy ID
            $id = DB::table('districts')->insertGetId([
                'code'          => $districtCode,
                'name'          => $district['name'],
                'code_name'     => $district['codename'] ?? '',
                'division_type' => $district['division_type'] ?? 'Quận',
                'province_id'   => $provinceId,
                'province_code' => $provinceCode,
                'metadata'      => json_encode($district),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $districtMap[$districtCode] = $id;
            $districtCount++;
        }
        echo "✓ Inserted $districtCount districts.\n\n";

        // =======================================================
        // 3. Insert Wards (API V1: /api/w/)
        // =======================================================
        echo "Fetching and Inserting Wards...\n";
        $wards = $this->fetch($this->apiV1 . 'w/');

        if (empty($wards)) {
            echo "ERROR: Failed to fetch wards\n";
        } else {
            echo "Found " . count($wards) . " wards. Starting insertion...\n";
        }

        foreach ($wards as $ward) {
            if (!isset($ward['code'], $ward['name'], $ward['district_code'])) {
                continue;
            }

            $wardCode = (string) $ward['code'];
            $districtCode = (string) $ward['district_code'];
            $districtId = $districtMap[$districtCode] ?? null;

            if (!$districtId) {
                // Nếu huyện cha không tồn tại, bỏ qua
                continue;
            }

            DB::table('wards')->insert([
                'code'          => $wardCode,
                'name'          => $ward['name'],
                'code_name'     => $ward['codename'] ?? '',
                'division_type' => $ward['division_type'] ?? 'Phường',
                'district_id'   => $districtId,
                'district_code' => $districtCode,
                'metadata'      => json_encode($ward),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $wardCount++;
        }
        echo "✓ Inserted $wardCount wards.\n\n";

        echo str_repeat("=", 50) . "\n";
        echo "=== Summary ===\n";
        echo "✓ Provinces: $provinceCount\n";
        echo "✓ Districts: $districtCount\n";
        echo "✓ Wards: $wardCount\n";
        echo str_repeat("=", 50) . "\n";
        echo "=== Vietnam Locations Seeded Successfully ===\n";
    }

    /**
     * Tải dữ liệu từ API và xử lý lỗi (luôn trả về array).
     */
    private function fetch(string $url, int $retries = 3): array
    {
        for ($i = 0; $i < $retries; $i++) {
            try {
                $response = Http::timeout(30)->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    if (is_array($data)) {
                        return $data;
                    }
                }

                if ($i < $retries - 1) {
                    echo "  ⚠ HTTP {$response->status()} - Retry " . ($i + 1) . "/$retries URL: " . $url . "\n";
                    sleep(1);
                }
            } catch (\Throwable $e) {
                if ($i < $retries - 1) {
                    echo "  ⚠ Exception: {$e->getMessage()} - Retry " . ($i + 1) . "/$retries URL: " . $url . "\n";
                    sleep(1);
                }
            }
        }

        echo "  ❌ Failed to fetch data after $retries attempts for URL: " . $url . "\n";
        return [];
    }
}
