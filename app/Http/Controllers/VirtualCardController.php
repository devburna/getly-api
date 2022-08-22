<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVirtualCardRequest;
use App\Http\Requests\UpdateVirtualCardRequest;
use App\Models\VirtualCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class VirtualCardController extends Controller
{
    private $flutterwaveSecKey;

    public function __construct()
    {
        $this->flutterwaveSecKey = env('FLUTTERWAVE_SEC_KEY');
    }
    /**
     * Show the form for creating a new resource.
     *
     * @param  \App\Http\Requests\StoreVirtualCardRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function create(StoreVirtualCardRequest $request)
    {
        return DB::transaction(function () use ($request) {

            // checks duplicate wallet
            if ($request->user()->virtualCard) {
                return $this->show($request);
            }

            // checks if sender can fund virtual card
            if (!$request->user()->hasFunds($request->amount)) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient funds, please fund wallet and try again.'
                ]);
            }

            // send request to flutterwave.com
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->post(env('FLUTTERWAVE_URL') . '/virtual-cards', [
                'currency' => 'USD',
                'amount' => $request->amount,
                'billing_name' => "{$request->user()->first_name} {$request->user()->last_name}",
            ]);

            if (!$response->ok()) {
                throw ValidationException::withMessages([
                    'action' => $response->json()['message'],
                ]);
            }

            // set user id
            $request['user_id'] = $request->user()->id;

            // set virtual card data
            $data = $response->json()['data'];
            $data['user_id'] = $request->user()->id;
            $data['identity'] = $data['id'];
            $data['provider'] = 'flutterwave';

            // new request instance
            $storeVirtualCardRequest = new StoreVirtualCardRequest($data);

            // store virtual card
            $request->user()->virtualCard = $this->store($storeVirtualCardRequest);

            // debit user wallet
            $request->user()->debit($request->amount);

            return $this->show($request, $response->json()['message'], 201);
        });
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreVirtualCardRequest  $request
     */
    public function store(StoreVirtualCardRequest $request)
    {
        return VirtualCard::create($request->only([
            'user_id',
            'identity',
            'account_id',
            'currency',
            'card_hash',
            'card_pan',
            'masked_pan',
            'name_on_card',
            'expiration',
            'cvv',
            'address_1',
            'address_2',
            'city',
            'state',
            'zip_code',
            'callback_url',
            'is_active',
            'provider',
        ]));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Http\Requests  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $message = 'success', $code = 200)
    {
        // checks if user has virtaul card
        if (!$request->user()->virtualCard) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'No virtual card found for this account.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $request->user()->virtualCard,
            'message' => $message,
        ], $code);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateVirtualCardRequest  $request
     * @param  \App\Models\VirtualCard  $virtualCard
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateVirtualCardRequest $request, VirtualCard $virtualCard)
    {
        // checks if user has virtaul card
        if (!$request->user()->virtualCard) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'No virtual card found for this account.',
            ], 404);
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->flutterwaveSecKey}",
        ])->put(env('FLUTTERWAVE_URL') . "/virtual-cards/{$request->user()->virtualCard->identity}/status/{$request->action}");

        if (!$response->ok()) {
            throw ValidationException::withMessages([
                'action' => $response->json()['message'],
            ]);
        }

        return $this->show($request, $response->json()['message']);
    }

    public function fund(StoreVirtualCardRequest $request)
    {
        return DB::transaction(function () use ($request) {
            // checks if sender can fund virtual card
            if (!$request->user()->hasFunds($request->amount)) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient funds, please fund wallet and try again.'
                ]);
            }

            // checks if user has virtaul card
            if (!$request->user()->virtualCard) {
                return response()->json([
                    'status' => false,
                    'data' => null,
                    'message' => 'No virtual card found for this account.',
                ], 404);
            }

            // send request to flutterwave.com
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->post(env('FLUTTERWAVE_URL') . "/virtual-cards/{$request->user()->virtualCard->identity}/fund", [
                'debit_currency' => 'USD',
                'amount' => $request->amount,
            ]);

            if (!$response->ok()) {
                throw ValidationException::withMessages([
                    'amount' => $response->json()['message'],
                ]);
            }

            // debit user wallet
            $request->user()->debit($request->amount);

            return $this->show($request, $response->json()['message']);
        });
    }

    public function withdraw(StoreVirtualCardRequest $request)
    {
        // checks if user has virtaul card
        return DB::transaction(function () use ($request) {
            if (!$request->user()->virtualCard) {
                return response()->json([
                    'status' => false,
                    'data' => null,
                    'message' => 'No virtual card found for this account.',
                ], 404);
            }

            // send request to flutterwave.com
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->post(env('FLUTTERWAVE_URL') . "/virtual-cards/{$request->user()->virtualCard->identity}/withdraw", [
                'amount' => $request->amount,
            ]);

            if (!$response->ok()) {
                throw ValidationException::withMessages([
                    'amount' => $response->json()['message'],
                ]);
            }

            // credit user wallet
            $request->user()->credit($request->amount);

            return $this->show($request, $response->json()['message']);
        });
    }

    public function transactions(Request $request)
    {
        // checks if user has virtaul card
        if (!$request->user()->virtualCard) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'No virtual card found for this account.',
            ], 404);
        }

        // send request to flutterwave.com
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->flutterwaveSecKey}",
        ])->get(env('FLUTTERWAVE_URL') . "/virtual-cards/{$request->user()->virtualCard->identity}/transactions/{$request->from}/{$request->to}/{$request->index}/{$request->size}", [
            'amount' => $request->amount,
        ]);

        if (!$response->ok()) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $response->json()['message'],
            ], 422);
        }

        return response()->json([
            'status' => true,
            'data' => $response->json()['data'],
            'message' => $response->json()['message'],
        ]);
    }
}
