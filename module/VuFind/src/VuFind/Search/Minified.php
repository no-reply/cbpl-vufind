<?php
/**
 * VuFind Minified Search Object
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\Search;

/**
 * A minified search object used exclusively for trimming a search object down to its
 * barest minimum size before storage in a cookie or database.
 *
 * It still contains enough data granularity to programmatically recreate search
 * URLs.
 *
 * This class isn't intended for general use, but simply a way of storing/retrieving
 * data from a search object:
 *
 * eg. Store
 * $searchHistory[] = serialize($this->minify());
 *
 * eg. Retrieve
 * $searchObject = unserialize($search);
 * $searchObject->deminify($manager);
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_search_object Wiki
 */
class Minified
{
    public $t = array();
    public $f = array();
    public $id, $i, $s, $r, $ty, $pc, $rc;

    /**
     * Constructor. Building minified object from the
     *    searchObject passed in. Needs to be kept
     *    up-to-date with the deminify() function on
     *    searchObject.
     *
     * @param object $searchObject Search Object to minify
     */
    public function __construct($searchObject)
    {
        // Most values will transfer without changes
        $this->id = $searchObject->getSearchId();
        $this->i  = $searchObject->getStartTime();
        $this->s  = $searchObject->getQuerySpeed();
        $this->r  = $searchObject->getResultTotal();
        $this->ty = $searchObject->getParams()->getSearchType();
        $this->cl = $searchObject->getParams()->getSearchClassId();

        // Search terms, we'll shorten keys
        $tempTerms = $searchObject->getParams()->getSearchTerms();
        foreach ($tempTerms as $term) {
            $newTerm = array();
            foreach ($term as $k => $v) {
                switch ($k) {
                case 'join':
                    $newTerm['j'] = $v;
                    break;
                case 'index':
                    $newTerm['i'] = $v;
                    break;
                case 'lookfor':
                    $newTerm['l'] = $v;
                    break;
                case 'group':
                    $newTerm['g'] = array();
                    foreach ($v as $line) {
                        $search = array();
                        foreach ($line as $k2 => $v2) {
                            switch ($k2) {
                            case 'bool':
                                $search['b'] = $v2;
                                break;
                            case 'field':
                                $search['f'] = $v2;
                                break;
                            case 'lookfor':
                                $search['l'] = $v2;
                                break;
                            }
                        }
                        $newTerm['g'][] = $search;
                    }
                    break;
                }
            }
            $this->t[] = $newTerm;
        }

        // It would be nice to shorten filter fields too, but
        //      it would be a nightmare to maintain.
        $this->f = $searchObject->getParams()->getFilters();
    }

    /**
     * Turn the current object into search results.
     *
     * @param \VuFind\Search\Manager $manager Search manager
     *
     * @return \VuFind\Search\Base\Results
     */
    public function deminify(\VuFind\Search\Manager $manager)
    {
        // Figure out the parameter and result classes based on the search class ID:
        $this->populateClassNames();

        // Deminify everything:
        $params = $manager->setSearchClassId($this->cl)->getParams();
        $params->deminify($this);
        $results = $manager->setSearchClassId($this->cl)->getResults($params);
        $results->deminify($this);

        return $results;
    }

    /**
     * Support method for deminify -- populate parameter class and results class
     * if missing (for legacy compatibility).
     *
     * @return void
     */
    protected function populateClassNames()
    {
        // If this is a legacy entry from VuFind 1.x, we need to figure out the
        // search class ID for the object we're about to construct:
        if (!isset($this->cl)) {
            $fixType = true;    // by default, assume we need to fix type
            switch($this->ty) {
            case 'Summon':
            case 'SummonAdvanced':
                $this->cl = 'Summon';
                break;
            case 'WorldCat':
            case 'WorldCatAdvanced':
                $this->cl = 'WorldCat';
                break;
            case 'Authority':
            case 'AuthorityAdvanced':
                $this->cl = 'SolrAuth';
                break;
            default:
                $this->cl = 'Solr';
                $fixType = false;
                break;
            }

            // Now rewrite the type if necessary (only needed for legacy objects):
            if ($fixType) {
                $this->ty = (substr($this->ty, -8) == 'Advanced')
                    ? 'advanced' : 'basic';
            }
        }
    }
}
