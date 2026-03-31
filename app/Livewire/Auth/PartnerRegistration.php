<?php

namespace App\Livewire\Auth;

use App\Common\Constants\Organization\ProductField;
use App\Services\LeadService;
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

    public function getIndustries(): array
    {
        return ProductField::toOptions();
    }

    protected $rules = [
        'name' => 'required|min:3',
        'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10',
        'email' => 'required|email',
        'industry_id' => 'required',
        'custom_industry' => 'required_if:industry_id,other',
        'employee_count' => 'required',
        'preferred_time' => 'required',
    ];

    protected $messages = [
        'name.required' => 'auth.registration.validation.name_required',
        'name.min' => 'auth.registration.validation.name_min',
        'phone.required' => 'auth.registration.validation.phone_required',
        'phone.regex' => 'auth.registration.validation.phone_regex',
        'phone.min' => 'auth.registration.validation.phone_min',
        'email.required' => 'auth.registration.validation.email_required',
        'email.email' => 'auth.registration.validation.email_email',
        'industry_id.required' => 'auth.registration.validation.industry_required',
        'custom_industry.required_if' => 'auth.registration.validation.custom_industry_required',
        'employee_count.required' => 'auth.registration.validation.employee_count_required',
        'preferred_time.required' => 'auth.registration.validation.preferred_time_required',
    ];

    public function submit(LeadService $leadService)
    {
        $translatedMessages = [];
        foreach ($this->messages as $key => $value) {
            $translatedMessages[$key] = __($value);
        }

        $this->validate($this->rules, $translatedMessages);

        $industries = $this->getIndustries();
        $industryName = '';
        
        if ((int)$this->industry_id === ProductField::OTHER->value) {
            $industryName = $this->custom_industry;
        } else {
            $industryName = $industries[$this->industry_id] ?? '';
        }

        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'industry' => $industryName,
            'product_id' => $this->industry_id,
            'employee_count' => $this->employee_count,
            'preferred_time' => $this->preferred_time,
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
            'industriesList' => $this->getIndustries()
        ]);
    }
}
