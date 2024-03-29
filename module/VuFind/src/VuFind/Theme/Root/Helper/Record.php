<?php
/**
 * Record driver view helper
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\Theme\Root\Helper;
use VuFind\Config\Reader as ConfigReader, Zend\View\Exception\RuntimeException,
    Zend\View\Helper\AbstractHelper;

/**
 * Record driver view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class Record extends AbstractHelper
{
    protected $contextHelper;
    protected $driver;

    /**
     * Render a template within a record driver folder.
     *
     * @param string $name    Template name to render
     * @param array  $context Variables needed for rendering template; these will
     * be temporarily added to the global view context, then reverted after the
     * template is rendered (default = record driver only).
     *
     * @return string
     */
    protected function renderTemplate($name, $context = null)
    {
        // Set default context if none provided:
        if (is_null($context)) {
            $context = array('driver' => $this->driver);
        }

        // Set up the needed context in the view:
        $oldContext = $this->contextHelper->apply($context);

        // Get the current record driver's class name, then start a loop
        // in case we need to use a parent class' name to find the appropriate
        // template.
        $className = get_class($this->driver);
        while (true) {
            // Guess the template name for the current class:
            $classParts = explode('\\', $className);
            $template = 'RecordDriver/' . array_pop($classParts) . '/' . $name;
            try {
                // Try to render the template....
                $html = $this->view->render($template);
                $this->contextHelper->restore($oldContext);
                return $html;
            } catch (RuntimeException $e) {
                // If the template doesn't exist, let's see if we can inherit a
                // template from a parent class:
                $className = get_parent_class($className);
                if (empty($className)) {
                    // No more parent classes left to try?  Throw an exception!
                    throw new RuntimeException(
                        'Cannot find ' . $name . ' template for record driver: ' .
                        get_class($this->driver)
                    );
                }
            }
        }
    }

    /**
     * Store a record driver object and return this object so that the appropriate
     * template can be rendered.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver object.
     *
     * @return \VuFind\Theme\Root\Helper\Record
     */
    public function __invoke($driver)
    {
        // Set up context helper:
        $contextHelper = $this->getView()->plugin('context');
        $this->contextHelper = $contextHelper($this->getView());

        // Set up driver context:
        $this->driver = $driver;
        return $this;
    }

    /**
     * Render the core metadata area of the record view.
     *
     * @return string
     */
    public function getCoreMetadata()
    {
        return $this->renderTemplate('core.phtml');
    }

    /**
     * Export the record in the requested format.  For legal values, see
     * getExportFormats().
     *
     * @param string $format Export format to display
     *
     * @return string        Exported data
     */
    public function getExport($format)
    {
        $format = strtolower($format);
        return $this->renderTemplate('export-' . $format . '.phtml');
    }

    /**
     * Get an array of strings representing formats in which this record's
     * data may be exported (empty if none).  Legal values: "RefWorks",
     * "EndNote", "MARC", "RDF".
     *
     * @return array Strings representing export formats.
     * @access public
     */
    public function getExportFormats()
    {
        $config = ConfigReader::getConfig();
        $exportConfig = ConfigReader::getConfig('export');

        // Get an array of enabled export formats (from config, or use defaults
        // if nothing in config array).
        $active = isset($config->Export)
            ? $config->Export->toArray()
            : array('RefWorks' => true, 'EndNote' => true);

        // Loop through all possible formats:
        $formats = array();
        foreach ($exportConfig as $format => $details) {
            if (isset($active[$format]) && $active[$format]
                && $this->driver->supportsExport($format)
            ) {
                $formats[] = $format;
            }
        }

        // Send back the results:
        return $formats;
    }

    /**
     * Get the CSS class used to properly render a format.  (Note that this may
     * not be used by every theme).
     *
     * @param string $format Format text to convert into CSS class
     *
     * @return string
     */
    public function getFormatClass($format)
    {
        return $this->renderTemplate(
            'format-class.phtml', array('format' => $format)
        );
    }

    /**
     * Render a list of record formats.
     *
     * @return string
     */
    public function getFormatList()
    {
        return $this->renderTemplate('format-list.phtml');
    }

    /**
     * Render an entry in a favorite list.
     *
     * @param \VuFind\Db\Row\UserList $list Currently selected list (null for
     * combined favorites)
     * @param \VuFind\Db\Row\User     $user Current logged in user (false if none)
     *
     * @return string
     */
    public function getListEntry($list = null, $user = false)
    {
        // Get list of lists containing this entry
        $lists = null;
        if ($user) {
            $lists = $this->driver->getContainingLists($user->id);
        }
        return $this->renderTemplate(
            'list-entry.phtml',
            array(
                'driver' => $this->driver,
                'list' => $list,
                'user' => $user,
                'lists' => $lists
            )
        );
    }

    /**
     * Render previews of the item if configured.
     *
     * @return string
     */
    public function getPreviews()
    {
        $config = ConfigReader::getConfig();
        return $this->renderTemplate(
            'preview.phtml',
            array('driver' => $this->driver, 'config' => $config)
        );
    }

    /**
     * Get the name of the controller used by the record route.
     *
     * @return string
     */
    public function getController()
    {
        // Figure out controller using naming convention based on resource
        // source:
        $source = $this->driver->getResourceSource();
        if ($source == 'VuFind') {
            // "VuFind" is special case -- it refers to Solr, which uses
            // the basic record controller.
            return 'Record';
        }
        // All non-Solr controllers will correspond with the record source:
        return ucwords(strtolower($source)) . 'record';
    }

    /**
     * Render the link of the specified type.
     *
     * @param string $type    Link type
     * @param string $lookfor String to search for at link
     *
     * @return string
     */
    public function getLink($type, $lookfor)
    {
        return $this->renderTemplate(
            'link-' . $type . '.phtml', array('lookfor' => $lookfor)
        );
    }

    /**
     * Render the contents of the specified record tab.
     *
     * @param string $tab Tab to display
     *
     * @return string
     */
    public function getTab($tab)
    {
        // Maintain full view context rather than default driver/data-only context:
        return $this->renderTemplate('tab-' . $tab . '.phtml', array());
    }

    /**
     * Render a search result for the specified view mode.
     *
     * @param string $view View mode to use.
     *
     * @return string
     */
    public function getSearchResult($view)
    {
        return $this->renderTemplate('result-' . $view . '.phtml');
    }

    /**
     * Render an HTML checkbox control for the current record.
     *
     * @param string $idPrefix Prefix for checkbox HTML ids
     *
     * @return string
     */
    public function getCheckbox($idPrefix = '')
    {
        static $checkboxCount = 0;
        $id = $this->driver->getResourceSource() . '|'
            . $this->driver->getUniqueId();
        $context
            = array('id' => $id, 'count' => $checkboxCount++, 'prefix' => $idPrefix);
        return $this->contextHelper->renderInContext(
            'record/checkbox.phtml', $context
        );
    }

    /**
     * Generate a thumbnail URL (return false if unsupported).
     *
     * @param string $size Size of thumbnail (small, medium or large -- small is
     * default).
     *
     * @return string|bool
     */
    public function getThumbnail($size = 'small')
    {
        // Try to build thumbnail:
        $thumb = $this->driver->tryMethod('getThumbnail', array($size));

        // No thumbnail?  Return false:
        if (empty($thumb)) {
            return false;
        }

        // Array?  It's parameters to send to the cover generator:
        if (is_array($thumb)) {
            $urlHelper = $this->getView()->plugin('url');
            return $urlHelper('cover-show') . '?' . http_build_query($thumb);
        }

        // Default case -- return fixed string:
        return $thumb;
    }

    /**
     * Get all URLs associated with the record.  Returns an array of strings.
     *
     * @return array
     */
    public function getUrlList()
    {
        // Use a filter to pick URLs from the output of getLinkDetails():
        $filter = function ($i) {
            return $i['url'];
        };
        return array_map($filter, $this->getLinkDetails());
    }

    /**
     * Get all the links associated with this record.  Returns an array of
     * associative arrays each containing 'desc' and 'url' keys.
     *
     * @return array
     */
    public function getLinkDetails()
    {
        // See if there are any links available:
        $urls = $this->driver->tryMethod('getURLs');
        if (empty($urls)) {
            return array();
        }

        // If we found links, we may need to convert from the "route" format
        // to the "full URL" format.
        $urlHelper = $this->getView()->plugin('url');
        $serverUrlHelper = $this->getView()->plugin('serverurl');
        $formatLink = function ($link) use ($urlHelper, $serverUrlHelper) {
            // Error if route AND URL are missing at this point!
            if (!isset($link['route']) && !isset($link['url'])) {
                throw new \Exception('Invalid URL array.');
            }

            // Build URL from route/query details if missing:
            if (!isset($link['url'])) {
                $routeParams = isset($link['routeParams'])
                    ? $link['routeParams'] : array();

                $link['url'] = $serverUrlHelper(
                    $urlHelper($link['route'], $routeParams)
                );
                if (isset($link['queryString'])) {
                    $link['url'] .= $link['queryString'];
                }
            }

            // Use URL as description if missing:
            if (!isset($link['desc'])) {
                $link['desc'] = $link['url'];
            }
            return $link;
        };

        return array_map($formatLink, $urls);
    }
}