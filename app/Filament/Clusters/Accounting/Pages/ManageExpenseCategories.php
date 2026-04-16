<?php

namespace App\Filament\Clusters\Accounting\Pages;

use App\Common\Constants\Accounting\ExpenseCategory;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Accounting\AccountingCluster;
use App\Models\Expense;
use App\Utils\Helper;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ManageExpenseCategories extends Page
{
    protected static ?string $cluster = AccountingCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.clusters.accounting.pages.manage-expense-categories';

    public array $categoryStats = [];

    public static function getNavigationLabel(): string
    {
        return __('accounting.expense_category.navigation_label');
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_accounting');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (!$user) return false;

        return Helper::checkPermission([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::ACCOUNTING->value,
        ], $user->role);
    }

    public function getTitle(): string
    {
        return __('accounting.expense_category.page_title');
    }

    public function mount(): void
    {
        $this->loadCategoryStats();
    }

    public function loadCategoryStats(): void
    {
        $orgId = Auth::user()?->organization_id;

        $grouped = Expense::query()
            ->when($orgId, fn($q) => $q->where('organization_id', $orgId))
            ->selectRaw('category, COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $colorMap = [
            ExpenseCategory::MARKETING->value    => ['color' => 'warning',  'hex' => '#f59e0b'],
            ExpenseCategory::OPERATIONAL->value  => ['color' => 'success',  'hex' => '#10b981'],
            ExpenseCategory::FINANCIAL->value    => ['color' => 'primary',  'hex' => '#6366f1'],
            ExpenseCategory::OTHER->value        => ['color' => 'gray',     'hex' => '#6b7280'],
            ExpenseCategory::COST_OF_GOODS->value=> ['color' => 'danger',   'hex' => '#ef4444'],
            ExpenseCategory::SHIPPING_AUTO->value=> ['color' => 'info',     'hex' => '#3b82f6'],
            ExpenseCategory::BAD_DEBT->value     => ['color' => 'danger',   'hex' => '#dc2626'],
        ];

        $descriptionMap = [
            ExpenseCategory::OPERATIONAL->value  => __('accounting.expense_category.desc_operational'),
            ExpenseCategory::MARKETING->value    => __('accounting.expense_category.desc_marketing'),
            ExpenseCategory::FINANCIAL->value    => __('accounting.expense_category.desc_financial'),
            ExpenseCategory::OTHER->value        => __('accounting.expense_category.desc_other'),
            ExpenseCategory::COST_OF_GOODS->value=> __('accounting.expense_category.desc_cost_of_goods'),
            ExpenseCategory::SHIPPING_AUTO->value=> __('accounting.expense_category.desc_shipping_auto'),
            ExpenseCategory::BAD_DEBT->value     => __('accounting.expense_category.desc_bad_debt'),
        ];

        $this->categoryStats = [];
        foreach (ExpenseCategory::cases() as $cat) {
            $stat = $grouped->get($cat->value);
            $this->categoryStats[] = [
                'value'       => $cat->value,
                'label'       => $cat->getLabel(),
                'color'       => $colorMap[$cat->value]['color'] ?? 'gray',
                'hex'         => $colorMap[$cat->value]['hex'] ?? '#6b7280',
                'description' => $descriptionMap[$cat->value] ?? '',
                'count'       => (int) ($stat?->count ?? 0),
                'total'       => (float) ($stat?->total ?? 0),
                'is_system'   => in_array($cat, [
                    ExpenseCategory::SHIPPING_AUTO,
                    ExpenseCategory::BAD_DEBT,
                    ExpenseCategory::COST_OF_GOODS,
                ]),
            ];
        }
    }

    public function getTotalExpenses(): int
    {
        return array_sum(array_column($this->categoryStats, 'count'));
    }

    public function getTotalAmount(): float
    {
        return array_sum(array_column($this->categoryStats, 'total'));
    }
}
