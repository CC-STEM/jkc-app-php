<?php

declare(strict_types=1);

namespace App\Event;

class VipCardPayRegistered
{
    public int $memberId;

    public int $orderType;

    public string $orderNo;

    public function __construct(int $memberId,int $orderType,string $orderNo)
    {
        $this->memberId = $memberId;
        $this->orderType = $orderType;
        $this->orderNo = $orderNo;
    }
}