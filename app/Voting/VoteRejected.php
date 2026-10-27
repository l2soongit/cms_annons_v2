<?php

namespace App\Voting;

use Illuminate\Support\Carbon;
use RuntimeException;

class VoteRejected extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly ?Carbon $retryAt = null)
    {
        parent::__construct($reason);
    }
}
