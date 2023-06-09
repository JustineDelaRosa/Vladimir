<?php

namespace App\Http\Controllers;

use App\Models\CategoryList;
use Illuminate\Http\Request;
use App\Models\MinorCategory;
use App\Models\CategoryListTagMinorCategory;
use App\Http\Requests\CategoryList\CategoryListRequest;

class CategoryListController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $CategoryList = CategoryList::with('serviceProvider')
            ->with('majorCategory')
            ->with('categoryListTag.minorCategory')
            ->get();
        return $CategoryList;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CategoryListRequest $request)
    {
        $service_provider_id = $request->service_provider_id;
        $major_category_id = $request->major_category_id;
        $minor_category_id = array_unique($request->minor_category_id);
        $create = [];
        $categoryList = CategoryList::create([
            'service_provider_id' => $service_provider_id,
            'major_category_id' => $major_category_id,
            'is_active' => 1
        ]);

        foreach ($minor_category_id as $minor) {
            $MinorCategory = MinorCategory::find($minor);
            if ($MinorCategory) {
                $tagMinor = CategoryListTagMinorCategory::create([
                    'category_list_id' => $categoryList->id,
                    'minor_category_id' => $minor,
                    'is_active' => 1
                ]);
                $getMinorCategory = MinorCategory::where('id', $minor)->first();
                array_push($create, $getMinorCategory);
            }
        }
        return response()->json(['message' => 'Successfully Create', 'data' => $create], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $CategoryList = CategoryList::find($id);
        if (!$CategoryList) {
            return response()->json(['error' => 'Category List Route Not Found'], 404);
        }
        $getbyid = CategoryList::with('serviceProvider')
            ->with('majorCategory')
            ->with('categoryListTag.minorCategory')
            ->where('id', $id)->first();
        return $getbyid;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(CategoryListRequest $request, $id)
    {
        $service_provider_id = $request->service_provider_id;
        $major_category_id = $request->major_category_id;
        if (!CategoryList::find($id)) {
            return response()->json(['error' => 'Category List Route Not Found'], 404);
        }
        // if(CategoryList::where('id',$id)
        // ->where('service_provider_id', $service_provider_id)
        // ->where('major_category_id', $major_category_id)
        // ->exists()){
        //     return response()->json(['message' => 'No Changes'], 200);
        // }
        $update = CategoryList::where('id', $id)
            ->update([
                'service_provider_id' => $service_provider_id,
                'major_category_id' => $major_category_id
            ]);
        return response()->json(['message' => 'Successfully Updated!'], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function archived(CategoryListRequest $request, $id)
    {

        $status = $request->status;
        $CategoryList = CategoryList::query();
        if (!$CategoryList->withTrashed()->where('id', $id)->exists()) {
            return response()->json(['error' => 'Category List Route Not Found'], 404);
        }
        if (CategoryListTagMinorCategory::where('category_list_id', $id)->exists()) {
            if ($status == true) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                return response()->json(['message' => 'Unable to Archived!'], 409);
            }
        }

        if ($status == false) {
            if (!CategoryList::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $updateStatus = $CategoryList->where('id', $id)->update(['is_active' => false]);
                $CategoryList->where('id', $id)->delete();
                return response()->json(['message' => 'Successfully Deactived!'], 200);
            }
        }
        if ($status == true) {
            if (CategoryList::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $restoreUser = $CategoryList->withTrashed()->where('id', $id)->restore();
                $updateStatus = $CategoryList->update(['is_active' => true]);
                return response()->json(['message' => 'Successfully Activated!'], 200);
            }
        }
    }

    public function search(Request $request)
    {
        $search = $request->query('search');
        $limit = $request->query('limit');
        $page = $request->get('page');
        $status = $request->query('status');
        if ($status == NULL) {
            $status = 1;
        }
        if ($status == "active") {
            $status = 1;
        }
        if ($status == "deactivated") {
            $status = 0;
        }
        if ($status != "active" || $status != "deactivated") {
            $status = 1;
        }
        $CategoryList = CategoryList::with('serviceProvider')
            ->with('majorCategory')
            ->with('categoryListTag.minorCategory')
            ->withTrashed()
            ->where(function ($query) use ($status) {
                $query->where('is_active', 'LIKE', "%{$status}%");
            })
            ->where(function ($query) use ($search) {
                $query->where('id', 'like', "%{$search}%")
                    ->orWhereHas('serviceProvider', function ($q1) use ($search) {
                        $q1->where('service_provider_name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('majorCategory', function ($q2) use ($search) {
                        $q2->where('major_category_name', 'like', '%' . $search . '%')
                            ->orWhere('classification', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('categoryListTag.minorCategory', function ($q3) use ($search) {
                        $q3->where('minor_category_name', 'like', '%' . $search . '%')
                            ->orWhere('urgency_level', 'like', '%' . $search . '%');
                    });
            })

            ->orderby('created_at', 'DESC')
            ->paginate($limit);
        return $CategoryList;
    }

    public function UpdateMinorCategory(Request $request, $id)
    {
        $minor_category =  $request->minor_category;

        //    $CategoryListTagMinorCategory = CategoryListTagMinorCategory::find($id);
        //    if(!$CategoryListTagMinorCategory){
        //        return "Route Not Found!";
        //    }
        $notExists = [];
        $getMinorList = [];
        $minorList = CategoryListTagMinorCategory::where('category_list_id', $id)->get();
        foreach ($minorList as $List) {
            $minorLists = $List->minor_category_id;
            array_push($getMinorList, $minorLists);
        }


        $compare_MinorList = (array_diff($getMinorList, $minor_category));
        $implode = implode(", ", $compare_MinorList);
        $explode = array_map('intval', explode(', ', $implode));
        foreach ($minor_category as $minor) {

            if (!MinorCategory::where('id', $minor)->exists()) {
                array_push($notExists, $minor);
            } else {
                CategoryListTagMinorCategory::where('category_list_id', $id)->updateOrCreate(
                    [
                        'category_list_id' => $id,
                        'minor_category_id' => $minor,
                        'is_active' => 1
                    ],
                    [
                        'category_list_id' => $id,
                        'minor_category_id' => $minor
                    ]
                );
            }
        }

        foreach ($explode as $delete) {
            CategoryListTagMinorCategory::where('category_list_id', $id)->where('minor_category_id', $delete)->delete();
        }

        if (!empty($notExists)) {
            return response()->json([
                'message' => "Successfully Updated!",
                'minor_id_not_exist' => $notExists
            ]);
        } else {
            return response()->json([
                'message' => "Successfully Updated!"
            ]);
        }
    }
}
