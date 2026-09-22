<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => ['user' => fn () => $request->user()?->only('name', 'email', 'timezone')],
            'flash' => ['success' => fn () => $request->session()->get('success')],
            'demoMode' => fn () => $request->user() && DB::table('quran_datasets')->where('source_name', 'Demo sintetis — bukan mushaf')->exists(),
        ];
    }
}
