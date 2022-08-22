<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGetlistItemContributorRequest;
use App\Models\GetlistItemContributor;

class GetlistItemContributorController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreGetlistItemContributorRequest  $request
     */
    public function store(StoreGetlistItemContributorRequest $request)
    {
        // encode meta data
        $request['meta'] = json_encode($request->meta);

        return GetlistItemContributor::create($request->only([
            'getlist_item_id',
            'reference',
            'full_name',
            'email_address',
            'phone_number',
            'type',
            'amount',
            'meta'
        ]));
    }
}
