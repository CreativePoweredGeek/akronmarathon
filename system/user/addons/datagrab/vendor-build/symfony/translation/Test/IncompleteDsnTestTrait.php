<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace BoldMinded\DataGrab\Dependency\Symfony\Component\Translation\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Translation\Exception\IncompleteDsnException;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Translation\Provider\Dsn;
trait IncompleteDsnTestTrait
{
    /**
     * @return iterable<array{0: string, 1?: string|null}>
     */
    public static abstract function incompleteDsnProvider() : iterable;
    /**
     * @dataProvider incompleteDsnProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('incompleteDsnProvider')]
    public function testIncompleteDsnException(string $dsn, ?string $message = null)
    {
        $factory = $this->createFactory();
        $dsn = new Dsn($dsn);
        $this->expectException(IncompleteDsnException::class);
        if (null !== $message) {
            $this->expectExceptionMessage($message);
        }
        $factory->create($dsn);
    }
}
