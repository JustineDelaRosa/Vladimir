<?php

namespace App\Http\Requests\FixedAsset;

use App\Models\FixedAsset;
use Illuminate\Foundation\Http\FormRequest;

class FixedAssetUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->route()->parameter('fixed_asset');
        return [
            'sub_capex_id' => 'nullable|exists:sub_capexes,id',
            'tag_number' => ['nullable', 'max:13', function ($attribute, $value, $fail) use ($id) {
                //if the value id "-" and the is_old_asset is true return fail error
                if($value == "-" && $this->is_old_asset){
                    $fail('This is required for old asset');
                }
                $tag_number = FixedAsset::withTrashed()->where('tag_number', $value)
                    ->where('tag_number', '!=', '-')
                    ->where('id', '!=', $id)
                    ->exists();
                if ($tag_number) {
                    $fail('Tag number already exists');
                }
            }],
            'tag_number_old' => ['nullable', 'max:13', function ($attribute, $value, $fail) use ($id) {
                $tag_number_old = FixedAsset::withTrashed()->where('tag_number_old', $value)
                    ->where('tag_number_old', '!=', '-')
                    ->where('id', '!=', $id)
                    ->exists();
                if ($tag_number_old) {
                    $fail('Tag number old already exists');
                }
            }],
            'asset_description' => 'required',
            'type_of_request_id' => 'required',
            'asset_specification' => 'required',
            'accountability' => 'required',
            'accountable' => ['required_if:accountability,Personal Issued',
                function ($attribute, $value, $fail) {
                    $accountability = request()->input('accountable');
                    //if accountable is null, continue
                    if ($value == null) {
                        return;
                    }

                    // Check if necessary keys exist to avoid undefined index
                    if (isset($accountability['general_info']['full_name'])) {
                        $fullName = $accountability['general_info']['full_name'];
                    }
                    else {
                        // Fail validation if keys don't exist
                        $fail('The accountable person\'s full name is required.');
                        return;
                    }

                    // Validate full name
                    if ($fullName === '') {
                        $fail('The accountable person\'s full name cannot be empty.');
                        return;
                    }
                },
            ],
            'cellphone_number' => 'nullable|numeric|digits:11',
            'brand' => 'nullable',
            'major_category_id' => 'required|exists:major_categories,id',
            'minor_category_id' => 'required|exists:minor_categories,id',
            'voucher' => 'nullable',
            'receipt' => 'nullable',
            'quantity' => 'required',
            //if any of tag_number and tag_number_old is not null, then is_old_asset is true else false
            'is_old_asset' =>  ['required','boolean', function ($attribute, $value, $fail) {
                if ($value == 1) {
                    if (request()->tag_number == null && request()->tag_number_old == null) {
                        $fail('Either tag number or tag number old is required');
                    }
                }
            }],
            'asset_status_id' => 'required|exists:asset_statuses,id',
            'depreciation_status_id' => 'required|exists:depreciation_statuses,id',
            'cycle_count_status_id' => 'required|exists:cycle_count_statuses,id',
            'movement_status_id' => 'required|exists:movement_statuses,id',
            'depreciation_method' => 'required',
            'acquisition_date' => ['required', 'date_format:Y-m-d', 'date'],
            'acquisition_cost' => ['required', 'numeric'],
            'scrap_value' => ['required', 'numeric'],
            'original_cost' => ['required', 'numeric'],
            'accumulated_cost' => ['nullable', 'numeric'],
            'care_of' => 'nullable',
            'age' => 'required|numeric',
//            'end_depreciation' => 'required|date_format:Y-m',
            'depreciation_per_year' => ['nullable', 'numeric'],
            'depreciation_per_month' => ['nullable', 'numeric'],
            'remaining_book_value' => ['nullable', 'numeric'],
            'release_date' => ['required', 'date_format:Y-m'],
//            'start_depreciation' => ['required', 'date_format:Y-m'],
            'department_id' => 'required|exists:departments,id',
            'account_title_id' => 'required|exists:account_titles,id',
        ];
    }


    function messages(): array
    {
        return [
            'sub_capex_id.exists' => 'Sub capex is invalid',
            'tag_number.required' => 'Tag number is required',
            'tag_number.max' => 'Tag number must not exceed 13 characters',
            'tag_number_old.required' => 'Tag number old is required',
            'tag_number_old.max' => 'Tag number old must not exceed 13 characters',
            'asset_description.required' => 'Asset description is required',
            'type_of_request_id.required' => 'Type of request is required',
            'asset_specification.required' => 'Asset specification is required',
            'accountability.required' => 'Accountability is required',
            'accountable.required_if' => 'Accountable is required',
            'cellphone_number.numeric' => 'Cellphone number must be numeric',
            'cellphone_number.digits' => 'Cellphone number must be 11 digits',
            'major_category_id.required' => 'Major category is required',
            'minor_category_id.required' => 'Minor category is required',
            'quantity.required' => 'Quantity is required',
            'is_old_asset.required' => 'Is old asset is required',
            'asset_status_id.required' => 'Asset status is required',
            'depreciation_status_id.required' => 'Depreciation status is required',
            'cycle_count_status_id.required' => 'Cycle count status is required',
            'movement_status_id.required' => 'Movement status is required',
            'depreciation_method.required' => 'Depreciation method is required',
            'acquisition_date.required' => 'Acquisition date is required',
            'acquisition_date.date_format' => 'Acquisition date must be in Y-m-d format',
            'acquisition_date.date' => 'Acquisition date must be a valid date',
            'acquisition_cost.required' => 'Acquisition cost is required',
            'acquisition_cost.numeric' => 'Acquisition cost must be numeric',
            'scrap_value.required' => 'Scrap value is required',
            'scrap_value.numeric' => 'Scrap value must be numeric',
            'original_cost.required' => 'Original cost is required',
            'original_cost.numeric' => 'Original cost must be numeric',
            'accumulated_cost.numeric' => 'Accumulated cost must be numeric',
            'age.required' => 'Age is required',
            'age.numeric' => 'Age must be numeric',
            'end_depreciation.required' => 'End depreciation is required',
            'end_depreciation.date_format' => 'End depreciation must be in Y-m format',
            'depreciation_per_year.numeric' => 'Depreciation per year must be numeric',
            'depreciation_per_month.numeric' => 'Depreciation per month must be numeric',
            'remaining_book_value.numeric' => 'Remaining book value must be numeric',
            'release_date.required' => 'Release date is required',
            'release_date.date_format' => 'Release date must be in Y-m format',
            'start_depreciation.required' => 'Start depreciation is required',
            'start_depreciation.date_format' => 'Start depreciation must be in Y-m format',
            'department_id.required' => 'Department is required',
            'account_title_id.required' => 'Account title is required',
        ];
    }

}
