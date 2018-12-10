<?php
declare(strict_types=1);

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalconphp.com>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

namespace Phalcon\Test\Unit\Mvc\Router\Route;

use UnitTester;

/**
 * Class SetHttpMethodsCest
 *
 * @package Phalcon\Test\Unit\Mvc\Router\Route
 */
class SetHttpMethodsCest
{
    /**
     * Tests Phalcon\Mvc\Router\Route :: setHttpMethods()
     *
     * @param UnitTester $I
     *
     * @author Phalcon Team <team@phalconphp.com>
     * @since  2018-11-13
     */
    public function mvcRouterRouteSetHttpMethods(UnitTester $I)
    {
        $I->wantToTest("Mvc\Router\Route - setHttpMethods()");
        $I->skipTest("Need implementation");
    }
}