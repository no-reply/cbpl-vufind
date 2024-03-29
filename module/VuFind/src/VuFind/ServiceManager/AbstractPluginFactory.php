<?php
/**
 * VuFind Abstract Plugin Factory
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
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\ServiceManager;
use Zend\ServiceManager\AbstractFactoryInterface,
    Zend\ServiceManager\ServiceLocatorInterface;

/**
 * VuFind Abstract Plugin Factory
 *
 * @category VuFind2
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_search_object Wiki
 */
abstract class AbstractPluginFactory implements AbstractFactoryInterface
{
    protected $defaultNamespace;

    /**
     * Get the name of a class for a given plugin name.
     *
     * @param string $name          Name of service
     * @param string $requestedName Unfiltered name of service
     *
     * @return string               Fully qualified class name
     */
    protected function getClassName($name, $requestedName)
    {
        // If we have a FQCN, return it as-is; otherwise, prepend the default prefix:
        if (strpos($name, '\\') === false) {
            // First try the raw service name, then try a normalized version:
            $name = $this->defaultNamespace . '\\' . $requestedName;
            if (!class_exists($name)) {
                $name = $this->defaultNamespace . '\\' . ucwords(strtolower($name));
            }
        }
        return $name;
    }

    /**
     * Can we create a service for the specified name?
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param string                  $name           Name of service
     * @param string                  $requestedName  Unfiltered name of service
     *
     * @return bool
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $serviceLocator,
        $name, $requestedName
    ) {
        $className = $this->getClassName($name, $requestedName);
        return class_exists($className);
    }

    /**
     * Create a service for the specified name.
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param string                  $name           Name of service
     * @param string                  $requestedName  Unfiltered name of service
     *
     * @return object
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator,
        $name, $requestedName
    ) {
        $class = $this->getClassName($name, $requestedName);
        return new $class();
    }
}
