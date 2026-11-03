<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PreventDemoBusinessDuringReset
{
    public function handle(Request $request, Closure $next)
    {
        $businessId = (int) $request->session()->get('user.business_id', 0);
        if ($businessId > 0 && Cache::has('demo-reset:business:'.$businessId)) {
            $message = 'يتم الآن إعادة النسخة التجريبية لحالتها الأصلية. حاول مرة أخرى خلال دقائق قليلة.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 503, ['Retry-After' => 60]);
            }
            return response($message, 503, ['Retry-After' => 60]);
        }

        return $next($request);
    }
}
