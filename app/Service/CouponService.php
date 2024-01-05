<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\Utils\Context;

class CouponService extends BaseService
{
    /**
     * CouponService constructor.
     */
    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }


}