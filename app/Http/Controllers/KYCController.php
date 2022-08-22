<?php

namespace App\Http\Controllers;

use App\Http\Requests\KYCRequest;
use Illuminate\Validation\ValidationException;

class KYCController extends Controller
{
    // bvn
    public function bvn(KYCRequest $request)
    {
        // get bvn info
        $data = [];
        $data['bvn'] = $request->identity;
        $bvn = (new FlutterwaveController())->verifyBvn($data);

        // check if not status 200
        if (!$bvn->ok()) {
            throw ValidationException::withMessages([
                'bvn' => $bvn['message']
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $bvn['data'],
            'message' => 'success',
        ]);
    }
}
