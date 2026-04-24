<?php

namespace App\Livewire\Auth;

use App\Common\Constants\Organization\ProductField;
use App\Services\LeadService;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PartnerRegistration extends Component
{
    public $name = '';
    public $phone = '';
    public $email = '';
    public $industry_id = '';
    public $employee_count = '';
    public $preferred_time = '';
    public $custom_industry = '';

    public $success = false;

    public function mount(): void
    {
        $this->applyLocale();
    }

    public function hydrate(): void
    {
        $this->applyLocale();
    }

    public function getIndustries(): array
    {
        return ProductField::toOptions();
    }

    public function getEmployeeCountOptions(): array
    {
        return [
            '1-50' => __('auth.registration.fields.number_employee.select_1'),
            '51-100' => __('auth.registration.fields.number_employee.select_2'),
            '101-200' => __('auth.registration.fields.number_employee.select_3'),
            '201-500' => __('auth.registration.fields.number_employee.select_4'),
            '501-1000' => __('auth.registration.fields.number_employee.select_5'),
            '1000+' => __('auth.registration.fields.number_employee.select_6'),
        ];
    }

    public function getPreferredTimeOptions(): array
    {
        return [
            'anytime' => __('auth.registration.options.preferred_time.anytime'),
            'morning' => __('auth.registration.options.preferred_time.morning'),
            'afternoon' => __('auth.registration.options.preferred_time.afternoon'),
            'evening' => __('auth.registration.options.preferred_time.evening'),
        ];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'min:3', 'max:255'],
            'phone' => ['required', 'regex:/^([0-9\\s\\-\\+\\(\\)]*)$/', 'min:10', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'industry_id' => ['required', Rule::in(array_map('strval', array_keys($this->getIndustries())))],
            'custom_industry' => ['nullable', 'required_if:industry_id,' . ProductField::OTHER->value, 'min:2', 'max:255'],
            'employee_count' => ['required', Rule::in(array_keys($this->getEmployeeCountOptions()))],
            'preferred_time' => ['required', Rule::in(array_keys($this->getPreferredTimeOptions()))],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => __('auth.registration.validation.name_required'),
            'name.min' => __('auth.registration.validation.name_min'),
            'name.max' => __('auth.registration.validation.name_max'),
            'phone.required' => __('auth.registration.validation.phone_required'),
            'phone.regex' => __('auth.registration.validation.phone_regex'),
            'phone.min' => __('auth.registration.validation.phone_min'),
            'phone.max' => __('auth.registration.validation.phone_max'),
            'email.required' => __('auth.registration.validation.email_required'),
            'email.email' => __('auth.registration.validation.email_email'),
            'email.max' => __('auth.registration.validation.email_max'),
            'industry_id.required' => __('auth.registration.validation.industry_required'),
            'industry_id.in' => __('auth.registration.validation.industry_invalid'),
            'custom_industry.required_if' => __('auth.registration.validation.custom_industry_required'),
            'custom_industry.min' => __('auth.registration.validation.custom_industry_min'),
            'custom_industry.max' => __('auth.registration.validation.custom_industry_max'),
            'employee_count.required' => __('auth.registration.validation.employee_count_required'),
            'employee_count.in' => __('auth.registration.validation.employee_count_invalid'),
            'preferred_time.required' => __('auth.registration.validation.preferred_time_required'),
            'preferred_time.in' => __('auth.registration.validation.preferred_time_invalid'),
        ];
    }

    public function updated(string $property): void
    {
        if ($property === 'industry_id' && (int) $this->industry_id !== ProductField::OTHER->value) {
            $this->custom_industry = '';
            $this->resetValidation('custom_industry');
        }

        if ($property === 'custom_industry' && (int) $this->industry_id !== ProductField::OTHER->value) {
            return;
        }

        $this->validateOnly($property, $this->rules(), $this->messages());
    }

    public function submit(LeadService $leadService)
    {
        $validated = $this->validate($this->rules(), $this->messages());

        $industries = $this->getIndustries();
        $industryName = '';
        
        if ((int) $validated['industry_id'] === ProductField::OTHER->value) {
            $industryName = $validated['custom_industry'];
        } else {
            $industryName = $industries[$validated['industry_id']] ?? '';
        }

        $data = [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'industry' => $industryName,
            'product_id' => $validated['industry_id'],
            'employee_count' => $validated['employee_count'],
            'preferred_time' => $validated['preferred_time'],
        ];

        $result = $leadService->submitRegistration($data);

        if ($result->isSuccess()) {
            $this->success = true;
            $this->reset(['name', 'phone', 'email', 'industry_id', 'custom_industry', 'employee_count', 'preferred_time']);
        } else {
            session()->flash('error', $result->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.auth.partner-registration', [
            'industriesList' => $this->getIndustries(),
            'employeeCountOptions' => $this->getEmployeeCountOptions(),
            'preferredTimeOptions' => $this->getPreferredTimeOptions(),
        ]);
    }

    protected function applyLocale(): void
    {
        App::setLocale(session('locale', config('app.locale')));
    }
}
