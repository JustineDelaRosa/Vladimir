<?php

namespace App\Imports;

use App\Models\AccountTitle;
use App\Models\Capex;
use App\Models\Company;
use App\Models\Division;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\Department;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use App\Models\Status\AssetStatus;
use App\Models\Status\CycleCountStatus;
use App\Models\Status\DepreciationStatus;
use App\Models\Status\MovementStatus;
use App\Models\SubCapex;
use App\Models\TypeOfRequest;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use function PHPUnit\Framework\isEmpty;

class MasterlistImport extends DefaultValueBinder implements
    ToCollection,
    WithHeadingRow,
    WithCustomValueBinder,
    WithStartRow,
    WithCalculatedFormulas
{
    use Importable;

    function headingRow(): int
    {
        return 1;
    }

    public function startRow(): int
    {
        return 2;
    }

    /**
     * @throws Exception
     */
    public function bindValue(Cell $cell, $value): bool
    {

        if ($cell->getColumn() == 'V') {
            $cell->setValueExplicit(Date::excelToDateTimeObject($value)->format('Y-m-d'), DataType::TYPE_STRING);
            return true;
        }
//          elseif ($cell->getColumn() == 'AB') {
//            $cell->setValueExplicit(Date::excelToDateTimeObject($value)->format('Y-m'), DataType::TYPE_STRING);
//            return true;
//        } elseif ($cell->getColumn() == 'AG') {
//            $cell->setValueExplicit(Date::excelToDateTimeObject($value)->format('Y'), DataType::TYPE_STRING);
//            return true;
//        }

        // else return default behavior
        return parent::bindValue($cell, $value);
    }

    public function collection(Collection $collections)
    {
        Validator::make($collections->toArray(), $this->rules($collections->toArray()), $this->messages())->validate();

        foreach ($collections as $collection) {
            $majorCategoryId = $this->getMajorCategoryId($collection['major_category']);
            $minorCategoryId = $this->getMinorCategoryId($collection['minor_category'], $majorCategoryId);
            //pass est_useful_life to formula and fixed asset
            $est_useful_life = $this->getEstUsefulLife($majorCategoryId);
            $fixedAsset = $this->createFixedAsset($collection, $majorCategoryId, $minorCategoryId, $est_useful_life);
            $this->createFormula($fixedAsset, $collection, $est_useful_life);
        }
    }

    private function getEstUsefulLife($majorCategoryId)
    {
        $majorCategory = MajorCategory::withTrashed()
            ->where('major_category_name', $majorCategoryId)
            ->first();

        return $majorCategory ? $majorCategory->est_useful_life : null;
    }

    private function getMajorCategoryId($majorCategoryName)
    {
        $majorCategory = MajorCategory::withTrashed()
            ->where('major_category_name', $majorCategoryName)
            ->first();

        return $majorCategory ? $majorCategory->id : null;
    }

    private function getMinorCategoryId($minorCategoryName, $majorCategoryId)
    {
        $minorCategory = MinorCategory::withTrashed()
            ->where('minor_category_name', $minorCategoryName)
            ->where('major_category_id', $majorCategoryId)
            ->first();
        //return two valie
        return $minorCategory ? $minorCategory->id : null;
    }

    private function createFixedAsset($collection, $majorCategoryId, $minorCategoryId, $est_useful_life)
    {
        // Check if necessary IDs exist before creating FixedAsset
        if ($majorCategoryId == null || $minorCategoryId == null) {
            throw new Exception('Unable to create FixedAsset due to missing Major/Minor category ID.');
        }
        //get est_useful_life from major category


        return FixedAsset::create([
            'capex_id' => Capex::where('capex', $collection['capex'])->first()->id ?? null,
            'sub_capex_id' => SubCapex::where('sub_capex', $collection['sub_capex'])->first()->id ?? null,
            'vladimir_tag_number' => $this->vladimirTagGenerator(),
            'tag_number' => $collection['tag_number'] ?? '-',
            'tag_number_old' => $collection['tag_number_old'] ?? '-',
            'asset_description' => ucwords(strtolower($collection['description'])),
            'type_of_request_id' => TypeOfRequest::where('type_of_request_name', ($collection['type_of_request']))->first()->id,
            'asset_specification' => ucwords(strtolower($collection['additional_description'])),
            'accountability' => ucwords(strtolower($collection['accountability'])),
            'accountable' => ucwords(strtolower($collection['accountable'] ?? '-')),
            'cellphone_number' => $collection['cellphone_number'],
            'brand' => ucwords(strtolower($collection['brand'])),
            'division_id' => Division::where('division_name', $collection['division'])->first()->id,
            'major_category_id' => $majorCategoryId,
            'minor_category_id' => $minorCategoryId,
            'voucher' => ucwords(strtolower($collection['voucher'])),
            //check for unnecessary spaces and trim them to one space only
            'receipt' => preg_replace('/\s+/', ' ', ucwords(strtolower($collection['receipt']))),
            'quantity' => $collection['quantity'],
            'depreciation_method' => $collection['depreciation_method'] == 'STL' ? strtoupper($collection['depreciation_method']) : ucwords(strtolower($collection['depreciation_method'])),
            'acquisition_date' => $collection['acquisition_date'],
            'acquisition_cost' => $collection['acquisition_cost'],
            'asset_status_id' => AssetStatus::where('asset_status_name', $collection['asset_status'])->first()->id,
            'cycle_count_status_id' => CycleCountStatus::where('cycle_count_status_name', $collection['cycle_count_status'])->first()->id,
            'depreciation_status_id' => DepreciationStatus::where('depreciation_status_name', $collection['depreciation_status'])->first()->id,
            'movement_status_id' => MovementStatus::where('movement_status_name', $collection['movement_status'])->first()->id,
            'is_old_asset' => $collection['tag_number'] != '-' || $collection['tag_number_old'] != '-',
            'care_of' => ucwords(strtolower($collection['care_of'])),
            'company_id' => Company::where('company_code', $collection['company_code'])->first()->id,
            'department_id' => Department::where('department_code', $collection['department_code'])->first()->id,
            'location_id' => Location::where('location_code', $collection['location_code'])->first()->id,
            'account_id' => AccountTitle::where('account_title_code', $collection['account_code'])->first()->id,
        ]);
    }

    private function createFormula($fixedAsset, $collection, $est_useful_life)
    {
        $fixedAsset->formula()->create([
            'depreciation_method' => $collection['depreciation_method'] == 'STL' ? strtoupper($collection['depreciation_method']) : ucwords(strtolower($collection['depreciation_method'])),
            'acquisition_date' => $collection['acquisition_date'],
            'acquisition_cost' => $collection['acquisition_cost'],
            'scrap_value' => $collection['scrap_value'],
            'original_cost' => $collection['original_cost'],
            'accumulated_cost' => $collection['accumulated_cost'],
            'age' => $collection['age'],
            'end_depreciation' => Carbon::parse(substr_replace($collection['start_depreciation'], '-', 4, 0))->addYears(floor($est_useful_life))->addMonths(floor(($est_useful_life - floor($est_useful_life)) * 12) - 1)->format('Y-m'),
            'depreciation_per_year' => $collection['depreciation_per_year'],
            'depreciation_per_month' => $collection['depreciation_per_month'],
            'remaining_book_value' => $collection['remaining_book_value'],
            'release_date' => Carbon::parse(substr_replace($collection['start_depreciation'], '-', 4, 0))->subMonth()->format('Y-m'),
            'start_depreciation' => substr_replace($collection['start_depreciation'], '-', 4, 0),
        ]);
    }

//Todo: if the id is trashed then what should i do with the id?
    function rules($collection): array
    {
        $collections = collect($collection);
        return [
            '*.capex' => ['nullable', function ($attribute, $value, $fail) use ($collections) {
                if ($value == null || $value == '-') {
                    return true;
                }
                $capex = Capex::where('capex', $value)->first();
                if (!$capex) {
                    $fail('Capex does not exist');
                }
            }],
            '*.sub_capex' => ['nullable', 'regex:/^.+$/', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections->toArray()));
                $capexValue = $collections[$index]['capex'];
                $typeOfRequest = $collections[$index]['type_of_request'];
                //if a type of request is not CAPEX, then capex and sub capex should be empty
                if ($typeOfRequest != 'CAPEX') {
                    if ($value != '-') {
                        $fail('Capex and Sub Capex should be empty');
                        return true;
                    }
                }

                //todo:check for other way to check if the value is null or '-'
                if ($capexValue != '-') {
                    if ($value == '-') {
                        $fail('Sub Capex is required');
                        return true;
                    }
                } else {
                    if ($value != '-') {
                        $fail('Sub Capex should be empty');
                        return true;
                    }
                }

                $capex = Capex::where('capex', $capexValue)->first();
                if ($capex) {
                    $subCapex = SubCapex::withTrashed()->where('capex_id', $capex->id)->where('sub_capex', $value)->first();
                    if (!$subCapex) {
                        $fail('Sub capex does not exist in the capex');
                    }
                }
            }],
//            '*.project_name' => ['nullable', function ($attribute, $value, $fail) use ($collections) {
//                //check if the value of project name is null or '-'
//                if ($value == null || $value == '-') {
//                    return true;
//                }
//                //check in the capex table if the project name is the same with the capex
//                $index = array_search($attribute, array_keys($collections->toArray()));
//                $capex = Capex::where('capex', $collections[$index]['capex'])->first();
//                if ($capex) {
//                    $project = $capex->where('project_name', $value)->first();
//                    if (!$project) {
//                        $fail('Project name does not exist in the capex');
//                    }
//                }
//            }],
//            '*.sub_project' => ['nullable', function ($attribute, $value, $fail) use ($collections) {
//                if ($value == '' || $value == '-') {
//                    return true;
//                }
//                $index = array_search($attribute, array_keys($collections->toArray()));
//                $subCapexValue = $collections[$index]['sub_capex'];
//                if($subCapexValue != '' && $subCapexValue != '-'){
//                    if ($value == '' || $value == '-') {
//                        $fail('Sub Project is required');
//                        return true;
//                    }
//                }
//                //check in the sub capex table if the subproject is the same with the capex
//                $subCapex = SubCapex::where('sub_capex', $subCapexValue)->first();
//                if ($subCapex) {
//                    $subProject = $subCapex->where('sub_project', $value)->first();
//                    if (!$subProject) {
//                        $fail('Sub project does not exist in the sub capex');
//                    }
//                }
//            }],
            '*.tag_number' => ['required', 'regex:/^([0-9-]{6,13}|-)$/', function ($attribute, $value, $fail) use ($collections) {
                $duplicate = $collections->where('tag_number', $value)->where('tag_number', '!=', '-')->count();
                if ($duplicate > 1) {
                    $fail('Tag number in row ' . $attribute[0] . ' is not unique');
                }
                //check in a database
                $fixed_asset = FixedAsset::withTrashed()->where('tag_number', $value)->where('tag_number', '!=', '-')->first();
                if ($fixed_asset) {
                    $fail('Tag number already exists');
                }
            }],
            '*.tag_number_old' => ['required', 'regex:/^([0-9-]{6,13}|-)$/', function ($attribute, $value, $fail) use ($collections) {
                $duplicate = $collections->where('tag_number_old', $value)->where('tag_number_old', '!=', '-')->count();
                if ($duplicate > 1) {
                    $fail('Tag number old in row ' . $attribute[0] . ' is not unique');
                }
                //check in a database
                $fixed_asset = FixedAsset::withTrashed()->where('tag_number_old', $value)->where('tag_number_old', '!=', '-')->first();
                if ($fixed_asset) {
                    $fail('Tag number old already exists');
                }
            }],
            '*.description' => 'required', //todo: changing asset_description to description
            '*.type_of_request' => 'required|exists:type_of_requests,type_of_request_name',
            '*.additional_description' => 'required', //Todo changing asset_specification to Additional Description
            '*.accountability' => 'required',
            //required if accountability is personal issued and if the accountability is common it should be empty
            '*.accountable' => ['required_if:*.accountability,Personal Issued',
                function ($attribute, $value, $fail) use ($collections) {
                    $index = array_search($attribute, array_keys($collections->toArray()));
                    $accountability = $collections[$index]['accountability'];
                    if ($accountability == 'Common') {
                        if ($value != '') {
                            $fail('Accountable should be empty');
                        }
                    }
                }],
            '*.cellphone_number' => 'required',
            '*.brand' => 'required',
            '*.division' => ['required', function ($attribute, $value, $fail) {
                $division = Division::withTrashed()->where('division_name', $value)->first();
                if (!$division) {
                    $fail('Division does not exists');
                }
            }],
            '*.major_category' => [
                'required', 'exists:major_categories,major_category_name'
//                function ($attribute, $value, $fail) use ($collections) {
//                    $major_category = MajorCategory::withTrashed()->where('major_category_name', $value)->first();
//                    if (!$major_category) {
//                        $fail('Major Category does not exists');
//                    }
//                }
            ],
            '*.minor_category' => ['required', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections->toArray()));
//                $status = $collections[$index]['asset_status'];
                $major_category = $collections[$index]['major_category'];
                $major_category = MajorCategory::withTrashed()->where('major_category_name', $major_category)->first()->id ?? 0;
                $minor_category = MinorCategory::withTrashed()->where('minor_category_name', $value)
                    ->where('major_category_id', $major_category)->first();

//                if($minor_category->trashed()){
//                    $fail('Minor Category does not exist');
//                }
                if (!$minor_category) {
                    $fail('Minor Category does not exist');
                }

            }],
            '*.voucher' => 'required',
            '*.receipt' => 'required',
            '*.quantity' => 'required|numeric',
            '*.depreciation_method' => 'required|in:STL,One Time',
            '*.acquisition_date' => ['required', 'string', 'date_format:Y-m-d', 'date'],
            '*.acquisition_cost' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', function ($attribute, $value, $fail) {
                if ($value < 0) {
                    $fail('Acquisition cost must not be negative');
                }
            }],
            '*.scrap_value' => ['required',],
            '*.original_cost' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', function ($attribute, $value, $fail) {
                if ($value < 0) {
                    $fail('Original cost must not be negative');
                }
            }],
            '*.accumulated_cost' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', function ($attribute, $value, $fail) {
                if ($value < 0) {
                    $fail('Accumulated cost must not be negative');
                }
            }],
            '*.asset_status' => 'required|exists:asset_statuses,asset_status_name',
            '*.depreciation_status' => 'required|exists:depreciation_statuses,depreciation_status_name',
            '*.cycle_count_status' => 'required|exists:cycle_count_statuses,cycle_count_status_name',
            '*.movement_status' => 'required|exists:movement_statuses,movement_status_name',
            '*.care_of' => 'required',
            '*.age' => 'nullable',
            '*.end_depreciation' => 'required',
            '*.depreciation_per_year' => ['required'],
            '*.depreciation_per_month' => ['required'],
            '*.remaining_book_value' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', function ($attribute, $value, $fail) {
                if ($value < 0) {
                    $fail('Remaining book value must not be negative');
                }
            }],
            '*.start_depreciation' => ['required'],
            '*.company_code' => 'required|exists:companies,company_code',
            '*.department_code' => 'required|exists:departments,department_code',
            '*.location_code' => 'required|exists:locations,location_code',
            '*.account_code' => 'required|exists:account_titles,account_title_code',
        ];
    }

    function messages(): array
    {
        return [
            '*.capex_id.exists' => 'Capex does not exist',
            '*.vladimir_tag_number.required' => 'Vladimir Tag Number is required',
            '*.tag_number.required' => 'Tag Number is required',
            '*.tag_number_old.required' => 'Tag Number Old is required',
            '*.asset_description.required' => 'Description is required',
            '*.type_of_request.required' => 'Type of Request is required',
            '*.type_of_request.in' => 'Invalid Type of Request',
            '*.additional_description.required' => 'Additional Description is required',
            '*.accountability.required' => 'Accountability is required',
            '*.accountable.required_if' => 'Accountable is required',
            '*.cellphone_number.required' => 'Cellphone Number is required',
            '*.brand.required' => 'Brand is required',
            '*.division.required' => 'Division is required',
            '*.division.exists' => 'Division does not exist',
            '*.major_category.required' => 'Major Category is required',
            '*.major_category.exists' => 'Major Category does not exist',
            '*.minor_category.required' => 'Minor Category is required',
            '*.voucher.required' => 'Voucher is required',
            '*.receipt.required' => 'Receipt is required',
            '*.quantity.required' => 'Quantity is required',
            '*.quantity.numeric' => 'Quantity must be a number',
            '*.depreciation_method.required' => 'Depreciation Method is required',
            '*.depreciation_method.in' => 'The selected depreciation method is invalid.',
            '*.acquisition_date.required' => 'Acquisition Date is required',
            '*.acquisition_cost.required' => 'Acquisition Cost is required',
            '*.scrap_value.required' => 'Scrap Value is required',
            '*.original_cost.required' => 'Original Cost is required',
            '*.accumulated_cost.required' => 'Accumulated Cost is required',
            '*.asset_status.required' => 'Status is required',
            '*.asset_status.in' => 'The selected status is invalid.',
            '*.depreciation_status.required' => 'Depreciation Status is required',
            '*.depreciation_status.in' => 'The selected depreciation status is invalid.',
            '*.cycle_count_status.required' => 'Cycle Count Status is required',
            '*.cycle_count_status.in' => 'The selected cycle count status is invalid.',
            '*.movement_status.required' => 'Movement Status is required',
            '*.movement_status.in' => 'The selected movement status is invalid.',
            '*.care_of.required' => 'Care Of is required',
            '*.age.required' => 'Age is required',
            '*.end_depreciation.required' => 'End Depreciation is required',
            '*.depreciation_per_year.required' => 'Depreciation Per Year is required',
            '*.depreciation_per_month.required' => 'Depreciation Per Month is required',
            '*.remaining_book_value.required' => 'Remaining Book Value is required',
            '*.start_depreciation.required' => 'Start Depreciation is required',
            '*.start_depreciation.date_format' => 'Invalid date format',
            '*.company_code.required' => 'Company Code is required',
            '*.company_code.exists' => 'Company Code does not exist',
            '*.department_code.required' => 'Department Code is required',
            '*.department_code.exists' => 'Department Code does not exist',
            '*.location_code.required' => 'Location Code is required',
            '*.location_code.exists' => 'Location Code does not exist',
            '*.account_code.required' => 'Account Code is required',
            '*.account_code.exists' => 'Account Code does not exist',
        ];

    }


//  GENERATING VLADIMIR TAG NUMBER
    public function vladimirTagGenerator(): string
    {
        $generatedEan13Result = $this->generateEan13();
        // Check if the generated number is a duplicate or already exists in the database
        while ($this->checkDuplicateEan13($generatedEan13Result)) {
            $generatedEan13Result = $this->generateEan13();
        }

        return $generatedEan13Result;
    }

    public function generateEan13(): string
    {
        $date = date('ymd');
        static $lastRandom = 0;
        do {
            $random = mt_rand(1, 9) . mt_rand(1000, 9999);
        } while ($random === $lastRandom);
        $lastRandom = $random;

        $number = "5$date$random";

        if (strlen($number) !== 12) {
            return 'Invalid Number';
        }

        //Calculate checkDigit
        $checkDigit = $this->calculateCheckDigit($number);

        $ean13Result = $number . $checkDigit;

        return $ean13Result;
    }

    public function calculateCheckDigit(string $number): int
    {
        $evenSum = $this->calculateEvenSum($number);
        $oddSum = $this->calculateOddSum($number);

        $totalSum = $evenSum + $oddSum;
        $remainder = $totalSum % 10;
        $checkDigit = ($remainder === 0) ? 0 : 10 - $remainder;

        return $checkDigit;
    }

    public function calculateEvenSum(string $number): int
    {
        $evenSum = 0;
        for ($i = 1; $i < 12; $i += 2) {
            $evenSum += (int)$number[$i];
        }
        return $evenSum * 3;
    }

    public function calculateOddSum(string $number): int
    {
        $oddSum = 0;
        for ($i = 0; $i < 12; $i += 2) {
            $oddSum += (int)$number[$i];
        }
        return $oddSum;
    }

    public function checkDuplicateEan13(string $ean13Result): bool
    {
        $generated = [];
        return in_array($ean13Result, $generated) || FixedAsset::where('vladimir_tag_number', $ean13Result)->exists();
    }

}
