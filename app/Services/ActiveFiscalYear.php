<?php

namespace App\Services;

use App\Models\FiscalYear;
use Illuminate\Support\Collection;

/**
 * The fiscal year chosen in the top bar (session). Reports and the dashboard
 * default to its dates; with no choice, the year containing today, else the
 * newest one.
 */
class ActiveFiscalYear
{
    public const SESSION_KEY = 'active_fiscal_year_id';

    /** @return Collection<int, FiscalYear> */
    public function years(): Collection
    {
        return FiscalYear::orderByDesc('start_date')->get();
    }

    public function current(): ?FiscalYear
    {
        $years = $this->years();
        $stored = request()->hasSession() ? request()->session()->get(self::SESSION_KEY) : null;
        if ($stored && ($year = $years->firstWhere('id', (int) $stored))) {
            return $year;
        }

        $today = now()->toDateString();

        return $years->first(fn (FiscalYear $y) => $y->start_date->toDateString() <= $today && $y->end_date->toDateString() >= $today)
            ?? $years->first();
    }

    /** Is $id one of the company's fiscal years? */
    public function allows(int $id): bool
    {
        return $this->years()->contains('id', $id);
    }

    public function switchTo(int $id): bool
    {
        if (! $this->allows($id)) {
            return false;
        }
        request()->session()->put(self::SESSION_KEY, $id);

        return true;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, active: ?int}
     */
    public function switcher(): array
    {
        return [
            'items' => $this->years()->map(fn (FiscalYear $y) => [
                'id' => $y->id, 'name' => $y->name, 'start' => $y->start_date->toDateString(), 'end' => $y->end_date->toDateString(),
            ])->values()->all(),
            'active' => $this->current()?->id,
        ];
    }
}
