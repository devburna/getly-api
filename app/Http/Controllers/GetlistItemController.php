<?php

namespace App\Http\Controllers;

use App\Enums\GetlistItemContributionType;
use App\Enums\GetlistItemStatus;
use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\StoreGetlistItemContributorRequest;
use App\Http\Requests\StoreGetlistItemRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateGetlistItemRequest;
use App\Http\Requests\VerifyFlutterwaveTransactionRequest;
use App\Models\GetlistItem;
use App\Models\GetlistItemContributor;
use App\Models\Transaction;
use App\Notifications\Contribution;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class GetlistItemController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreGetlistItemRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreGetlistItemRequest $request)
    {

        // set status
        $request['status'] = GetlistItemStatus::UNFULFILLED();

        // upload image
        $request['image_url'] = (new UploadApi())->upload($request->image->path(), [
            'folder' => config('app.name') . '/gifts/',
            'public_id' => str_shuffle($request->getlist_id . rand(000000, 999999)),
            'overwrite' => true,
            // 'notification_url' => '',
            'resource_type' => 'image'
        ])['secure_url'];

        $getlistItem = GetlistItem::create($request->only([
            'getlist_id',
            'name',
            'price',
            'quantity',
            'details',
            'image_url',
            'status'
        ]));

        return $this->show($getlistItem, null, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\GetlistItem  $getlistItem
     * @return \Illuminate\Http\Response
     */
    public function show(GetlistItem $getlistItem, $message = 'success', $code = 200)
    {
        // add contributos to data
        $getlistItem->contributors;

        return response()->json([
            'status' => true,
            'data' => $getlistItem,
            'message' => $message,
        ], $code);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateGetlistItemRequest  $request
     * @param  \App\Models\GetlistItem  $getlistItem
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateGetlistItemRequest $request, GetlistItem $getlistItem)
    {
        // upload image
        if ($request->hasFile('image')) {
            $request['image_url'] = (new UploadApi())->upload($request->image->path(), [
                'folder' => config('app.name') . '/gifts/',
                'public_id' => $getlistItem->id,
                'overwrite' => true,
                // 'notification_url' => '',
                'resource_type' => 'image'
            ])['secure_url'];
        }

        // update details
        $getlistItem->update($request->only([
            'name',
            'details',
            'image_url'
        ]));

        return $this->show($getlistItem);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\GetlistItem  $getlistItem
     * @return \Illuminate\Http\Response
     */
    public function destroy(GetlistItem $getlistItem)
    {
        // restore getlist item if trashed or deleted it
        if ($getlistItem->trashed()) {
            $getlistItem->restore();
        } else {
            $getlistItem->delete();
        }

        return $this->show($getlistItem);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\StoreGetlistItemContributorRequest  $request
     * @param  \App\Models\GetlistItem  $getlistItem
     * @return \Illuminate\Http\Response
     */
    public function contribute(StoreGetlistItemContributorRequest $request, GetlistItem $getlistItem)
    {
        try {
            return DB::transaction(function () use ($request, $getlistItem) {
                // checks if unfufilled
                if (!$getlistItem->status->is(GetlistItemStatus::UNFULFILLED())) {
                    throw ValidationException::withMessages([
                        'This gift is either ' . GetlistItemStatus::REDEEMABLE() . ' or has already been ' . GetlistItemStatus::CLAIMED() . '.'
                    ]);
                }

                // get amount
                $amount = match ($request->type) {
                    'contribute' => $request->amount,
                    default => $getlistItem->price - $getlistItem->contributors->sum('amount')
                };

                // generate payment link
                $request['tx_ref'] = Str::uuid();
                $request['name'] = $request->full_name;
                $request['email'] = $request->email_address;
                $request['phone'] = $request->phone_number;
                $request['amount'] = $amount;
                $request['meta'] = [
                    "consumer_id" => $getlistItem->id,
                    "consumer_mac" => $request->type,
                ];
                $request['redirect_url'] = config('app.url') . "/contribute/{$getlistItem->id}";

                $link = (new FlutterwaveController())->generatePaymentLink($request->all());

                // set payment link
                $getlistItem->payment_link = $link['data']['link'];

                return $this->show($getlistItem);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $th->getMessage(),
            ], 422);
        }
    }

    public function webHook(VerifyFlutterwaveTransactionRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                // verify transaction
                $transaction = (new FlutterwaveController())->verifyTransaction($request->transaction_id);

                // checks duplicate entry
                if (Transaction::where('identity', $transaction['data']['tx_ref'])->first()) {
                    throw ValidationException::withMessages(['Duplicate transaction found.']);
                }

                // find getlist item
                if (!$getlistItem = GetlistItem::find($transaction['data']['meta']['consumer_id'])) {
                    throw ValidationException::withMessages(['Error occured, kindly reach out to support ASAP!']);
                }

                // get transaction status
                $status = match ($transaction['data']['status']) {
                    'success' => TransactionStatus::SUCCESS(),
                    'successful' => TransactionStatus::SUCCESS(),
                    'new' => TransactionStatus::SUCCESS(),
                    'pending' => TransactionStatus::SUCCESS(),
                    default => TransactionStatus::FAILED()
                };

                // set channel
                $channel = match ($transaction['data']['meta']['consumer_mac']) {
                    'buy' => TransactionChannel::GETLIST_PURCHASE(),
                    'contribute' => TransactionChannel::GETLIST_CONTRIBUTION(),
                    default => throw ValidationException::withMessages(['Error occured, kindly reach out to support ASAP!'])
                };

                // update getlist status
                if ($channel === TransactionChannel::GETLIST_PURCHASE()) {
                    $getlistItem->update([
                        'status' => GetlistItemStatus::REDEEMABLE()
                    ]);
                }

                // store transaction
                $storeTransactionRequest = (new StoreTransactionRequest($transaction));
                $storeTransactionRequest['user_id'] = $getlistItem->getlist->user->id;
                $storeTransactionRequest['identity'] = $transaction['data']['tx_ref'];
                $storeTransactionRequest['reference'] = $transaction['data']['flw_ref'];
                $storeTransactionRequest['type'] = TransactionType::CREDIT();
                $storeTransactionRequest['channel'] = $channel;
                $storeTransactionRequest['amount'] = $transaction['data']['amount'];
                $storeTransactionRequest['narration'] = $transaction['data']['narration'];
                $storeTransactionRequest['status'] = $status;
                $storeTransactionRequest['meta'] = json_encode($transaction);
                $storedTransaction = (new TransactionController())->store($storeTransactionRequest);

                // store contributor details
                $storeGetlistItemContributorRequest = (new StoreGetlistItemContributorRequest());
                $storeGetlistItemContributorRequest['getlist_item_id'] = $getlistItem->id;
                $storeGetlistItemContributorRequest['reference'] = $transaction['data']['tx_ref'];
                $storeGetlistItemContributorRequest['full_name'] = $transaction['data']['customer']['name'];
                $storeGetlistItemContributorRequest['email_address'] = $transaction['data']['customer']['email'];
                $storeGetlistItemContributorRequest['phone_number'] = $transaction['data']['customer']['phone_number'];
                $storeGetlistItemContributorRequest['type'] =  $transaction['data']['meta']['consumer_mac'];
                $storeGetlistItemContributorRequest['amount'] = $transaction['data']['amount'];
                $storeGetlistItemContributorRequest['meta'] = json_encode($storedTransaction);
                $contributor = (new GetlistItemContributorController())->store($storeGetlistItemContributorRequest);

                // credit wallet if success
                if ($storedTransaction->status->is(TransactionStatus::SUCCESS())) {
                    $getlistItem->getlist->user->credit($storedTransaction->amount);
                }

                // notify gift owner
                // $getlistItem->getlist->user->notify(new Contribution($getlistItem, $contributor));

                return response()->json([
                    'status' => true,
                    'data' => $getlistItem,
                    'message' => 'success',
                ]);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $th->getMessage(),
            ], 422);
        }
    }
}
