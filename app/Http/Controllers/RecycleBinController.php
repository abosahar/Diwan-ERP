<?php

namespace App\Http\Controllers;

use App\Services\RecycleBin;
use Inertia\Inertia;
use Inertia\Response;

/** Deleted quotations and orders: list, restore, erase now. */
class RecycleBinController extends Controller
{
    public function index(): Response
    {
        $rows = collect(RecycleBin::TYPES)->flatMap(function ($def, $type) {
            [$model, $label, $numberColumn] = $def;

            return $model::onlyTrashed()->latest('deleted_at')->limit(200)->get()->map(fn ($d) => [
                'type' => $type,
                'id' => $d->id,
                'kind' => $label,
                'number' => $d->{$numberColumn},
                'party' => ($d->customer ?? $d->supplier ?? null)?->name,
                'total' => (float) $d->total,
                'deleted_at' => $d->deleted_at->toDateTimeString(),
                'expires_at' => $d->deleted_at->copy()->addDays(RecycleBin::RETENTION_DAYS)->toDateString(),
            ]);
        })->sortByDesc('deleted_at')->values();

        return Inertia::render('RecycleBin/Index', ['rows' => $rows, 'days' => RecycleBin::RETENTION_DAYS]);
    }

    public function restore(string $type, int $id)
    {
        $document = $this->find($type, $id);
        $document->restore();

        return back()->with('success', 'تمت استعادة المستند');
    }

    public function purge(string $type, int $id)
    {
        $document = $this->find($type, $id);
        $document->items()->delete();
        $document->forceDelete();

        return back()->with('success', 'تم حذف المستند نهائيًا');
    }

    protected function find(string $type, int $id)
    {
        abort_unless(isset(RecycleBin::TYPES[$type]), 404);
        $model = RecycleBin::TYPES[$type][0];

        return $model::onlyTrashed()->findOrFail($id);
    }
}
