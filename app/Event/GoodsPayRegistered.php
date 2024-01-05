<?php

declare(strict_types=1);

namespace App\Event;

class GoodsPayRegistered
{
    public int $memberId;

    public string $orderNo;

    public function __construct(int $memberId,string $orderNo)
    {
        $this->memberId = $memberId;
        $this->orderNo = $orderNo;
    }
}