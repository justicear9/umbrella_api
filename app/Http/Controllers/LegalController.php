<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function support(Request $request): View
    {
        return view('legal.support', [
            'embed' => $request->boolean('embed'),
        ]);
    }

    public function terms(Request $request): View
    {
        return view('legal.terms', [
            'embed' => $request->boolean('embed'),
        ]);
    }

    public function privacy(Request $request): View
    {
        return view('legal.privacy', [
            'embed' => $request->boolean('embed'),
        ]);
    }
}
