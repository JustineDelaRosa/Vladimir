<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Imports\MasterlistImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MasterlistImportController extends Controller
{
    public function masterlistImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlx,xls,pdf,xlsx'
        ]);

        $file = $request->file('file');

        Excel::import(new MasterlistImport, $file);

        //put into an array the data from the excel file
        $data = Excel::toArray(new MasterlistImport, $file);
        return response()->json(
            [
                'message' => 'Masterlist imported successfully.',
                'data' => $data
            ],
            200
        );
    }
}
