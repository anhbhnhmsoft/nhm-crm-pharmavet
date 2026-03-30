<div class="w-full max-w-lg mx-auto bg-white dark:bg-zinc-900 shadow-xl rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-800">
    <div class="px-8 py-10">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-zinc-900 dark:text-white">{{ __('auth.registration.title') }}</h2>
            <p class="text-zinc-500 dark:text-zinc-400 mt-2">{{ __('auth.registration.subtitle') }}</p>
        </div>

        @if ($success)
            <div x-data="{ show: true }" x-show="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                <div class="bg-white dark:bg-zinc-900 rounded-2xl p-8 max-w-sm w-full shadow-2xl border border-zinc-200 dark:border-zinc-800 transform transition-all">
                    <div class="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-center text-zinc-900 dark:text-white mb-2">{{ __('auth.registration.success_title') }}</h3>
                    <p class="text-zinc-600 dark:text-zinc-400 text-center mb-8">{{ __('auth.registration.success_body') }}</p>
                    <button @click="show = false; $wire.set('success', false)" class="w-full py-3 bg-zinc-900 dark:bg-white dark:text-zinc-900 text-white rounded-xl font-semibold hover:opacity-90 transition-opacity">
                        Đóng
                    </button>
                </div>
            </div>
        @endif

        <form wire:submit.prevent="submit" x-data="{ industryId: @entangle('industry_id') }" class="space-y-5">
            {{-- Họ tên --}}
            <div>
                <label for="name" class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">{{ __('auth.registration.fields.name') }} <span class="text-red-500">*</span></label>
                <input wire:model.blur="name" type="text" id="name" placeholder="{{ __('auth.registration.placeholder.name') }}" 
                    class="w-full px-4 py-3 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-white focus:ring-2 focus:ring-zinc-900 dark:focus:ring-white transition-all outline-none @error('name') border-red-500 @enderror">
                @error('name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- SĐT --}}
            <div>
                <label for="phone" class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">{{ __('auth.registration.fields.phone') }} <span class="text-red-500">*</span></label>
                <input wire:model.blur="phone" type="text" id="phone" placeholder="{{ __('auth.registration.placeholder.phone') }}" 
                    class="w-full px-4 py-3 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-white focus:ring-2 focus:ring-zinc-900 dark:focus:ring-white transition-all outline-none @error('phone') border-red-500 @enderror">
                @error('phone') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Email --}}
            <div>
                <label for="email" class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">{{ __('auth.registration.fields.email') }} <span class="text-red-500">*</span></label>
                <input wire:model.blur="email" type="email" id="email" placeholder="{{ __('auth.registration.placeholder.email') }}" 
                    class="w-full px-4 py-3 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-white focus:ring-2 focus:ring-zinc-900 dark:focus:ring-white transition-all outline-none @error('email') border-red-500 @enderror">
                @error('email') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Ngành hàng --}}
            <div>
                <label for="industry_id" class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">{{ __('auth.registration.fields.industry') }} <span class="text-red-500">*</span></label>
                <select wire:model.live="industry_id" id="industry_id" 
                    class="w-full px-4 py-3 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-white focus:ring-2 focus:ring-zinc-900 dark:focus:ring-white transition-all outline-none @error('industry_id') border-red-500 @enderror">
                    <option value="">{{ __('auth.registration.fields.industry_placeholder') }}</option>
                    @foreach($industriesList as $key => $name)
                        <option value="{{ $key }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('industry_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                
                <div x-show="industryId == {{ App\Common\Constants\Organization\ProductField::OTHER->value }}" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <input wire:model="custom_industry" type="text" placeholder="{{ __('auth.registration.fields.custom_industry_placeholder') }}" 
                        class="w-full mt-2 px-4 py-3 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-white focus:ring-2 focus:ring-zinc-900 dark:focus:ring-white transition-all outline-none @error('custom_industry') border-red-500 @enderror">
                    @error('custom_industry') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                {{-- Số lượng nhân sự --}}
                <div>
                    <label for="employee_count" class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">{{ __('auth.registration.fields.employee_count') }} <span class="text-red-500">*</span></label>
                    <select wire:model="employee_count" id="employee_count" 
                        class="w-full px-4 py-3 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-white focus:ring-2 focus:ring-zinc-900 dark:focus:ring-white transition-all outline-none @error('employee_count') border-red-500 @enderror">
                        <option value="">{{ __('auth.registration.fields.employee_count_placeholder') }}</option>
                        <option value="1-50">{{ __('auth.registration.fields.number_employee.select_1') }}</option>
                        <option value="51-100">{{ __('auth.registration.fields.number_employee.select_2') }}</option>
                        <option value="101-200">{{ __('auth.registration.fields.number_employee.select_3') }}</option>
                        <option value="201-500">{{ __('auth.registration.fields.number_employee.select_4') }}</option>
                        <option value="501-1000">{{ __('auth.registration.fields.number_employee.select_5') }}</option>
                        <option value="1000+">{{ __('auth.registration.fields.number_employee.select_6') }}</option>
                    </select>
                    @error('employee_count') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                {{-- Khung giờ nghe máy --}}
                <div>
                    <label for="preferred_time" class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">{{ __('auth.registration.fields.preferred_time') }} <span class="text-red-500">*</span></label>
                    <select wire:model="preferred_time" id="preferred_time" 
                        class="w-full px-4 py-3 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-white focus:ring-2 focus:ring-zinc-900 dark:focus:ring-white transition-all outline-none @error('preferred_time') border-red-500 @enderror">
                        <option value="">{{ __('auth.registration.fields.preferred_time_placeholder') }}</option>
                        <option value="anytime">{{ __('auth.registration.options.preferred_time.anytime') }}</option>
                        <option value="morning">{{ __('auth.registration.options.preferred_time.morning') }}</option>
                        <option value="afternoon">{{ __('auth.registration.options.preferred_time.afternoon') }}</option>
                        <option value="evening">{{ __('auth.registration.options.preferred_time.evening') }}</option>
                    </select>
                    @error('preferred_time') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                class="w-full mt-4 flex items-center justify-center gap-2 py-4 bg-zinc-900 dark:bg-white dark:text-zinc-900 text-white rounded-xl font-bold text-lg hover:scale-[1.02] active:scale-[0.98] transition-all disabled:opacity-50">
                <span wire:loading.remove wire:target="submit">{{ __('auth.registration.submit') }}</span>
                <span wire:loading wire:target="submit">{{ __('auth.registration.submitting') }}</span>
                <svg wire:loading.remove wire:target="submit" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                </svg>
            </button>

            <div class="text-center mt-6">
                <a href="{{ url('/') }}" class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300 transition-colors underline underline-offset-4">
                    {{ __('auth.registration.back_to_login') }}
                </a>
            </div>
        </form>

        @if (session()->has('error'))
            <div class="mt-4 p-4 bg-red-100 text-red-700 rounded-xl text-sm">
                {{ session('error') }}
            </div>
        @endif
    </div>
</div>
