<?php

declare(strict_types=1);

namespace App\Event;

class CourseOfflineCancelRegistered
{
    public int $memberId;

    public int $isSample;

    public function __construct(int $memberId,int $isSample)
    {
        $this->memberId = $memberId;
        $this->isSample = $isSample;
    }
}