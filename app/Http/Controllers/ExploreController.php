<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class ExploreController extends Controller
{
    public function index()
    {
        try {
            $response = Http::timeout(15)->get('https://master.kiz.app/');

            $response->throw();

            $data = $response->json();

            return view('explore', compact('data'));
        } catch (RequestException $e) {
            return view('explore', [
                'data' => [],
                'error' => 'Failed to fetch API data.'
            ]);
        }
    }
}
