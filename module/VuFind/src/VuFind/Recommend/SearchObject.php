<?php
/**
 * Abstract SearchObject Recommendations Module (needs to be extended to use
 * a particular search object).
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
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\Recommend;

/**
 * Abstract SearchObject Recommendations Module (needs to be extended to use
 * a particular search object).
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
abstract class SearchObject extends AbstractSearchManagerAwareModule
{
    protected $results;
    protected $limit;
    protected $requestParam;

    /**
     * setConfig
     *
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $settings = explode(':', $settings);
        $this->requestParam = empty($settings[0]) ? 'lookfor' : $settings[0];
        $this->limit
            = (isset($settings[1]) && is_numeric($settings[1]) && $settings[1] > 0)
            ? intval($settings[1]) : 5;
    }

    /**
     * init
     *
     * Called at the end of the Search Params objects' initFromRequest() method.
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Zend\StdLib\Parameters    $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
        // Build a search parameters object:
        $sm = $this->getSearchManager()->setSearchClassId($this->getSearchClassId());
        $params = $sm->getParams();
        $params->setLimit($this->limit);
        $params->setBasicSearch($request->get($this->requestParam));

        // Perform the search:
        $this->results = $sm->setSearchClassId($this->getSearchClassId())
            ->getResults($params);
        $this->results->performAndProcessSearch();
    }

    /**
     * process
     *
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        // No action needed.
    }

    /**
     * Get search results.
     *
     * @return \VuFind\Search\Base\Results
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Get the search class ID to use for building search objects.
     *
     * @return string
     */
    abstract protected function getSearchClassId();
}
