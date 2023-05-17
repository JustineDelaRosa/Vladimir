<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\MasterlistImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;

class MasterlistImportController extends Controller
{
    public function masterlistImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlx,xls,pdf,xlsx'
        ]);

        $file = $request->file('file')->store('import');

        Excel::import(new MasterlistImport, $file);
        return response()->json(
            [
                'message' => 'Masterlist imported successfully.',
            ],
            200
        );
    }
}
