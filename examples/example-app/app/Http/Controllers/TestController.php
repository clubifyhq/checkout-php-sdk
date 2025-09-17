<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    public function simple()
    {
        return response('Test working!');
    }

    public function view()
    {
        return view('welcome');
    }

    public function clubifyTest()
    {
        return view('clubify.test-all-methods');
    }
}