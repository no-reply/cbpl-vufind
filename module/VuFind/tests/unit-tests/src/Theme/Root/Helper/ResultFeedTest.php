<?php
/**
 * ResultFeed Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
namespace VuFind\Test\Theme\Root\Helper;
use VuFind\Theme\Root\Helper\ResultFeed;

/**
 * ResultFeed Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
class ResultFeedTest extends \VuFind\Tests\ViewHelperTestCase
{
    /**
     * Get plugins to register to support view helper being tested
     *
     * @return array
     */
    protected function getPlugins()
    {
        $recordLink = $this->getMock('VuFind\Theme\Root\Helper\RecordLink');
        $recordLink->expects($this->any())->method('getUrl')
            ->will($this->returnValue('test/url'));
        return array(
            'recordlink' => $recordLink
        );
    }

    /**
     * Test feed generation
     *
     * @return void
     */
    public function testRSS()
    {
        // Set up a request -- we'll sort by title to ensure a predictable order
        // for the result list (relevance or last_indexed may lead to unstable test
        // cases).
        $request = new \Zend\Stdlib\Parameters();
        $request->set('lookfor', 'id:testbug2 OR id:testsample1');
        $request->set('skip_rss_sort', 1);
        $request->set('sort', 'title');
        $request->set('view', 'rss');

        $sm = $this->getSearchManager();
        $params = $sm->setSearchClassId('Solr')->getParams();
        $params->initFromRequest($request);

        $results = $sm->setSearchClassId('Solr')->getResults($params);
        $helper = new ResultFeed();
        $helper->setView($this->getPhpRenderer($this->getPlugins()));
        $mockTranslator = function ($str) {
            return $str;
        };
        $helper->setTranslator($mockTranslator);
        $feed = $helper->__invoke($results, '/test/path');
        $this->assertTrue(is_object($feed));
        $rss = $feed->export('rss');

        // Make sure it's really an RSS feed:
        $this->assertTrue(strstr($rss, '<rss') !== false);

        // Make sure custom Dublin Core elements are present:
        $this->assertTrue(strstr($rss, 'dc:format') !== false);

        // Now re-parse it and check for some expected values:
        $parsedFeed = \Zend\Feed\Reader\Reader::importString($rss);
        $this->assertEquals(
            $parsedFeed->getDescription(),
            'Displaying the top 2 search results of 2 found'
        );
        $items = array();
        $i = 0;
        foreach ($parsedFeed as $item) {
            $items[$i++] = $item;
        }
        $this->assertEquals(
            $items[1]->getTitle(), 'Journal of rational emotive therapy : '
            . 'the journal of the Institute for Rational-Emotive Therapy.'
        );
    }
}