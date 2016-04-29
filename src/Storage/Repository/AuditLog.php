<?php

namespace Bolt\Extension\Bolt\Audit\Storage\Repository;

use Bolt\Extension\Bolt\Audit\Storage\Entity;
use Bolt\Storage\Repository;

/**
 * AuditLog repository.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class AuditLog extends Repository
{
    /**
     * Fetches all audit log records.
     *
     * @return Entity\AuditLog[]
     */
    public function getAuditLogs()
    {
        $query = $this->getAuditLogsQuery();

        return $this->findWith($query);
    }

    public function getAuditLogsQuery()
    {
        $qb = $this->createQueryBuilder();
        $qb->select('*');

        return $qb;
    }
}
