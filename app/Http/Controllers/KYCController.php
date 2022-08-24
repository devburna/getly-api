<?php

namespace App\Http\Controllers;

use App\Http\Requests\KYCRequest;
use Illuminate\Validation\ValidationException;

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
                'data' => $bvn,
                'message' => 'success',
            ]);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }
}
