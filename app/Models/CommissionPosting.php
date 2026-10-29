<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A month's commission already sent to payroll. */
class CommissionPosting extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'user_id', 'year', 'month', 'amount', 'employee_adjustment_id'];
}
