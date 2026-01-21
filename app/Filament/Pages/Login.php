<?php

namespace App\Filament\Pages;

use App\Common\Constants\Language;
use App\Models\Organization;
use App\Services\AuthService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Vite;
use Illuminate\Validation\ValidationException;
use Throwable;

class Login extends BaseLogin
{
    protected AuthService $authService;

    public function mount(): void
    {
        parent::mount();
        $locale = session('locale', Language::VI->value);
        App::setLocale($locale);
    }

    public function boot()
    {
        $this->authService = app(AuthService::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('organization_code')
                ->label(__('filament.login.organization_code'))
                ->options(fn() => Organization::get()->pluck('name', 'code')->toArray())
                ->required()
                ->extraInputAttributes(['tabindex' => 1])
                ->validationMessages([
                    'required' => __('common.error.required'),
                ]),
            TextInput::make('username')
                ->label(__('filament.login.username'))
                ->string()
                ->required()
                ->autocomplete('username')
                ->extraInputAttributes(['tabindex' => 2])
                ->validationMessages([
                    'required' => __('common.error.required'),
                ]),
            TextInput::make('password')
                ->label(__('filament.login.password'))
                ->password()
                ->revealable(filament()->arePasswordsRevealable())
                ->autocomplete('current-password')
                ->required()
                ->extraInputAttributes(['tabindex' => 3])
                ->validationMessages([
                    'required' => __('common.error.required'),
                ]),
        ]);
    }

    public function switchLanguage(string $locale): void
    {
        session(['locale' => $locale]);
        App::setLocale($locale);

        $this->dispatch('$refresh');
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title(__('filament-panels::auth/pages/login.notifications.throttled.title', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => $exception->minutesUntilAvailable,
                ]))
                ->body(array_key_exists('body', __('filament-panels::auth/pages/login.notifications.throttled') ?: []) ? __('filament-panels::auth/pages/login.notifications.throttled.body', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => $exception->minutesUntilAvailable,
                ]) : null)
                ->danger()
                ->send();
        }
        $data = $this->form->getState();

        $loginValue = $data['username'];

        $credentials = [
            'login_value'       => $loginValue,
            'password'          => $data['password'],
            'organization_code' => $data['organization_code'],
        ];

        $result = $this->authService->handleLoginUser($credentials);

        if ($result->isError()) {
            if ($result->getException() instanceof Throwable) {
                throw $result->getException();
            }
        }
        return app(LoginResponse::class);
    }

    public function getView(): string
    {
        return 'filament.pages.login';
    }
}
