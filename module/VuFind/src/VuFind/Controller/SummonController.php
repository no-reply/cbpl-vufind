<?php
/**
 * Summon Controller
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;
use Zend\Mvc\MvcEvent;

/**
 * Summon Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

class SummonController extends AbstractSearch
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->searchClassId = 'Summon';
        $this->useResultScroller = false;
        parent::__construct();
    }

    /**
     * preDispatch -- block access when appropriate.
     *
     * @param MvcEvent $e Event object
     *
     * @return void
     */
    public function preDispatch(MvcEvent $e)
    {
        $this->layout()->poweredBy
            = 'Powered by Summon™ from Serials Solutions, a division of ProQuest.';
    }

    /**
     * Register the default events for this controller
     *
     * @return void
     */
    protected function attachDefaultListeners()
    {
        parent::attachDefaultListeners();
        $events = $this->getEventManager();
        $events->attach(MvcEvent::EVENT_DISPATCH, array($this, 'preDispatch'), 1000);
    }

    /**
     * Handle an advanced search
     *
     * @return mixed
     */
    public function advancedAction()
    {
        // Standard setup from base class:
        $view = parent::advancedAction();

        // Set up facet information:
        $view->facetList = $this->processAdvancedFacets(
            $this->getAdvancedFacets()->getFacetList(), $view->saved
        );

        return $view;
    }

    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction()
    {
        return $this->createViewModel(
            array('results' => $this->getAdvancedFacets())
        );
    }

    /**
     * Search action -- call standard results action
     *
     * @return mixed
     */
    public function searchAction()
    {
        return $this->resultsAction();
    }

    /**
     * Return a Search Results object containing advanced facet information.  This
     * data may come from the cache, and it is currently shared between the Home
     * page and the Advanced search screen.
     *
     * @return \VuFind\Search\Summon\Results
     */
    protected function getAdvancedFacets()
    {
        // Check if we have facet results cached, and build them if we don't.
        $cache = $this->getServiceLocator()->get('CacheManager')->getCache('object');
        if (!($results = $cache->getItem('summonSearchHomeFacets'))) {
            $sm = $this->getSearchManager();
            $params = $sm->setSearchClassId('Summon')->getParams();
            $params->addFacet('Language,or,1,20');
            $params->addFacet('ContentType,or,1,20', 'Format');

            // We only care about facet lists, so don't get any results:
            $params->setLimit(0);

            $results = $sm->setSearchClassId('Summon')->getResults($params);
            // force processing for cache
            $results->getResults();

            // Temporarily remove the service manager so we can cache the
            // results (otherwise we'll get errors about serializing closures):
            $results->unsetServiceLocator();
            $cache->setItem('summonSearchHomeFacets', $results);
        }

        // Restore the real service locator to the object:
        $results->restoreServiceLocator($this->getServiceLocator());
        return $results;
    }

    /**
     * Process the facets to be used as limits on the Advanced Search screen.
     *
     * @param array  $facetList    The advanced facet values
     * @param object $searchObject Saved search object (false if none)
     *
     * @return array               Sorted facets, with selected values flagged.
     */
    protected function processAdvancedFacets($facetList, $searchObject = false)
    {
        // Process the facets, assuming they came back
        foreach ($facetList as $facet => $list) {
            foreach ($list['list'] as $key => $value) {
                // Build the filter string for the URL:
                $fullFilter = $facet.':"'.$value['value'].'"';

                // If we haven't already found a selected facet and the current
                // facet has been applied to the search, we should store it as
                // the selected facet for the current control.
                if ($searchObject
                    && $searchObject->getParams()->hasFilter($fullFilter)
                ) {
                    $facetList[$facet]['list'][$key]['selected'] = true;
                    // Remove the filter from the search object -- we don't want
                    // it to show up in the "applied filters" sidebar since it
                    // will already be accounted for by being selected in the
                    // filter select list!
                    $searchObject->getParams()->removeFilter($fullFilter);
                }
            }
        }
        return $facetList;
    }
}

