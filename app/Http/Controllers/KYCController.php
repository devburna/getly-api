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
            $data = [];
            $data['bvn'] = $request->identity;
            $bvn = (new FlutterwaveController())->verifyBvn($data);

            return response()->json([
                'status' => true,
                'data' => $bvn['data'],
                'message' => 'success',
            ]);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }
}
