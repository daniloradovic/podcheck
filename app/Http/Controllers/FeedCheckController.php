<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedCheckController extends Controller
{
    public function index(): View
    {
        return view('home');
    }

    public function check(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        dd('Feed URL submitted:', $validated['url']);
    }
}
