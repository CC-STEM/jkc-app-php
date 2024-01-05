<?php

declare(strict_types=1);

namespace App\Snowflake;

use Hyperf\Snowflake\IdGeneratorInterface;
use Hyperf\Utils\ApplicationContext;

class IdGenerator
{
    /**
     * @return int
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public static function generate(): int
    {
        $container = ApplicationContext::getContainer();
        $generator = $container->get(IdGeneratorInterface::class);
        return $generator->generate();
    }
}