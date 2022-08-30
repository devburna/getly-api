<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMonoAccountHolderRequest;
use App\Models\MonoAccountHolder;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MonoAccountHolderController extends Controller
{
    private $monoUrl, $monoSecKey;

    public function __construct()
    {
        $this->monoUrl = env('MONO_URL');
        $this->monoSecKey = env('MONO_SEC_KEY');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * https://docs.mono.co/docs/creating-account-holders
     *
     * @param  \App\Http\Requests\StoreMonoAccountHolderRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function createAccountHolder(StoreMonoAccountHolderRequest $request)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mono-sec-key' => $this->monoSecKey,
            ])->post("{$this->monoUrl}/issuing/v1/accountholders", [
                'entity' => 'INDIVIDUAL',
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'bvn' => $request->bvn,
                'phone' => $request->phone
            ])->json();

            // catch error
            if (!array_key_exists('status', $response) || $response['status'] === 'failed') {
                throw ValidationException::withMessages([$response['message']]);
            }

            // set data
            $request['identity'] = $response['data']['id'];
            $request['meta'] = json_encode($response);

            return $this->store($request);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreMonoAccountHolderRequest  $request
     */
    public function store(StoreMonoAccountHolderRequest $request)
    {
        return MonoAccountHolder::create($request->only([
            'user_id',
            'identity',
            'meta'
        ]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\MonoAccountHolder  $monoAccountHolder
     * @return \Illuminate\Http\Response
     */
    public function destroy(MonoAccountHolder $monoAccountHolder)
    {
        //
    }
}
