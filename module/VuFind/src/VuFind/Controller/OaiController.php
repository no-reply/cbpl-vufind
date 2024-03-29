<?php
/**
 * OAI Module Controller
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */
namespace VuFind\Controller;
use VuFind\Config\Reader as ConfigReader;

/**
 * OAIController Class
 *
 * Controls the OAI server
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */
class OaiController extends AbstractBase
{
    /**
     * Display OAI server form.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        // no action needed
        return $this->createViewModel();
    }

    /**
     * Standard OAI server.
     *
     * @return \Zend\Http\Response
     */
    public function authserverAction()
    {
        return $this->handleOAI('VuFind\OAI\Server\Auth');
    }

    /**
     * Standard OAI server.
     *
     * @return \Zend\Http\Response
     */
    public function serverAction()
    {
        return $this->handleOAI('VuFind\OAI\Server');
    }

    /**
     * Shared OAI logic.
     *
     * @param string $serverClass Class to load for handling OAI requests.
     *
     * @return \Zend\Http\Response
     */
    protected function handleOAI($serverClass)
    {
        // Check if the OAI Server is enabled before continuing
        $config = ConfigReader::getConfig();
        $response = $this->getResponse();
        if (!isset($config->OAI)) {
            $response->setStatusCode(404);
            $response->setContent('OAI Server Not Configured.');
            return $response;
        }

        // Collect relevant parameters for OAI server:
        $url = explode('?', $this->getServerUrl());
        $baseURL = $url[0];

        // Build OAI response or die trying:
        try {
            $server = new $serverClass(
                $this->getSearchManager(),
                $baseURL, $this->getRequest()->getQuery()->toArray()
            );
            $xml = $server->getResponse();
        } catch (\Exception $e) {
            $response->setStatusCode(500);
            $response->setContent($e->getMessage());
            return $response;
        }

        // Return response:
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'text/xml');
        $response->setContent($xml);
        return $response;
    }
}