<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PillarController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pillar' => ['required', 'in:National,International,Institutional'],
        ]);

        $request->session()->put('pillar', $validated['pillar']);

        return back();
    }
}
