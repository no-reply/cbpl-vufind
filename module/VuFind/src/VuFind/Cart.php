<?php
/**
 * Cart Class
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
 * @package  Cart
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
namespace VuFind;
use VuFind\Config\Reader as ConfigReader,
    Zend\ServiceManager\ServiceLocatorAwareInterface,
    Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Cart Class
 *
 * The data model object representing a user's book cart.
 *
 * @category VuFind2
 * @package  Cart
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
class Cart implements ServiceLocatorAwareInterface
{
    /**
     * Cart contents.
     *
     * @var array
     */
    protected $items;

    /**
     * Maximum number of items allowed in cart.
     *
     * @var int
     */
    protected $maxSize = 100;

    /**
     * Is the cart currently activated?
     *
     * @var bool
     */
    protected $active = false;

    /**
     * Service locator
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    const CART_COOKIE =  'vufind_cart';
    const CART_COOKIE_SOURCES = 'vufind_cart_src';
    const CART_COOKIE_DELIM = "\t";

    /**
     * Constructor
     */
    public function __construct()
    {
        $config = ConfigReader::getConfig();
        if (isset($config->Site->showBookBag)) {
            $this->active = (bool)$config->Site->showBookBag;
        }
        if (isset($config->Site->bookBagMaxSize)) {
            $this->maxSize = $config->Site->bookBagMaxSize;
        }
        $this->items = array();

        // Initialize contents
        $this->init();
    }

    /**
     * Return the contents of the cart.
     *
     * @return array     array of items in the cart
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Does the cart contain the specified item?
     *
     * @param string $item ID of item to check
     *
     * @return bool
     */
    public function contains($item)
    {
        return in_array($item, $this->items);
    }

    /**
     * Empty the cart.
     *
     * @return void
     */
    public function emptyCart()
    {
        $this->items = array();
        $this->save();
    }

    /**
     * Add an item to the cart.
     *
     * @param string $item ID of item to remove
     *
     * @return array       Associative array with two keys: success (bool) and
     * notAdded (array of IDs that were unable to be added to the cart)
     */
    public function addItem($item)
    {
        return $this->addItems(array($item));
    }

    /**
     * Add an array of items to the cart.
     *
     * @param array $items IDs of items to add
     *
     * @return array       Associative array with two keys: success (bool) and
     * notAdded (array of IDs that were unable to be added to the cart)
     */
    public function addItems($items)
    {
        $items = array_merge($this->items, $items);

        $total = count($items);
        $this->items = array_slice(array_unique($items), 0, $this->maxSize);
        $this->save();
        if ($total > $this->maxSize) {
            $notAdded = $total-$this->maxSize;
            return array('success' => false, 'notAdded' => $notAdded);
        }
        return array('success' => true);
    }

    /**
     * Remove an item from the cart.
     *
     * @param array $items An array of item IDS
     *
     * @return void
     */
    public function removeItems($items)
    {
        $results = array();
        foreach ($this->items as $id) {
            if (!in_array($id, $items)) {
                $results[] = $id;
            }
        }
        $this->items = $results;
        $this->save();
    }

    /**
     * Get cart size.
     *
     * @return string The maximum cart size
     */
    public function getMaxSize()
    {
        return $this->maxSize;
    }

    /**
     * Check whether the cart is full.
     *
     * @return bool      true if full, false otherwise
     */
    public function isFull()
    {
        return (count($this->items) >= $this->maxSize);
    }

    /**
     * Check whether the cart is empty.
     *
     * @return bool      true if empty, false otherwise
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    /**
     * Check whether cart is enabled.
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * Initialize the cart model.
     *
     * @return array   contents of the cart
     */
    protected function init()
    {
        $items = null;
        if (isset($_COOKIE[self::CART_COOKIE])) {
            $cookie = $_COOKIE[self::CART_COOKIE];
            $items = explode(self::CART_COOKIE_DELIM, $cookie);

            if (!isset($_COOKIE[self::CART_COOKIE_SOURCES])) {
                // Backward compatibility with VuFind 1.x -- if no source cookie, all
                // items come from the VuFind source:
                for ($i = 0; $i < count($items); $i++) {
                    $items[$i] = 'VuFind|' . $items[$i];
                }
            } else {
                // Default case for VuFind 2.x carts -- decompress source data:
                $sources = explode(
                    self::CART_COOKIE_DELIM, $_COOKIE[self::CART_COOKIE_SOURCES]
                );
                for ($i = 0; $i < count($items); $i++) {
                    $sourceIndex = ord(substr($items[$i], 0, 1)) - 65;
                    $items[$i]
                        = $sources[$sourceIndex] . '|' . substr($items[$i], 1);
                }
            }
        }
        $this->items = $items ? $items : array();
    }

    /**
     * Save the state of the cart. This implementation uses cookie
     * so the cart contents can be manipulated on the client side as well.
     *
     * @return void
     */
    protected function save()
    {
        $sources = array();
        $ids = array();

        foreach ($this->items as $item) {
            // Break apart the source and the ID:
            list($source, $id) = explode('|', $item, 2);

            // Add the source to the source array if it is not already there:
            $sourceIndex = array_search($source, $sources);
            if ($sourceIndex === false) {
                $sourceIndex = count($sources);
                $sources[$sourceIndex] = $source;
            }

            // Encode the source into the ID as a single character:
            $ids[] = chr(65 + $sourceIndex) . $id;
        }

        // Save the cookies:
        $cookie = implode(self::CART_COOKIE_DELIM, $ids);
        setcookie(self::CART_COOKIE, $cookie, 0, '/');
        $cookie = implode(self::CART_COOKIE_DELIM, $sources);
        setcookie(self::CART_COOKIE_SOURCES, $cookie, 0, '/');
    }

    /**
     * Process parameters and return the cart content.
     *
     * @return array $records The cart content
     */
    public function getRecordDetails()
    {
        return $this->getServiceLocator()
            ->get('RecordLoader')->loadBatch($this->items);
    }

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator Locator to register
     *
     * @return Manager
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    /**
     * Get the service locator.
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }
}
