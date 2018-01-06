<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\StaticConstructorPass;

class StaticConstructorPassTest extends CodeCleanerTestCase
{
    protected function setUp()
    {
        $this->setPass(new StaticConstructorPass());
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testProcessInvalidStatement($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    /**
     * @dataProvider invalidParserStatements
     * @expectedException \Psy\Exception\ParseErrorException
     */
    public function testProcessInvalidStatementCatchedByParser($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function invalidStatements()
    {
        return [
            ['class A { public static function A() {}}'],
            ['class A { private static function A() {}}'],
        ];
    }

    public function invalidParserStatements()
    {
        return [
            ['class A { public static function __construct() {}}'],
            ['class A { private static function __construct() {}}'],
            ['class A { private static function __construct() {} public function A() {}}'],
            ['namespace B; class A { private static function __construct() {}}'],
        ];
    }

    /**
     * @dataProvider validStatements
     */
    public function testProcessValidStatement($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
        $this->assertTrue(true);
    }

    public function validStatements()
    {
        return [
            ['class A { public static function A() {} public function __construct() {}}'],
            ['class A { private function __construct() {} public static function A() {}}'],
            ['namespace B; class A { private static function A() {}}'],
        ];
    }
}
