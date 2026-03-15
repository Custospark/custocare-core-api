<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UserStatisticsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Only platform administrators can access user statistics
        // return $this->user() && $this->user()->can('view-platform-statistics');
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_range' => 'sometimes|string|in:7_days,14_days,30_days,90_days,4_weeks,8_weeks,12_weeks,24_weeks',
            'export_format' => 'sometimes|string|in:csv,json',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_range.in' => 'The date range must be one of: 7_days, 14_days, 30_days, 90_days, 4_weeks, 8_weeks, 12_weeks, 24_weeks',
            'export_format.in' => 'The export format must be either csv or json',
        ];
    }
}