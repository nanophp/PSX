<?php
/*
 * PSX is a open source PHP framework to develop RESTful APIs.
 * For the current version and informations visit <http://phpsx.org>
 *
 * Copyright 2010-2017 Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace PSX\Sql\Tests;

use Doctrine\DBAL\Connection;
use PSX\Sql\Table\Reader;
use PSX\Sql\TableInterface;
use PSX\Sql\TableManager;

/**
 * TableManagerTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    http://phpsx.org
 */
class TableManagerTest extends \PHPUnit_Extensions_Database_TestCase
{
    public function getDataSet()
    {
        return $this->createFlatXMLDataSet(__DIR__ . '/table_fixture.xml');
    }

    public function getConnection()
    {
        return $this->createDefaultDBConnection(getConnection()->getWrappedConnection(), '');
    }

    public function testGetTable()
    {
        $manager = new TableManager(getConnection());

        $table = $manager->getTable(TestTable::class);

        $this->assertInstanceOf(TableInterface::class, $table);
        $this->assertEquals('psx_handler_comment', $table->getName());
        $this->assertEquals(['id', 'userId', 'title', 'date'], array_keys($table->getColumns()));
    }

    public function testGetTableWithReader()
    {
        $manager = new TableManager(getConnection(), new Reader\Schema(getConnection()));

        $table = $manager->getTable('psx_handler_comment');

        $this->assertInstanceOf(TableInterface::class, $table);
        $this->assertEquals('psx_handler_comment', $table->getName());
        $this->assertEquals(['id', 'userId', 'title', 'date'], array_keys($table->getColumns()));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetTableInvalidTable()
    {
        $manager = new TableManager(getConnection());
        $manager->getTable('PSX\Sql\FooTable');
    }

    public function testGetConnection()
    {
        $manager = new TableManager(getConnection());

        $this->assertInstanceOf(Connection::class, $manager->getConnection());
    }
}
