<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Test\Legacy;

use Magento\Framework\Test\Utility\Files;
use Magento\Framework\Test\Utility\AggregateInvoker;

/**
 * Tests to find obsolete install/upgrade schema/data scripts
 */
class InstallUpgradeTest extends \PHPUnit_Framework_TestCase
{
    public function testForOldInstallUpgradeScripts()
    {
        $invoker = new AggregateInvoker($this);
        $invoker(
            /**
             * @param string $file
             */
            function ($file) {
                $this->assertStringStartsNotWith(
                    'install-',
                    basename($file),
                    'Install scripts are obsolete. Please create class InstallSchema in module\'s Setup folder'
                );
                $this->assertStringStartsNotWith(
                    'data-install-',
                    basename($file),
                    'Install scripts are obsolete. Please create class InstallData in module\'s Setup folder'
                );
                $this->assertStringStartsNotWith(
                    'upgrade-',
                    basename($file),
                    'Upgrade scripts are obsolete. Please create class UpgradeSchema in module\'s Setup folder'
                );
                $this->assertStringStartsNotWith(
                    'data-upgrade-',
                    basename($file),
                    'Upgrade scripts are obsolete. Please create class UpgradeData in module\'s Setup folder'
                );
                $this->assertStringStartsNotWith(
                    'recurring',
                    basename($file),
                    'Recurring scripts are obsolete. Please create class Recurring in module\'s Setup folder'
                );
                $this->fail(
                    'Invalid directory. Please convert data/sql scripts to a class within module\'s Setup folder'
                );
            },
            $this->convertArray(
                Files::init()->getFiles([BP . '/app/code/*/*/sql', BP . '/app/code/*/*/data'], '*.php')
            )
        );
    }

    /**
     * Converts from string array to array of arrays.
     *
     * @param array $stringArray
     * @return array
     */
    private function convertArray($stringArray)
    {
        $array = [];
        foreach ($stringArray as $item) {
            $array[] = [$item];
        }
        return $array;
    }
}