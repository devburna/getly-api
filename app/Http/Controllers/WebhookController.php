<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWebhookRequest;
use App\Http\Requests\UpdateWebhookRequest;
use App\Models\Webhook;

class WebhookController extends Controller
{
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
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreWebhookRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWebhookRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Webhook  $webhook
     * @return \Illuminate\Http\Response
     */
    public function show(Webhook $webhook)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Webhook  $webhook
     * @return \Illuminate\Http\Response
     */
    public function edit(Webhook $webhook)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWebhookRequest  $request
     * @param  \App\Models\Webhook  $webhook
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWebhookRequest $request, Webhook $webhook)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Webhook  $webhook
     * @return \Illuminate\Http\Response
     */
    public function destroy(Webhook $webhook)
    {
        //
    }
}
