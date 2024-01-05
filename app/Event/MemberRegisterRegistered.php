<?php

declare(strict_types=1);

namespace App\Event;

class MemberRegisterRegistered
{
    public int $memberId;

    public function __construct(int $memberId)
    {
        $this->memberId = $memberId;
    }
}