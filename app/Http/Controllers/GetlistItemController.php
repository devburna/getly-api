<?php

namespace App\Http\Controllers;

use App\Enums\GetlistItemContributionType;
use App\Enums\GetlistItemStatus;
use App\Http\Requests\StoreGetlistItemContributorRequest;
use App\Http\Requests\StoreGetlistItemRequest;
use App\Http\Requests\UpdateGetlistItemRequest;
use App\Models\GetlistItem;
use App\Models\GetlistItemContributor;
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
                        'amount' => 'This gift is either ' . GetlistItemStatus::REDEEMABLE() . ' or has already been ' . GetlistItemStatus::CLAIMED() . '.',
                    ]);
                }

                // get amount
                switch ($request->type) {
                    case GetlistItemContributionType::CONTRIBUTE():
                        $amount = $request->meta['contribute']['amount'];
                        break;

                    default:
                        $amount = $getlistItem->price - $getlistItem->contributors->sum('amount');
                        break;
                }

                // generate payment link
                $data = [];
                $data['tx_ref'] = Str::uuid();
                $data['name'] = $request->full_name;
                $data['email'] = $request->email_address;
                $data['phone_number'] = $request->phone_number;
                $data['amount'] = $amount;
                $data['meta'] = [
                    "consumer_id" => $getlistItem->id,
                    "consumer_mac" => $request->type,
                ];
                $data['redirect_url'] = route('contribution', ['getlistItem' => $getlistItem->id]);

                $link = (new FlutterwaveController())->generatePaymentLink($data)->json()['data'];

                // set payment link
                $getlistItem->payment_link = $link['link'];

                return $this->show($getlistItem);
            });
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }

    public function contribution(Request $request)
    {
        try {
            // verify transaction
            $response = (new FlutterwaveController())->verifyTransaction($request->transaction_id)->json();

            // find getlist
            if (!$getlistItem = GetlistItem::find($response['data']['meta']['consumer_id'])) {
                throw ValidationException::withMessages([
                    'message' => 'Error occured, please contact support.'
                ]);
            }

            // checks duplicate entry
            if (GetlistItemContributor::where('reference', $response['data']['id'])->first()) {
                throw ValidationException::withMessages([
                    'message' => 'Transaction has already been verified.'
                ]);
            }

            // set contributor details
            if ($response['data']['status'] === 'successful') {
                $response['getlist_item_id'] = $response['data']['meta']['consumer_id'];
                $response['reference'] = $response['data']['id'];
                $response['full_name'] = $response['data']['customer']['name'];
                $response['email_address'] = $response['data']['customer']['email'];
                $response['phone_number'] = $response['data']['customer']['phone_number'];
                $response['type'] =  $response['data']['meta']['consumer_mac'];
                $response['amount'] = $response['data']['amount'];
                $response['meta'] = json_encode($response);

                // store contributor details
                $storeGetlistItemContributorRequest = (new StoreGetlistItemContributorRequest($response));
                $contributor = (new GetlistItemContributorController())->store($storeGetlistItemContributorRequest);

                // update getlist status
                if ($response['type'] === GetlistItemContributionType::BUY()) {
                    $getlistItem->update([
                        'status' => GetlistItemStatus::REDEEMABLE()
                    ]);
                }

                // credit gift owner
                $getlistItem->getlist->user->credit($response['amount']);

                // notify gift owner
                $getlistItem->getlist->user->notify(new Contribution($getlistItem, $contributor));

                return $this->show($getlistItem);
            }

            throw ValidationException::withMessages([
                'message' => $response['message']
            ]);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }
}
