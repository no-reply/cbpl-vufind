<?php
/**
 * Autocomplete handler plugin manager
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
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/creating_a_session_handler Wiki
 */
namespace VuFind\Autocomplete;
use VuFind\Config\Reader as ConfigReader;

/**
 * Autocomplete handler plugin manager
 *
 * @category VuFind2
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/creating_a_session_handler Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return 'VuFind\Autocomplete\AutocompleteInterface';
    }

    /**
     * getSuggestions
     *
     * This returns an array of suggestions based on current request parameters.
     * This logic is present in the factory class so that it can be easily shared
     * by multiple AJAX handlers.
     *
     * @param \Zend\Stdlib\Parameters $request    The user request
     * @param string                  $typeParam  Request parameter containing search
     * type
     * @param string                  $queryParam Request parameter containing query
     * string
     *
     * @return array
     */
    public function getSuggestions($request, $typeParam = 'type', $queryParam = 'q')
    {
        // Process incoming parameters:
        $type = $request->get($typeParam, '');
        $query = $request->get($queryParam, '');

        // get Autocomplete_Type config
        $searcher = $request->get('searcher', 'Solr');
        $options = $this->getServiceLocator()->get('SearchManager')
            ->setSearchClassId($searcher)->getOptionsInstance();
        $config = ConfigReader::getConfig($options->getSearchIni());
        $types = isset($config->Autocomplete_Types) ?
            $config->Autocomplete_Types->toArray() : array();

        // Figure out which handler to use:
        if (!empty($type) && isset($types[$type])) {
            $module = $types[$type];
        } else if (isset($config->Autocomplete->default_handler)) {
            $module = $config->Autocomplete->default_handler;
        } else {
            $module = false;
        }

        // Get suggestions:
        if ($module) {
            if (strpos($module, ':') === false) {
                $module .= ':'; // force colon to avoid warning in explode below
            }
            list($name, $params) = explode(':', $module, 2);
            $handler = $this->get($name);
            $handler->setConfig($params);
        }

        return (isset($handler) && is_object($handler))
            ? array_values($handler->getSuggestions($query)) : array();
    }
}