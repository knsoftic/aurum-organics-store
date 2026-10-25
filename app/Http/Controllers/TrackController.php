<?php

namespace App\Http\Controllers;

use App\Services\Analytics\Tracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TrackController extends Controller
{
    /** Page-view / click beacon sent by the storefront (navigator.sendBeacon). */
    public function collect(Request $request, Tracker $tracker)
    {
        $data = json_decode((string) $request->getContent(), true);

        $valid = is_array($data) && Validator::make($data, [
            'sid' => ['required', 'regex:/^[A-Za-z0-9]{8,40}$/'],
            'vid' => ['required', 'regex:/^[A-Za-z0-9]{8,40}$/'],
            'p' => 'nullable|string|max:1000',
            't' => 'nullable|string|max:300',
            'r' => 'nullable|string|max:1000',
            'w' => 'nullable|integer|min:0|max:10000',
            'tz' => 'nullable|string|max:60',
            'lang' => 'nullable|string|max:20',
            'e' => 'nullable|string|max:30',
            'v' => 'nullable|numeric',
            'l' => 'nullable|string|max:200',
        ])->passes();

        if ($valid) {
            try {
                $tracker->collect($request, $data);
            } catch (Throwable $e) {
                report($e); // never break the shop because of analytics
            }
        }

        return response()->noContent();
    }
}
