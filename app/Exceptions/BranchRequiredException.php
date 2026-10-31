<?php

namespace App\Exceptions;

use Exception;

/**
 * A record was about to be created while "all branches" is shown: every
 * financial record belongs to one branch, so the user must pick it.
 */
class BranchRequiredException extends Exception
{
    public function __construct(string $message = 'اختر فرعاً محدداً من بطاقة «سياق العمل» في القائمة الجانبية قبل إنشاء أي مستند أو قيد.')
    {
        parent::__construct($message);
    }
}
