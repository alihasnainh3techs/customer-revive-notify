<?php

namespace App\Http\Controllers;

class CampaignController extends Controller
{
    public function index()
    {
        return view('create-campaign');
    }
}
