<?php

namespace App\Http\Controllers;

use App\Http\Requests\KYCRequest;

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
}
