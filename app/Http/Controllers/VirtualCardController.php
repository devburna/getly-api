<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVirtualCardRequest;
use App\Http\Requests\UpdateVirtualCardRequest;
use App\Models\VirtualCard;

class VirtualCardController extends Controller
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
     * @param  \App\Http\Requests\StoreVirtualCardRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreVirtualCardRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\VirtualCard  $virtualCard
     * @return \Illuminate\Http\Response
     */
    public function show(VirtualCard $virtualCard)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\VirtualCard  $virtualCard
     * @return \Illuminate\Http\Response
     */
    public function edit(VirtualCard $virtualCard)
    {
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\VirtualCard  $virtualCard
     * @return \Illuminate\Http\Response
     */
    public function destroy(VirtualCard $virtualCard)
    {
        //
    }
}
