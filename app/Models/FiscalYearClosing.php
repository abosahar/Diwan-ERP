<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** One branch closed inside a fiscal year (its own closing entry). */
class FiscalYearClosing extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'fiscal_year_id', 'branch_id', 'closing_entry_id', 'closed_at', 'closed_by'];
}
