<?php

namespace App\Imports;

use App\Models\AccountTitle;
use App\Models\Company;
use App\Models\Division;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\Department;
use App\Models\Formula;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Contracts\Queue\ShouldQueue;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class MasterlistImport extends DefaultValueBinder implements ToCollection, WithHeadingRow, WithCustomValueBinder, WithChunkReading, ShouldQueue, WithStartRow
{
    function headingRow(): int
    {
        return 1;
    }

    public function chunkSize(): int
    {
        return 200;
    }

    public function startRow(): int
    {
        return 2;
    }

    public function batchSize(): int
    {
        return 150;
    }

    public function bindValue(Cell $cell, $value): bool
    {

        if ($cell->getColumn() == 'U') {
            $cell->setValueExplicit(Date::excelToDateTimeObject($value)->format('Y-m-d'), DataType::TYPE_STRING);
            return true;
        } elseif ($cell->getColumn() == 'AC') {
            $cell->setValueExplicit(Date::excelToDateTimeObject($value)->format('Y-m'), DataType::TYPE_STRING);
            return true;
        }

        // else return default behavior
        return parent::bindValue($cell, $value);
    }




    /**
     * @param Collection $collections
     */
    public function collection(Collection $collections)
    {

        Validator::make(
            $collections->toArray(),
            [
                '*.capex' => 'required',
                '*.project_name' => 'required',
                '*.vladimir_tag_number' => ['required', Rule::unique('fixed_assets', 'vladimir_tag_number')],
                '*.tag_number' => ['required', Rule::unique('fixed_assets', 'tag_number')],
                '*.tag_number_old' => ['required', Rule::unique('fixed_assets', 'tag_number_old')],
                '*.asset_description' => 'required',
                '*.type_of_request' => 'required',
                '*.asset_specification' => 'required',
                '*.accountability' => 'required',
                '*.accountable' => 'required',
                '*.cellphone_number' => 'required',
                '*.brand' => 'required',
                '*.division' => ['required', function ($attribute, $value, $fail) {
                    $division = Division::withTrashed()->where('division_name', $value)->first();
                    if (!$division) {
                        $fail('Division does not exists');
                    }
                }],
                '*.major_category' => [
                    'required', function ($attribute, $value, $fail) use ($collections) {
                        $index = array_search($attribute, array_keys($collections->toArray()));
                        $division = $collections[$index]['division'];
                        $major_category = MajorCategory::withTrashed()->where('major_category_name', $value)
                            ->where('division_id', Division::withTrashed()->where('division_name', $division)->first()->id ?? 0)->first();
                        if (!$major_category) {
                            $fail('Major Category does not exists');
                        }
                    }
                ],
                '*.minor_category' => ['required', function ($attribute, $value, $fail) use ($collections) {
                    $index = array_search($attribute, array_keys($collections->toArray()));
                    $division = $collections[$index]['division'];
                    $major_category = $collections[$index]['major_category'];
                    $major_category = MajorCategory::withTrashed()->where('major_category_name', $major_category)
                        ->where('division_id', Division::withTrashed()->where('division_name', $division)->first()->id ?? 0)->first()->id ?? 0;
                    $minor_category = MinorCategory::withTrashed()->where('minor_category_name', $value)
                        ->where('major_category_id', $major_category)->first();
                    if (!$minor_category) {
                        $fail('Minor Category does not exists');
                    }
                }],
                '*.voucher' => 'required',
                '*.receipt' => 'required',
                '*.quantity' => 'required',
                '*.depreciation_method' => 'required',
                '*.est_useful_life' => 'required',
                '*.acquisition_date' => ['required', 'string', 'date_format:Y-m-d', 'date'],
                '*.acquisition_cost' => 'required',
                '*.scrap_value' => 'required',
                '*.original_cost' => 'required',
                '*.accumulated_cost' => 'required',
                '*.status' => 'required',
                '*.care_of' => 'required',
                '*.age' => 'required',
                '*.end_depreciation' => 'required|date_format:Y-m',
                '*.depreciation_per_year' => 'required',
                '*.depreciation_per_month' => 'required',
                '*.remaining_book_value' => 'required',
                '*.start_depreciation' => 'required',
                '*.company_code' => 'required|exists:companies,company_code',
                '*.company' => 'required|exists:companies,company_name',
                '*.department_code' => 'required|exists:departments,department_code',
                '*.department' => 'required|exists:departments,department_name',
                '*.location_code' => 'required|exists:locations,location_code',
                '*.location' => 'required|exists:locations,location_name',
                '*.account_code' => 'required|exists:account_titles,account_title_code',
                '*.account_title' => 'required|exists:account_titles,account_title_name',
            ],
            [
                '*.capex.required' => 'Capex is required',
                '*.project_name.required' => 'Project Name is required',
                '*.vladimir_tag_number.required' => 'Vladimir Tag Number is required',
                '*.tag_number.required' => 'Tag Number is required',
                '*.tag_number_old.required' => 'Tag Number Old is required',
                '*.asset_description.required' => 'Description is required',
                '*.type_of_request.required' => 'Type of Request is required',
                '*.asset_specification.required' => 'Additional Description is required',
                '*.accountability.required' => 'Accountability is required',
                '*.accountable.required' => 'accountable is required',
                '*.cellphone_number.required' => 'Cellphone Number is required',
                '*.brand.required' => 'Brand is required',
                '*.division.required' => 'Division is required',
                '*.division.exists' => 'Division does not exist',
                '*.major_category.required' => 'Major Category is required',
                '*.minor_category.required' => 'Minor Category is required',
                '*.voucher.required' => 'Voucher is required',
                '*.receipt.required' => 'Receipt is required',
                '*.quantity.required' => 'Quantity is required',
                '*.depreciation_method.required' => 'Depreciation Method is required',
                '*.est_useful_life.required' => 'Est Useful Life is required',
                '*.acquisition_date.required' => 'Acquisition Date is required',
                '*.acquisition_cost.required' => 'Acquisition Cost is required',
                '*.scrap_value.required' => 'Scrap Value is required',
                '*.original_cost.required' => 'Original Cost is required',
                '*.accumulated_cost.required' => 'Accumulated Cost is required',
                '*.status.required' => 'Status is required',
                '*.care_of.required' => 'Care Of is required',
                '*.age.required' => 'Age is required',
                '*.end_depreciation.required' => 'End Depreciation is required',
                '*.depreciation_per_year.required' => 'Depreciation Per Year is required',
                '*.depreciation_per_month.required' => 'Depreciation Per Month is required',
                '*.remaining_book_value.required' => 'Remaining Book Value is required',
                '*.start_depreciation.required' => 'Start Depreciation is required',
                '*.company_code.required' => 'Company Code is required',
                '*.company_code.exists' => 'Company Code does not exist',
                '*.company.required' => 'Company is required',
                '*.company.exists' => 'Company does not exist',
                '*.department_code.required' => 'Department Code is required',
                '*.department_code.exists' => 'Department Code does not exist',
                '*.department.required' => 'Department is required',
                '*.department.exists' => 'Department does not exist',
                '*.location_code.required' => 'Location Code is required',
                '*.location_code.exists' => 'Location Code does not exist',
                '*.location.required' => 'Location is required',
                '*.location.exists' => 'Location does not exist',
                '*.account_code.required' => 'Account Code is required',
                '*.account_code.exists' => 'Account Code does not exist',
                '*.account_title.required' => 'Account Title is required',
                '*.account_title.exists' => 'Account Title does not exist',

            ]
        )->validate();


        foreach ($collections as $collection) {


            // Check if the division exists
            $division = Division::withTrashed()->where('division_name', $collection['division'])->first();
            if ($division) {
                $divisionId = $division->id;

                // Check if the major category exists in this division
                $majorCategory = MajorCategory::withTrashed()->where('major_category_name', $collection['major_category'])
                    ->where('division_id', $divisionId)
                    ->first();
                if ($majorCategory) {
                    $majorCategoryId = $majorCategory->id;

                    // Check if the minor category exists in this major category and division
                    $minorCategory = MinorCategory::withTrashed()->where('minor_category_name', $collection['minor_category'])
                        ->where('major_category_id', $majorCategoryId)
                        ->first();
                    if ($minorCategory) {
                        $minorCategoryId = $minorCategory->id;
                    }
                }
            }


            // Create the Masterlist with the obtained ids or null if not found
            FixedAsset::create([
                'capex' => $collection['capex'],
                'project_name' => $collection['project_name'],
                'vladimir_tag_number' => $collection['vladimir_tag_number'],
                'tag_number' => $collection['tag_number'],
                'tag_number_old' => $collection['tag_number_old'],
                'asset_description' => $collection['asset_description'],
                'type_of_request' => $collection['type_of_request'],
                'asset_specification' => $collection['asset_specification'],
                'accountability' => $collection['accountability'],
                'accountable' => $collection['accountable'],
                'cellphone_number' => $collection['cellphone_number'],
                'brand' => $collection['brand'],
                'division_id' => $divisionId,
                'major_category_id' => $majorCategoryId,
                'minor_category_id' => $minorCategoryId,
                'voucher' => $collection['voucher'],
                'receipt' => $collection['receipt'],
                'quantity' => $collection['quantity'],
                'depreciation_method' => $collection['depreciation_method'],
                'est_useful_life' => $collection['est_useful_life'],
                'acquisition_date' => $collection['acquisition_date'],
                'acquisition_cost' => $collection['acquisition_cost'],
                'is_active' => $collection['status'],
                'care_of' => $collection['care_of'],
                'company_id' => Company::where('company_code', $collection['company_code'])->first()->id,
                'company_name' => $collection['company'],
                'department_id' => Department::where('department_code', $collection['department_code'])->first()->id,
                'department_name' => $collection['department'],
                'location_id' => Location::where('location_code', $collection['location_code'])->first()->id,
                'location_name' => $collection['location'],
                'account_id' => AccountTitle::where('account_title_code', $collection['account_code'])->first()->id,
                'account_title' => $collection['account_title'],
            ]);



            Formula::create([
                'fixed_asset_id' => FixedAsset::where('vladimir_tag_number', $collection['vladimir_tag_number'])->first()->id,
                'depreciation_method' => $collection['depreciation_method'],
                'est_useful_life' => $collection['est_useful_life'],
                'acquisition_date' => $collection['acquisition_date'],
                'acquisition_cost' => $collection['acquisition_cost'],
                'scrap_value' => $collection['scrap_value'],
                'original_cost' => $collection['original_cost'],
                'accumulated_cost' => $collection['accumulated_cost'],
                'age' => $collection['age'],
                'end_depreciation' => $collection['end_depreciation'],
                'depreciation_per_year' => $collection['depreciation_per_year'],
                'depreciation_per_month' => $collection['depreciation_per_month'],
                'remaining_book_value' => $collection['remaining_book_value'],
                'start_depreciation' => $collection['start_depreciation'],
            ]);
        }
    }
}