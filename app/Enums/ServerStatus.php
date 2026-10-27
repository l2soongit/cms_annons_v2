<?php

namespace App\Enums;

enum ServerStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Hidden = 'hidden';
    case Blocked = 'blocked';
    case Archived = 'archived';

    public function label(): string
    {
        return __("server.status.{$this->value}");
    }
}
