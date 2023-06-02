<?php

namespace App\Http\Controllers;

use App\Exports\MasterlistExport;
use App\Models\FixedAsset;
use Illuminate\Http\Request;

class MasterlistExportController extends Controller
{
    public function export(Request $request)
    {
        $validated = $request->validate([
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date',
        ]);
//        $filename = $request->get('filename');
//        //ternary if empty, the default filename is Fixed_Asset_Date
//        $filename = $filename == null ? 'Fixed_Asset'. '_' . date('Y-m-d') :
//                    str_replace(' ', '_', $filename) . '_' . date('Y-m-d');
        $search = $request->get('search');
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');

        if($startDate != null && $endDate != null){
            $fixed_assets = FixedAsset::withTrashed()->with([
                    'formula' => function ($query) {
                        $query->withTrashed();
                    },
                    'division' => function ($query) {
                        $query->withTrashed()->select('id', 'division_name');
                    },
                    'majorCategory' => function ($query) {
                        $query->withTrashed()->select('id', 'major_category_name');
                    },
                    'minorCategory' => function ($query) {
                        $query->withTrashed()->select('id', 'minor_category_name');
                    },
                ])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();
            return $this->refactorExport($fixed_assets);
        }

        $fixedAsset = FixedAsset::withTrashed()->with([
                'formula' => function ($query) {
                    $query->withTrashed();
                },
                'division' => function ($query) {
                    $query->withTrashed()->select('id', 'division_name');
                },
                'majorCategory' => function ($query) {
                    $query->withTrashed()->select('id', 'major_category_name');
                },
                'minorCategory' => function ($query) {
                    $query->withTrashed()->select('id', 'minor_category_name');
                },
            ])
            ->Where(function ($query) use ($search, $startDate, $endDate) {
                $query->Where('capex', 'LIKE', '%'.$search.'%')
                    ->orWhere('project_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('vladimir_tag_number', 'LIKE', '%'.$search.'%')
                    ->orWhere('tag_number', 'LIKE', '%'.$search.'%')
                    ->orWhere('tag_number_old', 'LIKE', '%'.$search.'%')
                    ->orWhere('type_of_request', 'LIKE', '%'.$search.'%')
                    ->orWhere('accountability', 'LIKE', '%'.$search.'%')
                    ->orWhere('accountable', 'LIKE', '%'.$search.'%')
                    ->orWhere('brand', 'LIKE', '%'.$search.'%')
                    ->orWhere('depreciation_method', 'LIKE', '%'.$search.'%');
                $query->orWhereHas('majorCategory', function ($query) use ($search) {
                    $query->where('major_category_name', 'LIKE', '%'.$search.'%');
                });
                $query->orWhereHas('minorCategory', function ($query) use ($search) {
                    $query->where('minor_category_name', 'LIKE','%'.$search.'%');
                });
                $query->orWhereHas('division', function ($query) use ($search) {
                    $query->where('division_name', 'LIKE', '%{$search}%');
                });
                $query->orWhereHas('location', function ($query) use ($search) {
                    $query->where('location_name', 'LIKE', '%'.$search.'%');
                });
                $query->orWhereHas('company', function ($query) use ($search) {
                    $query->where('company_name', 'LIKE', '%'.$search.'%');
                });
                $query->orWhereHas('department', function ($query) use ($search) {
                    $query->where('department_name', 'LIKE', '%'.$search.'%');
                });
                $query->orWhereHas('accountTitle', function ($query) use ($search) {
                    $query->where('account_title_name', 'LIKE', '%'.$search.'%');
                });
            })
            ->orderBy('id', 'ASC')
            ->get();

        if($fixedAsset->count() == 0){
            return response()->json([
                'message' => 'No data found',
            ], 404);
        }
        return $this->refactorExport($fixedAsset);

        //      $fixedAsset = FixedAsset::query()
//            ->with([
//                'formula'=>function($query){
//                    $query->withTrashed();
//                },
//                'majorCategory'=>function($query){
//                    $query->withTrashed();
//                },
//                'minorCategory'=>function($query){
//                    $query->withTrashed();
//                },
//                'division'=>function($query){
//                    $query->withTrashed();
//                },
//            ])
//            ->when($search, function ($query, $search) {
//                return  $query->where('capex',$search)
//                    ->orWhere('project_name',$search)
//                    ->orWhere('vladimir_tag_number',$search)
//                    ->orWhere('tag_number',$search)
//                    ->orWhere('tag_number_old',$search)
//                    //->orWhere('asset_description',$search)
//                    ->orWhere('type_of_request',$search)
//                    ->orWhere('accountability',$search)
//                    ->orWhere('accountable',$search)
//                    ->orWhere('brand',$search)
//                    ->orWhere('depreciation_method',$search)
//                    ->orWhereHas('majorCategory', function ($query) use ($search) {
//                        $query->withTrashed()->where('major_category_name', $search);
//                    })
//                    ->orWhereHas('minorCategory', function ($query) use ($search) {
//                        $query->withTrashed()->where('minor_category_name',  $search );
//                    })
//                    ->orWhereHas('division', function ($query) use ($search) {
//                        $query->withTrashed()->where('division_name',  $search);
//                    })
//                    ->orWhereHas('company', function ($query) use ($search) {
//                        $query->where('company_name', $search);
//                    })
//                    ->orWhereHas('department', function ($query) use ($search) {
//                        $query->where('department_name', $search );
//                    })
//                    ->orWhereHas('location', function ($query) use ($search) {
//                        $query->where('location_name', $search);
//                    })
//                    ->orWhereHas('accountTitle', function ($query) use ($search) {
//                        $query->where('account_title_name', $search);
//                    });
//
//            })
//            ->withTrashed()
//            ->when($startDate, function ($query, $startDate) {
//                return $query->where('created_at', '>=', $startDate);
//            })
//            ->when($endDate, function ($query, $endDate) {
//                return $query->where('created_at', '<=', $endDate);
//            })
//            ->orderBy('id', 'ASC');
//
//        if($fixedAsset->count() == 0){
//            return response()->json([
//                'message' => 'No data found'
//            ], 404 );
//        }
        //$export = (new MasterlistExport($search, $startDate, $endDate))->download($filename . '.xlsx');
        // return $export;
    }

    public function refactorExport($fixedAssets): array
    {
        $fixed_assets_arr = [];
        foreach ($fixedAssets as $fixed_asset) {
            $fixed_assets_arr[] = [
                'id' => $fixed_asset->id,
                'capex' => $fixed_asset->capex,
                'project_name' => $fixed_asset->project_name,
                'vladimir_tag_number' => $fixed_asset->vladimir_tag_number,
                'tag_number' => $fixed_asset->tag_number,
                'tag_number_old' => $fixed_asset->tag_number_old,
                'asset_description' => $fixed_asset->asset_description,
                'type_of_request' => $fixed_asset->type_of_request,
                'asset_specification' => $fixed_asset->asset_specification,
                'accountability' => $fixed_asset->accountability,
                'accountable' => $fixed_asset->accountable,
                'brand' => $fixed_asset->brand,
                'division' => $fixed_asset->division->division_name,
                'major_category' => $fixed_asset->majorCategory->major_category_name,
                'minor_category' => $fixed_asset->minorCategory->minor_category_name,
                'voucher' => $fixed_asset->voucher,
                'receipt' => $fixed_asset->receipt,
                'quantity' => $fixed_asset->quantity,
                'depreciation_method' => $fixed_asset->depreciation_method,
                'est_useful_life' => $fixed_asset->est_useful_life,
                //                    'salvage_value' => $fixed_asset->salvage_value,
                'acquisition_date' => $fixed_asset->acquisition_date,
                'acquisition_cost' => $fixed_asset->acquisition_cost,
                'scrap_value' => $fixed_asset->formula->scrap_value,
                'original_cost' => $fixed_asset->formula->original_cost,
                'accumulated_cost' => $fixed_asset->formula->accumulated_cost,
                'status' => $fixed_asset->status,
                'care_of' => $fixed_asset->care_of,
                'age' => $fixed_asset->formula->age,
                'end_depreciation' => $fixed_asset->formula->end_depreciation,
                'depreciation_per_year' => $fixed_asset->formula->depreciation_per_year,
                'depreciation_per_month' => $fixed_asset->formula->depreciation_per_month,
                'remaining_book_value' => $fixed_asset->formula->remaining_book_value,
                'start_depreciation' => $fixed_asset->formula->start_depreciation,
                'company_code' => $fixed_asset->company->company_code,
                'company_name' => $fixed_asset->company->company_name,
                'department_code' => $fixed_asset->department->department_code,
                'department_name' => $fixed_asset->department->department_name,
                'location_code' => $fixed_asset->location->location_code,
                'location_name' => $fixed_asset->location->location_name,
                'account_title_code' => $fixed_asset->accountTitle->account_title_code,
                'account_title_name' => $fixed_asset->accountTitle->account_title_name
            ];
        }

        return $fixed_assets_arr;
    }

}
