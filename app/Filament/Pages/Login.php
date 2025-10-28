<?php

namespace App\Filament\Pages;

use App\Common\Constants\Language;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();
        $locale = session('locale', Language::VI->value);
        App::setLocale($locale);
    }

    public function boot()
    {
        FilamentAsset::register([
            Css::make('app-css', Vite::asset('resources/css/app.css')),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('organization_code')
                ->label(__('filament.login.organization_code'))
                ->required()
                ->autocomplete()
                ->autofocus()
                ->extraInputAttributes(['tabindex' => 1]),
            TextInput::make('username')
                ->label(__('filament.login.username'))
                ->string()
                ->required()
                ->autocomplete('username')
                ->extraInputAttributes(['tabindex' => 2]),
            TextInput::make('password')
                ->label(__('filament.login.password'))
                ->password()
                ->revealable(filament()->arePasswordsRevealable())
                ->autocomplete('current-password')
                ->required()
                ->extraInputAttributes(['tabindex' => 3]),
            Checkbox::make('remember')
                ->label(__('filament.login.remember_me'))
                ->extraInputAttributes(['tabindex' => 4]),

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

        $credentials = [
            'username'     => $data['username'],
            'password'     => $data['password'],
            'organization_code' => $data['organization_code'],
        ];

        if (!auth()->attempt($credentials, (bool)($data['remember'] ?? false))) {
            throw ValidationException::withMessages([
                'data.username' => __('filament.login.error.invalid_credentials'),
            ]);
        }
        return app(LoginResponse::class);
    }

    public function getView(): string
    {
        return 'filament.pages.login';
    }
}
