<?php

declare(strict_types=1);

namespace App\Event;

class CourseOfflineCompleteRegistered
{
    public int $courseOfflinePlanId;

    public function __construct(int $courseOfflinePlanId)
    {
        $this->courseOfflinePlanId = $courseOfflinePlanId;
    }
}