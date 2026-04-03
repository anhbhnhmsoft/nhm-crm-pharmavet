<?php

namespace Database\Seeders;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Marketing\IntegrationType;
use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\StatusConnect;
use App\Common\Constants\Team\TeamType;
use App\Common\Constants\User\UserPosition;
use App\Common\Constants\User\UserRole;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\MarketingAlertLog;
use App\Models\MarketingBudget;
use App\Models\MarketingScoringRuleSet;
use App\Models\MarketingSpend;
use App\Models\MarketingSpendAttachment;
use App\Models\Organization;
use App\Models\Order;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketingDemoSeeder extends Seeder
{
    private array $columnCache = [];

    public function run(): void
    {
        DB::transaction(function (): void {
            $organization = Organization::query()->first();
            if (!$organization) {
                return;
            }

            $admin = User::query()->firstOrCreate(
                ['username' => 'marketing_admin_demo'],
                [
                    'organization_id' => $organization->id,
                    'name' => 'Marketing Admin Demo',
                    'email' => 'marketing-admin-demo@example.com',
                    'password' => Hash::make('Test12345678@'),
                    'role' => UserRole::ADMIN->value,
                    'position' => UserPosition::LEADER->value,
                    'disable' => false,
                ]
            );

            $marketingTeam = Team::query()->firstOrCreate(
                ['code' => 'MKT-DEMO-' . $organization->id],
                [
                    'organization_id' => $organization->id,
                    'name' => 'Marketing Demo Team',
                    'type' => TeamType::MARKETING->value,
                    'description' => 'Seeder generated marketing team',
                ]
            );

            $saleTeam = Team::query()->firstOrCreate(
                ['code' => 'SALE-DEMO-' . $organization->id],
                [
                    'organization_id' => $organization->id,
                    'name' => 'Sale Demo Team',
                    'type' => TeamType::SALE->value,
                    'description' => 'Seeder generated sale team',
                ]
            );

            $marketingUsers = collect([1, 2])->map(function (int $index) use ($organization, $marketingTeam, $admin) {
                return User::query()->firstOrCreate(
                    ['username' => 'mkt_demo_' . $index],
                    [
                        'organization_id' => $organization->id,
                        'team_id' => $marketingTeam->id,
                        'name' => 'Marketing Demo ' . $index,
                        'email' => 'mkt_demo_' . $index . '@example.com',
                        'password' => Hash::make('Test12345678@'),
                        'role' => UserRole::MARKETING->value,
                        'position' => UserPosition::STAFF->value,
                        'disable' => false,
                        'created_by' => $admin->id,
                    ]
                );
            });

            $saleUsers = collect([1, 2, 3])->map(function (int $index) use ($organization, $saleTeam, $admin) {
                return User::query()->firstOrCreate(
                    ['username' => 'sale_demo_' . $index],
                    [
                        'organization_id' => $organization->id,
                        'team_id' => $saleTeam->id,
                        'name' => 'Sale Demo ' . $index,
                        'email' => 'sale_demo_' . $index . '@example.com',
                        'password' => Hash::make('Test12345678@'),
                        'role' => UserRole::SALE->value,
                        'position' => UserPosition::STAFF->value,
                        'disable' => false,
                        'created_by' => $admin->id,
                    ]
                );
            });

            $websiteIntegration = $this->upsertIntegration(
                organizationId: (int) $organization->id,
                name: 'Website V2 Demo',
                type: IntegrationType::WEBSITE->value,
                createPayload: [
                    'status' => StatusConnect::CONNECTED->value,
                    'config' => [
                        'site_id' => 'site_demo_' . $organization->id,
                        'webhook_secret' => Str::random(24),
                        'input_mode' => 'auto',
                        'distribution_mode' => 'quota',
                        'distribution_limit' => 5,
                        'inbound_tag' => 'form_register',
                        'field_defaults' => [
                            'source_detail' => 'landing_page_demo',
                        ],
                    ],
                    'field_mapping' => [
                        'full_name' => 'name',
                        'phone_number' => 'phone',
                        'email_address' => 'email',
                        'utm_campaign' => 'source_detail',
                    ],
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]
            );

            $this->upsertIntegration(
                organizationId: (int) $organization->id,
                name: 'Facebook Lead Ads Demo',
                type: IntegrationType::FACEBOOK_ADS->value,
                createPayload: [
                    'status' => StatusConnect::CONNECTED->value,
                    'config' => [
                        'pixel_id' => '123456789012345',
                        'webhook_verify_token' => Str::random(24),
                        'input_mode' => 'auto',
                    ],
                    'field_mapping' => [
                        'full_name' => 'name',
                        'phone_number' => 'phone',
                        'email' => 'email',
                    ],
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]
            );

            MarketingScoringRuleSet::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => 'Default Marketing Score',
                ],
                [
                    'rules_json' => [
                        'order_weight' => 10,
                        'contact_weight' => 2,
                        'revenue_weight' => 0.001,
                        'conversion_bonus_threshold' => 30,
                        'conversion_bonus' => 20,
                    ],
                    'is_default' => true,
                    'is_active' => true,
                ]
            );

            $channels = ['facebook_ads', 'google_ads', 'website'];
            $campaigns = ['spring_sale', 'retargeting_q2', 'new_product_launch'];

            foreach (range(0, 14) as $offset) {
                $date = Carbon::now()->subDays($offset)->toDateString();

                foreach ($channels as $index => $channel) {
                    $campaign = $campaigns[$index % count($campaigns)];
                    $budgetAmount = 3000000 + ($index * 500000);
                    $actualSpend = $budgetAmount + (($offset % 4 === 0) ? 600000 : -150000);
                    $feeAmount = 50000 + ($index * 20000);

                    MarketingBudget::query()->updateOrCreate(
                        [
                            'organization_id' => $organization->id,
                            'date' => $date,
                            'channel' => $channel,
                            'campaign' => $campaign,
                        ],
                        [
                            'budget_amount' => $budgetAmount,
                        ]
                    );

                    $spend = MarketingSpend::query()->updateOrCreate(
                        [
                            'organization_id' => $organization->id,
                            'date' => $date,
                            'channel' => $channel,
                            'campaign' => $campaign,
                        ],
                        [
                            'actual_spend' => $actualSpend,
                            'fee_amount' => $feeAmount,
                            'note' => 'Seeder spend data for ' . $channel,
                        ]
                    );

                    $filePath = 'marketing/spends/demo_' . $spend->id . '_v1.txt';
                    Storage::disk('local')->put($filePath, 'Demo attachment for spend #' . $spend->id);

                    MarketingSpendAttachment::query()->updateOrCreate(
                        [
                            'marketing_spend_id' => $spend->id,
                            'version' => 1,
                        ],
                        [
                            'file_path' => $filePath,
                            'uploaded_by' => $admin->id,
                            'uploaded_at' => Carbon::parse($date)->setTime(9, 0),
                        ]
                    );
                }
            }

            $sources = ['facebook_ads', 'google_ads', IntegrationType::WEBSITE->value];
            $sourceDetails = ['spring_sale', 'retargeting_q2', 'landing_page_demo'];

            foreach (range(1, 60) as $index) {
                $createdAt = Carbon::now()->subDays(rand(0, 20))->setTime(rand(8, 19), rand(0, 59));
                $source = $sources[$index % count($sources)];
                $sourceDetail = $sourceDetails[$index % count($sourceDetails)];
                $sale = $saleUsers[$index % $saleUsers->count()];

                $customer = Customer::query()->create([
                    'organization_id' => $organization->id,
                    'username' => 'Demo Lead ' . $index,
                    'phone' => '09' . str_pad((string) (10000000 + $index), 8, '0', STR_PAD_LEFT),
                    'email' => 'lead_demo_' . $index . '@example.com',
                    'address' => 'Demo Address ' . $index,
                    'customer_type' => $index % 5 === 0 ? CustomerType::OLD_CUSTOMER->value : CustomerType::NEW->value,
                    'assigned_staff_id' => $sale->id,
                    'note' => '#form_register',
                    'source' => $source,
                    'source_detail' => $sourceDetail,
                    'source_id' => 'ext_' . $index,
                    'interaction_status' => InteractionStatus::FIRST_CALL->value,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                if ($index % 2 === 0) {
                    $status = match (true) {
                        $index % 9 === 0 => OrderStatus::CANCELLED->value,
                        $index % 7 === 0 => OrderStatus::SHIPPING->value,
                        default => OrderStatus::COMPLETED->value,
                    };

                    $total = rand(300000, 2500000);
                    $discount = rand(0, 150000);

                    $orderPayload = [
                        'organization_id' => $organization->id,
                        'customer_id' => $customer->id,
                        'code' => 'ORD-MKT-' . str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                        'status' => $status,
                        'total_amount' => $total,
                        'discount' => $discount,
                        'shipping_fee' => rand(20000, 50000),
                        'deposit' => rand(0, 100000),
                        'created_by' => $sale->id,
                        'updated_by' => $sale->id,
                        'created_at' => $createdAt->copy()->addHours(2),
                        'updated_at' => $createdAt->copy()->addHours(2),
                    ];

                    if ($this->hasColumn('orders', 'shipping_exception_reason_code')) {
                        $orderPayload['shipping_exception_reason_code'] = $status === OrderStatus::CANCELLED->value
                            ? collect(['customer_cancel', 'logistic_delay', 'return_request'])->random()
                            : null;
                    }

                    Order::query()->create($orderPayload);
                }
            }

            MarketingAlertLog::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'alert_type' => 'over_budget',
                    'channel' => 'facebook_ads',
                    'campaign' => 'spring_sale',
                    'triggered_at' => Carbon::now()->subDay(),
                ],
                [
                    'severity' => 'high',
                    'payload_json' => [
                        'budget_amount' => 3000000,
                        'actual_spend' => 3800000,
                        'reason' => 'Seeder demo over budget alert',
                    ],
                    'resolved_at' => null,
                ]
            );

            MarketingAlertLog::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'alert_type' => 'low_roi',
                    'channel' => 'google_ads',
                    'campaign' => 'retargeting_q2',
                    'triggered_at' => Carbon::now()->subDays(2),
                ],
                [
                    'severity' => 'warning',
                    'payload_json' => [
                        'roi' => 0.67,
                        'reason' => 'Seeder demo low ROI alert',
                    ],
                    'resolved_at' => Carbon::now()->subDay(),
                ]
            );

            // Keep integration timestamp fresh for UI summary.
            $websiteIntegration->update(['last_sync_at' => now()]);
        });
    }

    private function upsertIntegration(int $organizationId, string $name, int $type, array $createPayload): Integration
    {
        $query = Integration::query()
            ->where('organization_id', $organizationId)
            ->where('name', $name);

        if ($this->hasColumn('integrations', 'type')) {
            $query->where('type', $type);
        }

        $integration = $query->first();
        if ($integration) {
            return $integration;
        }

        if ($this->hasColumn('integrations', 'type')) {
            $createPayload['type'] = $type;
        }

        $createPayload['organization_id'] = $organizationId;
        $createPayload['name'] = $name;

        return Integration::query()->create($createPayload);
    }

    private function hasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;

        if (array_key_exists($cacheKey, $this->columnCache)) {
            return $this->columnCache[$cacheKey];
        }

        return $this->columnCache[$cacheKey] = Schema::hasColumn($table, $column);
    }
}
