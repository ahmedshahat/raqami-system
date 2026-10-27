<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Light / dark appearance chosen by the signed-in user for himself only.
 * It overrides the business appearance (business.theme_color) in layouts/app.
 */
class UserThemeController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['light', 'dark'])],
        ]);

        $user = auth()->user();
        $user->theme_mode = $data['mode'];
        $user->save();

        return redirect()->back();
    }
}
