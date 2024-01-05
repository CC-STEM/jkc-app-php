<?php

declare(strict_types=1);

namespace App\Event;

class InviteBindRegistered
{
    public int $memberId;

    public int $parentId;

    public function __construct(int $memberId,int $parentId)
    {
        $this->memberId = $memberId;
        $this->parentId = $parentId;
    }
}