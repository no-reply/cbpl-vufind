<?php
/**
 * VuFindDate Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Tests\Date;
use VuFind\Date\Converter, VuFind\Exception\Date as DateException,
    Zend\Config\Config;

/**
 * VuFindDate Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class ConverterTest extends \VuFind\Tests\TestCase
{
    protected $savedDateFormat = null;
    protected $savedTimeFormat = null;

    /**
     * Test citation generation
     *
     * @return void
     */
    public function testDates()
    {
        // Build an object to test with (using empty configuration to ensure default
        // settings):
        $date = new Converter(new Config(array()));

        // Try some conversions:
        $this->assertEquals(
            '11-29-1973', $date->convertToDisplayDate('U', 123456879)
        );
        $this->assertEquals(
            '11-29-1973', $date->convertToDisplayDate('m-d-y', '11-29-73')
        );
        $this->assertEquals(
            '11-29-1973', $date->convertToDisplayDate('m-d-y', '11-29-1973')
        );
        $this->assertEquals(
            '11-29-1973', $date->convertToDisplayDate('m-d-y H:i', '11-29-73 23:01')
        );
        $this->assertEquals(
            '23:01', $date->convertToDisplayTime('m-d-y H:i', '11-29-73 23:01')
        );
        $this->assertEquals(
            '01-02-2001', $date->convertToDisplayDate('m-d-y', '01-02-01')
        );
        $this->assertEquals(
            '01-02-2001', $date->convertToDisplayDate('m-d-y', '01-02-2001')
        );
        $this->assertEquals(
            '01-02-2001', $date->convertToDisplayDate('m-d-y H:i', '01-02-01 05:11')
        );
        $this->assertEquals(
            '05:11', $date->convertToDisplayTime('m-d-y H:i', '01-02-01 05:11')
        );
        $this->assertEquals(
            '01-02-2001', $date->convertToDisplayDate('Y-m-d', '2001-01-02')
        );
        $this->assertEquals(
            '01-02-2001',
            $date->convertToDisplayDate('Y-m-d H:i', '2001-01-02 05:11')
        );
        $this->assertEquals(
            '05:11', $date->convertToDisplayTime('Y-m-d H:i', '2001-01-02 05:11')
        );
        $this->assertEquals(
            '01-2001', $date->convertFromDisplayDate('m-Y', '01-02-2001')
        );

        // Check for proper handling of known problems:
        try {
            $bad = $date->convertToDisplayDate('U', 'invalid');
            $this->fail('Expected exception did not occur');
        } catch (DateException $e) {
            $this->assertTrue(
                (bool)stristr($e->getMessage(), 'failed to parse time string')
            );
        }
        try {
            $bad = $date->convertToDisplayDate('d-m-Y', '31-02-2001');
            $this->fail('Expected exception did not occur');
        } catch (DateException $e) {
            $this->assertTrue(
                (bool)stristr($e->getMessage(), 'parsed date was invalid')
            );
        }
    }
}