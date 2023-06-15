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

namespace Phalcon\Tests\Database\Mvc\Model\FLC;

use DatabaseTester;
use PDO;
use Phalcon\Cache\AdapterFactory;
use Phalcon\Cache\Cache;
use Phalcon\Mvc\Model\Exception;
use Phalcon\Storage\SerializerFactory;
use Phalcon\Tests\Fixtures\Migrations\CustomersMigration;
use Phalcon\Tests\Fixtures\Migrations\InvoicesMigration;
use Phalcon\Tests\Fixtures\Migrations\ObjectsMigration;
use Phalcon\Tests\Fixtures\Traits\DiTrait;
use Phalcon\Tests\Models\Customers;
use Phalcon\Tests\Models\Invoices;
use Phalcon\Tests\Models\Objects;
use Phalcon\Mvc\Model\Resultset\Simple;
use Phalcon\Mvc\Model\FLC\FirstLevelCache;
use Phalcon\Tests\Models\InvoicesKeepSnapshots;

use function getOptionsRedis;
use function outputDir;
use function uniqid;

/**
 * Class FindCest
 */
class FirstLevelCacheCest
{
    use DiTrait;

    public function _before(DatabaseTester $I)
    {
        $this->setNewFactoryDefault();
        $this->setDatabase($I);
        $manager = $this->getDi()->get('modelsManager');
        $serializerFactory = new SerializerFactory();
        $manager->setFirstLevelCache(new FirstLevelCache($serializerFactory));
    }

    /**
     * Tests Phalcon\Mvc\Model :: FirstLevelCache()
     *
     * @author Phalcon Team <team@phalcon.io>
     * @since  2020-02-01
     *
     * @group  mysql
     * @group  pgsql
     * @group  sqlite
     */
    public function metaDataGetUUID(DatabaseTester $I)
    {

        $I->wantToTest('Mvc\MetaData - getUUID()');
        /** @var PDO $connection */
        $connection = $I->getConnection();
        $migration  = new ObjectsMigration($connection);
        $migration->insert(1, 'random data', 1);
        $result = $connection->query('select * from objects');
        $row = $result->fetch();

        $metadata = $this->getDi()->getShared("modelsMetadata");
        $object = new Objects();
        $uuid = $metadata->getUUID($object, $row);
        $comp_uuid = strtolower(get_class($object)) . "-" . $object->getSource() . $object->getSchema() . "::" . $row['obj_id'];
        $I->assertEquals($uuid ,$comp_uuid);
    }


    /**
     * Tests Phalcon\Mvc\Model :: FirstLevelCache()
     *
     * @author Phalcon Team <team@phalcon.io>
     * @since  2020-02-01
     *
     * @group  mysql
     * @group  pgsql
     * @group  sqlite
     */
    public function mvcModelFind(DatabaseTester $I)
    {
        $I->wantToTest('Mvc\Model - find()');

        /** @var PDO $connection */
        $connection = $I->getConnection();
        $migration  = new ObjectsMigration($connection);
        $migration->insert(1, 'random data', 1);

        $data = Objects::find();
        $I->assertEquals(1, count($data));

        $record = $data[0];
        $I->assertEquals(1, $record->obj_id);

        $I->assertEquals('random data', $record->obj_name);

        $I->assertEquals(true, $data instanceof Simple);
        $obj = Objects::findFirst();
        $I->assertEquals($obj, $record);
        $I->assertEquals(\spl_object_id($obj), \spl_object_id($record));
    }

    /**
     * Tests Phalcon\Mvc\Model :: FirstLevelCache()
     *
     * @author Phalcon Team <team@phalcon.io>
     * @since  2020-02-01
     *
     * @group  mysql
     * @group  pgsql
     * @group  sqlite
     */
    public function mvcModelGetRelationAndFind(DatabaseTester $I)
    {
        $I->wantToTest('Mvc\Model - Relation and Find');

        /** @var \PDO $connection */
        $connection = $I->getConnection();

        $invoicesMigration = new InvoicesMigration($connection);
        $invoicesMigration->insert(77, 1, 0, uniqid('inv-', true));

        $customersMigration = new CustomersMigration($connection);
        $customersMigration->insert(1, 1, 'test_firstName_1', 'test_lastName_1');

        /**
         * @var Invoices $invoice
         */
        $invoice = Invoices::findFirst(77);

        $expected = 1;
        $actual   = $invoice->customer->id;
        $I->assertEquals($expected, $actual);

        $customer1 = $invoice->customer;
        $customer2 = Customers::findFirst(1);
        $I->assertEquals($customer1, $customer2);
        $I->assertEquals(\spl_object_id($customer1), \spl_object_id($customer2));
    }

     /**
     * Tests Phalcon\Mvc\Model :: FirstLevelCache()
     *
     * @author Phalcon Team <team@phalcon.io>
     * @since  2020-02-01
     *
     * @group  mysql
     * @group  pgsql
     * @group  sqlite
     */
    public function mvcModelKeepSnapshotsGetRelationAndFind(DatabaseTester $I)
    {
        $I->wantToTest('Mvc\Model - Relation and Find');

        /** @var \PDO $connection */
        $connection = $I->getConnection();

        $invoicesMigration = new InvoicesMigration($connection);
        $invoicesMigration->insert(77, 1, 0, uniqid('inv-', true));

        $customersMigration = new CustomersMigration($connection);
        $customersMigration->insert(1, 1, 'test_firstName_1', 'test_lastName_1');

        /**
         * @var Invoices $invoice
         */
        $invoice = InvoicesKeepSnapshots::findFirst(77);

        $expected = 1;
        $actual   = $invoice->customer->id;
        $I->assertEquals($expected, $actual);

        $customer1 = $invoice->customer;
        $customer2 = Customers::findFirst(1);
        $I->assertEquals($customer1, $customer2);
        $I->assertEquals(\spl_object_id($customer1), \spl_object_id($customer2));
    }

    // /**
    //  * Tests Phalcon\Mvc\Model :: find()
    //  *
    //  * @author Phalcon Team <team@phalcon.io>
    //  * @since  2020-02-01
    //  *
    //  * @group  mysql
    //  * @group  pgsql
    //  * @group  sqlite
    //  */
    // public function mvcModelFindWithCache(DatabaseTester $I)
    // {
    //     $I->wantToTest('Mvc\Model - find() - with cache');

    //     $file = outputDir('data-/my/-c/ac/my-cache');
    //     $I->safeDeleteFile($file);

    //     /** @var PDO $connection */
    //     $connection = $I->getConnection();
    //     $migration  = new ObjectsMigration($connection);
    //     $migration->insert(1, 'random data', 1);

    //     $options = [
    //         'defaultSerializer' => 'Json',
    //         'storageDir'        => outputDir(),
    //         'lifetime'          => 172800,
    //         'prefix'            => 'data-',
    //     ];

    //     // Models Cache setup
    //     $serializerFactory = new SerializerFactory();
    //     $adapterFactory    = new AdapterFactory($serializerFactory);
    //     $adapter           = $adapterFactory->newInstance('stream', $options);
    //     $cache             = new Cache($adapter);

    //     $this->container->setShared('modelsCache', $cache);

    //     /**
    //      * Get the records (should cache the resultset)
    //      */
    //     $data = Objects::find(
    //         [
    //             'cache' => [
    //                 'key' => 'my-cache',
    //             ],
    //         ]
    //     );

    //     /**
    //      * See the file created
    //      */
    //     $I->seeFileFound($file);

    //     $I->assertEquals(1, count($data));

    //     $record = $data[0];
    //     $I->assertEquals(1, $record->obj_id);
    //     $I->assertEquals('random data', $record->obj_name);

    //     /**
    //      * Get the models cache
    //      */
    //     $modelsCache = $this->container->get('modelsCache');

    //     $exists = $modelsCache->has('my-cache');
    //     $I->assertTrue($exists);

    //     /**
    //      * Get the data now from the cache
    //      */
    //     $data = $modelsCache->get('my-cache');

    //     $I->assertEquals(1, count($data));

    //     $record = $data[0];
    //     $I->assertEquals(1, $record->obj_id);
    //     $I->assertEquals('random data', $record->obj_name);
    // }

    // /**
    //  * Tests Phalcon\Mvc\Model :: find() - second iteration of Resultset
    //  *
    //  * @author Phalcon Team <team@phalcon.io>
    //  * @since  2020-10-17
    //  *
    //  * @see https://github.com/phalcon/cphalcon/issues/15065
    //  *
    //  * @group  mysql
    //  * @group  pgsql
    //  * @group  sqlite
    //  */
    // public function mvcModelFindResultsetSecondIteration(DatabaseTester $I)
    // {
    //     $I->wantToTest('Mvc\Model - find() - second iteration of Resultset');

    //     /** @var PDO $connection */
    //     $connection = $I->getConnection();

    //     $customersMigration = new CustomersMigration($connection);
    //     $customersMigration->clear();
    //     $customersMigration->insert(1, 1, uniqid('cust-', true), uniqid('cust-', true));
    //     $customersMigration->insert(2, 0, uniqid('cust-', true), uniqid('cust-', true));

    //     $customers = Customers::find();

    //     $I->assertCount(2, $customers);

    //     /**
    //      * First iteration
    //      *
    //      * @var Customers $customer
    //      */
    //     foreach ($customers as $customer) {
    //         $I->assertNotNull(
    //             $customer->getId()
    //         );

    //         $I->assertIsNumeric(
    //             $customer->getId()
    //         );
    //     }

    //     /**
    //      * Second iteration
    //      *
    //      * @var Customers $secondCustomer
    //      */
    //     foreach ($customers as $secondCustomer) {
    //         $I->assertNotNull(
    //             $secondCustomer->getId()
    //         );

    //         $I->assertIsNumeric(
    //             $secondCustomer->getId()
    //         );
    //     }
    // }

    // /**
    //  * Tests Phalcon\Mvc\Model :: find() - with cache/exception
    //  *
    //  * @author Phalcon Team <team@phalcon.io>
    //  * @since  2021-05-10
    //  *
    //  * @group  mysql
    //  * @group  pgsql
    //  * @group  sqlite
    //  */
    // public function mvcModelFindWithCacheException(DatabaseTester $I)
    // {
    //     $I->wantToTest('Mvc\Model - find() - with cache - exception');

    //     $I->expectThrowable(
    //         new Exception(
    //             "Cache service must be an object implementing " .
    //             "Phalcon\Cache\CacheInterface"
    //         ),
    //         function () {
    //             $options = [
    //                 'storageDir' => outputDir(),
    //                 'lifetime'   => 172800,
    //                 'prefix'     => 'data-',
    //             ];

    //             // Models Cache setup
    //             $serializerFactory = new SerializerFactory();
    //             $adapterFactory    = new AdapterFactory($serializerFactory);
    //             $adapter           = $adapterFactory->newInstance('stream', $options);

    //             $this->container->setShared('modelsCache', $adapter);

    //             Objects::find(
    //                 [
    //                     'cache' => [
    //                         'key' => 'my-cache',
    //                     ],
    //                 ]
    //             );
    //         }
    //     );
    // }

    // /**
    //  * Tests Phalcon\Mvc\Model :: find() - private property with Redis cache
    //  *
    //  * @author Phalcon Team <team@phalcon.io>
    //  * @since  2021-05-25
    //  * @issue  15439
    //  *
    //  * @group  mysql
    //  * @group  pgsql
    //  * @group  sqlite
    //  */
    // public function mvcModelFindPrivatePropertyWithRedisCache(DatabaseTester $I)
    // {
    //     $I->wantToTest('Mvc\Model - find() - private property with Redis cache');

    //     /** @var PDO $connection */
    //     $connection = $I->getConnection();
    //     $migration  = new InvoicesMigration($connection);
    //     $migration->insert(1, 1, 1, 'Test', 101);

    //     $cacheKey = uniqid('redis-');

    //     /**
    //      * Find without models cache
    //      */
    //     /** @var Invoices $original */
    //     $original = Invoices::find(
    //         [
    //             'conditions' => 'inv_id = :inv_id:',
    //             'bind'       => [
    //                 'inv_id' => 1,
    //             ],
    //         ]
    //     );

    //     $I->assertCount(1, $original);

    //     $record = $original[0];
    //     $actual = $record->getIsActive();

    //     $I->assertTrue($actual);

    //     // Models Cache setup
    //     $serializerFactory = new SerializerFactory();
    //     $adapterFactory    = new AdapterFactory($serializerFactory);
    //     $adapter           = $adapterFactory->newInstance('redis', getOptionsRedis());
    //     $cache             = new Cache($adapter);
    //     $this->container->setShared('modelsCache', $cache);

    //     /**
    //      * Find it - so that we can use the models cache now
    //      */
    //     /** @var Invoices $cached */
    //     $cached = Invoices::find(
    //         [
    //             'conditions' => 'inv_id = :inv_id:',
    //             'bind'       => [
    //                 'inv_id' => 1,
    //             ],
    //             'cache'      => [
    //                 'key'      => $cacheKey,
    //                 'lifetime' => 60,
    //             ],
    //         ]
    //     );

    //     $I->assertCount(1, $cached);

    //     $record = $cached[0];
    //     $actual = $record->getIsActive();
    //     $I->assertTrue($actual);

    //     /**
    //      * Delete the record just in case to ensure we get it from the cache
    //      */
    //     $result = $original->delete();
    //     $I->assertNotFalse($result);

    //     /**
    //      * Ensure we do not have anything in the db
    //      */
    //     /** @var Invoices $original */
    //     $original = Invoices::find(
    //         [
    //             'conditions' => 'inv_id = :inv_id:',
    //             'bind'       => [
    //                 'inv_id' => 1,
    //             ],
    //         ]
    //     );

    //     $I->assertCount(0, $original);

    //     /**
    //      * Finally get it back from the cache
    //      */
    //     /** @var Invoices $cached */
    //     $cached = Invoices::find(
    //         [
    //             'conditions' => 'inv_id = :inv_id:',
    //             'bind'       => [
    //                 'inv_id' => 1,
    //             ],
    //             'cache'      => [
    //                 'key'      => $cacheKey,
    //                 'lifetime' => 60,
    //             ],
    //         ]
    //     );

    //     $I->assertCount(1, $cached);

    //     $record = $cached[0];
    //     $actual = $record->getIsActive();
    //     $I->assertTrue($actual);

    //     /**
    //      * delete the cached entry
    //      */
    //     $result = $cache->delete($cacheKey);
    //     $I->assertTrue($result);
    // }
}
