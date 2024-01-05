<?php

declare(strict_types=1);

namespace App\Event;

class CourseOfflinePayRegistered
{
    public int $memberId;

    public int $isSample;

    public string $orderNo;

    public function __construct(int $memberId,int $isSample, string $orderNo)
    {
        $this->memberId = $memberId;
        $this->isSample = $isSample;
        $this->orderNo = $orderNo;
    }
}