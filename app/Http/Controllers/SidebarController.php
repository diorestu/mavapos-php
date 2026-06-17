<?php

namespace App\Http\Controllers;

use App\Helpers\MenuHelper;

class SidebarController extends Controller
{
    public function getMenuData()
    {
        return view('layouts.sidebar', [
            'menuGroups' => MenuHelper::getMenuGroups(),
        ]);
    }
}
