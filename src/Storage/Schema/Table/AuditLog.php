<?php

namespace Bolt\Extension\Bolt\Audit\Storage\Schema\Table;

use Bolt\Storage\Database\Schema\Table\BaseTable;

/**
 * AuditLog table.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class AuditLog extends BaseTable
{
    /**
     * {@inheritdoc}
     */
    protected function addColumns()
    {
        $this->table->addColumn('id',       'integer',  ['autoincrement' => true]);
        $this->table->addColumn('event',    'string',   ['notnull' => true,  'length' => 32]);
        $this->table->addColumn('reason',   'integer',  ['notnull' => false]);
        $this->table->addColumn('datetime', 'datetime', ['notnull' => true]);
        $this->table->addColumn('username', 'string',   ['notnull' => false, 'length' => 64]);
        $this->table->addColumn('ip',       'string',   ['notnull' => true,  'length' => 32]);
        $this->table->addColumn('uri',      'string',   ['notnull' => false, 'length' => 128]);
        $this->table->addColumn('message',  'string',   ['notnull' => true,  'length' => 1024]);
    }

    /**
     * {@inheritdoc}
     */
    protected function addIndexes()
    {
        $this->table->addIndex(['event']);
        $this->table->addIndex(['reason']);
        $this->table->addIndex(['username']);
        $this->table->addIndex(['ip']);
        $this->table->addIndex(['uri']);
    }

    /**
     * {@inheritdoc}
     */
    protected function setPrimaryKey()
    {
        $this->table->setPrimaryKey(['id']);
    }
}
