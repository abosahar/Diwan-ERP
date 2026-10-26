<?php

namespace App\Exceptions;

use Exception;

/**
 * A record was about to be created while the top bar shows "all branches":
 * every financial record belongs to one branch, so the user must pick it.
 */
class BranchRequiredException extends Exception
{
    public function __construct(string $message = 'اختر فرعاً محدداً من القائمة العلوية قبل إنشاء أي مستند أو قيد.')
    {
        parent::__construct($message);
    }
}
