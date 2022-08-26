<?php

namespace App\Http\Controllers;

use App\Http\Requests\KYCRequest;
use App\Http\Requests\StoreMonoAccountHolderRequest;
use Illuminate\Http\Request;

class KYCController extends Controller
{
    // bvn
    public function bvn(KYCRequest $request)
    {
        try {
            // get bvn info
            $bvn = (new MonoController())->verifyBvn($request->bvn);

            // re-verify bvn
            $bvn = (new MonoController())->verifyBvn($request->bvn);

            $storeMonoAccountHolderRequest = (new StoreMonoAccountHolderRequest());
            $storeMonoAccountHolderRequest['user_id'] = $request->user()->id;
            $storeMonoAccountHolderRequest['first_name'] = $bvn['data']['first_name'];
            $storeMonoAccountHolderRequest['last_name'] = $bvn['data']['last_name'];
            $storeMonoAccountHolderRequest['bvn'] = $request->bvn;
            $storeMonoAccountHolderRequest['phone'] = $bvn['data']['phone'];
            (new MonoAccountHolderController())->createAccountHolder($storeMonoAccountHolderRequest);

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
