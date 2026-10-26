<?php

namespace App\Http\Controllers;

use App\Services\SelfUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Dashboard "update available" banner: progress of the update and the button that starts it. */
class SelfUpdateController extends Controller
{
    public function status(Request $request, SelfUpdate $updates): JsonResponse
    {
        abort_unless($updates->enabled() && $request->user()->isCompanyOwner(), 404);

        return response()->json(['status' => $updates->status(), 'current' => $updates->current(), 'available' => $updates->available()]);
    }

    public function apply(Request $request, SelfUpdate $updates): JsonResponse
    {
        abort_unless($updates->enabled() && $request->user()->isCompanyOwner(), 404);
        abort_unless($updates->available(), 422, 'لا يوجد إصدار أحدث.');
        if (! $updates->running()) {
            $updates->request();
        }

        return response()->json(['status' => $updates->status()]);
    }
}
