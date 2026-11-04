<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PosShellController extends Controller
{
    /**
     * Render the offline POS shell PWA.
     */
    public function index(Request $request): View
    {
        return view('pos.shell', [
            'terminalCode' => 'T-01',
            'outletName' => 'Dhanmondi Outlet #1',
            'tenantName' => 'Sonar Bangla Mart',
        ]);
    }
}
