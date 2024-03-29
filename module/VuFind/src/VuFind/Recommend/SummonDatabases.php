<?php
/**
 * SummonDatabases Recommendations Module
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
 * SummonDatabases Recommendations Module
 *
 * This class provides database recommendations by doing a search of Summon.
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class SummonDatabases extends AbstractSearchManagerAwareModule
{
    protected $databases;
    protected $requestParam = 'lookfor';
    protected $lookfor;

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
        // Only one setting -- HTTP request field containing search terms (ignored
        // if $searchObject is Summon type).
        $this->requestParam = empty($settings) ? $this->requestParam : $settings;
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
        // Save search query in case we need it later:
        $this->lookfor = $request->get($this->requestParam);
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
        // If we received a Summon search object, we'll use that.  If not, we need
        // to create a new Summon search object using the specified request 
        // parameter for search terms.
        if ($results->getParams()->getSearchClassId() != 'Summon') {
            $sm = $this->getSearchManager();
            $params = $sm->setSearchClassId('Summon')->getParams();
            $params->setBasicSearch($this->lookfor);
            $results = $sm->setSearchClassId('Summon')->getResults($params);
            $results->performAndProcessSearch();
        }
        $this->databases = $results->getDatabaseRecommendations();
    }

    /**
     * Get database results.
     *
     * @return array
     */
    public function getResults()
    {
        return $this->databases;
    }
}