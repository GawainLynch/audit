<?php

namespace Bolt\Extension\Bolt\Audit\Tests;

use Bolt\Tests\BoltUnitTest;
use Bolt\Extension\Bolt\Audit\AuditExtension;

/**
 * AuditExtension testing class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class AuditExtensionTest extends BoltUnitTest
{
    /**
     * Ensure that the AuditExtension extension loads correctly.
     */
    public function testExtensionBasics()
    {
        $app = $this->getApp(false);
        $extension = new ExtensionNameExtension($app);

        $name = $extension->getName();
        $this->assertSame($name, 'AuditExtension');
        $this->assertInstanceOf('\Bolt\Extension\ExtensionInterface', $extension);
    }
}
