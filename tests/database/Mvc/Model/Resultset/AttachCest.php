<?php

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the
 * LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Tests\Database\Mvc\Model\Resultset;

use DatabaseTester;
use Phalcon\Tests\Fixtures\Migrations\CustomersMigration;
use Phalcon\Tests\Fixtures\Migrations\InvoicesMigration;
use Phalcon\Tests\Fixtures\Traits\DiTrait;
use Phalcon\Tests\Fixtures\Traits\RecordsTrait;
use Phalcon\Tests\Models\Customers;
use Phalcon\Tests\Models\Invoices;

class AttachCest
{
    use DiTrait;
    use RecordsTrait;

    /**
     * @var InvoicesMigration
     */
    private $invoiceMigration;

    /**
     * Executed before each test
     *
     * @param  DatabaseTester $I
     * @return void
     */
    public function _before(DatabaseTester $I): void
    {
        $this->setNewFactoryDefault();
        $this->setDatabase($I);
    }

    /**
     * Tests Mvc\Model\Resultset :: getFirst() - Issue 15027
     *
     * @param  DatabaseTester $I
     *
     * @author Phalcon Team <team@phalcon.io>
     * @since  2020-05-06
     * @issue  15027
     *
     * @group  mysql
     * @group  pgsql
     */
    public function mvcModelResultsetAttachNew(DatabaseTester $I)
    {
        $I->wantToTest('Mvc\Model\Resultset :: AttachNew()');

        $connection = $I->getConnection();

        $custId = 2;

        $firstName = uniqid('cust-', true);
        $lastName  = uniqid('cust-', true);

        $customersMigration = new CustomersMigration($connection);
        $customersMigration->insert($custId, 0, $firstName, $lastName);

        $paidInvoiceId   = 4;
        $unpaidInvoiceId = 5;

        $title = uniqid('inv-');

        $invoicesMigration = new InvoicesMigration($connection);
        $invoicesMigration->insert(
            $paidInvoiceId,
            $custId,
            Invoices::STATUS_PAID,
            $title . '-paid'
        );

        $invoice = new Invoices();
        $invoice->inv_id = 5;
        $invoice->inv_status_flag = Invoices::STATUS_UNPAID;
        $invoice->inv_title = $title . '-unpaid';

        /**
         * @var Customers $customer
         */
        $customer = Customers::findFirst($custId);

        $expected = 1;
        $actual   = $customer->invoices->count();
        $I->assertEquals($expected, $actual);

        $customer->invoices = $customer->invoices;
        $customer->invoices->attachNew($invoice);
        
        $expected = 2;
        $actual   = $customer->invoices->count();
        $I->assertEquals($expected, $actual);

        $invoiceRelated = $customer->invoices->getFirst();

        $expected = 4;
        $actual   = $invoiceRelated->inv_id;
        $I->assertEquals($expected, $actual);

        $invoiceRelated->inv_title = $title;
        $customer->invoices->attachRelated($invoiceRelated);

        $expected = 2;
        $actual   = $customer->invoices->count();
        $I->assertEquals($expected, $actual);

        $customer->save();

        $expected = 2;
        $actual   = $customer->invoices->count();
        $I->assertEquals($expected, $actual);

        $I->assertNotNull($invoice->inv_id);
    }

    /**
     * Tests Mvc\Model\Resultset :: getFirst() - Issue 15027
     *
     * @param  DatabaseTester $I
     *
     * @author Phalcon Team <team@phalcon.io>
     * @since  2020-05-06
     * @issue  15027
     *
     * @group  mysql
     * @group  pgsql
     */
    public function mvcModelResultsetDettach(DatabaseTester $I)
    {
        $I->wantToTest('Mvc\Model\Resultset :: Dettach()');

        $connection = $I->getConnection();

        $custId = 2;

        $firstName = uniqid('cust-', true);
        $lastName  = uniqid('cust-', true);

        $customersMigration = new CustomersMigration($connection);
        $customersMigration->insert($custId, 0, $firstName, $lastName);

        $paidInvoiceId   = 4;
        $unpaidInvoiceId = 5;

        $title = uniqid('inv-');

        $invoicesMigration = new InvoicesMigration($connection);
        $invoicesMigration->insert(
            $paidInvoiceId,
            $custId,
            Invoices::STATUS_PAID,
            $title . '-paid'
        );

        $invoice = new Invoices();
        $invoice->inv_id = 5;
        $invoice->inv_status_flag = Invoices::STATUS_UNPAID;
        $invoice->inv_title = $title . '-unpaid';

        /**
         * @var Customers $customer
         */
        $customer = Customers::findFirst($custId);

        $expected = 1;
        $actual   = $customer->invoices->count();
        $I->assertEquals($expected, $actual);

        $customer->invoices = $customer->invoices;
        $customer->invoices->attachNew($invoice);
        
        $expected = 2;
        $actual   = $customer->invoices->count();
        $I->assertEquals($expected, $actual);

        $invoiceRelated = $customer->invoices->getFirst();

        $expected = 4;
        $actual   = $invoiceRelated->inv_id;
        $I->assertEquals($expected, $actual);

        $customer->invoices->detachRelated($invoiceRelated);

        $expected = 2;
        $actual   = $customer->invoices->count();
        $I->assertEquals($expected, $actual);

        $customer->save();

        $customer = Customers::findFirst($custId);
        $expected = 1;
        $actual   = $customer->invoices->count();
        $I->assertEquals($expected, $actual);

        $I->assertNotNull($invoice->inv_id);
    }
}
