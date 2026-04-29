<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPanelController extends Controller
{
    public function show(Request $request): View
    {
        return view('admin.panel', [
            'adminKey' => (string) $request->query('key', ''),
        ]);
    }
}
