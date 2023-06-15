<?php

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Tests\Integration\Cache\Adapter\WeakCache;

use IntegrationTester;
use Phalcon\Cache\Adapter\WeakCache;
use Phalcon\Storage\SerializerFactory;
use Phalcon\Support\Exception as HelperException;

class GetKeysCest
{
    /**
     * Tests Phalcon\Cache\Adapter\WeakCache :: getKeys()
     *
     * @param IntegrationTester $I
     *
     * @throws HelperException
     *
     * @author Phalcon Team <team@phalcon.io>
     * @since  2020-09-09
     */
    public function storageAdapterWeakCacheGetKeys(IntegrationTester $I)
    {
        $I->wantToTest('Cache\Adapter\WeakCache - getKeys()');

        $serializer = new SerializerFactory();
        $adapter    = new WeakCache($serializer);

        $I->assertTrue($adapter->clear());
        $obj1 = new \stdClass();
        $obj1->id = 1;
        $obj2 = new \stdClass();
        $obj2->id = 2;
        $obj3 = new \stdClass();
        $obj3->id = 3;


        $adapter->set('key-1', $obj1);
        $adapter->set('key-2', $obj2);
        $adapter->set('key-3', $obj3);
        $adapter->set('one-1', $obj1);
        $adapter->set('one-2', $obj2);
        $adapter->set('one-3', $obj3);


        $actual = $adapter->has('key-1');
        $I->assertTrue($actual);
        $actual = $adapter->has('key-2');
        $I->assertTrue($actual);
        $actual = $adapter->has('key-3');
        $I->assertTrue($actual);

        $expected = [
            'ph-weakcache-key-1',
            'ph-weakcache-key-2',
            'ph-weakcache-key-3',
            'ph-weakcache-one-1',
            'ph-weakcache-one-2',
            'ph-weakcache-one-3',
        ];
        $actual = $adapter->getKeys();
        sort($actual);
        $I->assertEquals($expected, $actual);

        $expected = [
            'ph-weakcache-one-1',
            'ph-weakcache-one-2',
            'ph-weakcache-one-3',
        ];
        $actual   = $adapter->getKeys("one");
        sort($actual);
        $I->assertEquals($expected, $actual);

        unset($obj1);

        $I->assertEquals(null, $adapter->get('key-1'));

        $temp = $adapter->get('key-2');
        unset($obj2);
        $I->assertEquals($temp, $adapter->get('key-2'));
    }
}
