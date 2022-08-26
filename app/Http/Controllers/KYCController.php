<?php

namespace App\Http\Controllers;

use App\Http\Requests\KYCRequest;
use Illuminate\Http\Request;

class KYCController extends Controller
{
    // bvn
    public function bvn(KYCRequest $request)
    {
        try {
            // get bvn info
            $bvn = (new MonoController())->verifyBvn($request->bvn);

            return response()->json([
                'status' => true,
                'data' => $bvn['data'],
                'message' => 'success',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $th->getMessage()
            ]);
        }
    }

    public function banks(Request $request)
    {
        try {
            // get bvn info
            $banks = (new MonoController())->banks($request->bvn);

            return response()->json([
                'status' => true,
                'data' => $banks['data'],
                'message' => 'success',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $th->getMessage()
            ]);
        }
    }

    public function bank(Request $request)
    {
        try {
            // validate request
            $request->validate([
                'bank' => 'required',
                'account_number' => 'required|digits:10'
            ]);

            // get bvn info
            $banks = (new MonoController())->verifyBank($request->all());

            return response()->json([
                'status' => true,
                'data' => $banks['data'],
                'message' => 'success',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $th->getMessage()
            ]);
        }
    }
}
