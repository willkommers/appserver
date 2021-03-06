<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @category   Server
 * @package    Appserver
 * @subpackage Core
 * @author     Bernhard Wick <bw@appserver.io>
 * @copyright  2014 TechDivision GmbH - <info@appserver.io>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       http://www.appserver.io/
 */

namespace AppserverIo\Appserver\Core\Api\Node;

/**
 * AppserverIo\Appserver\Core\Api\Node\AnalyticsNodeTrait
 *
 * Trait which allows for the management of analytic nodes within another node
 *
 * @category   Server
 * @package    Appserver
 * @subpackage Core
 * @author     Bernhard Wick <bw@appserver.io>
 * @copyright  2014 TechDivision GmbH - <info@appserver.io>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       http://www.appserver.io/
 */
trait AnalyticsNodeTrait
{
    /**
     * The analytics specified within the parent node
     *
     * @var array
     * @AS\Mapping(nodeName="analytics/analytic", nodeType="array", elementType="AppserverIo\Appserver\Core\Api\Node\AnalyticNode")
     */
    protected $analytics = array();

    /**
     * Will return the analytics array
     *
     * @return array The array with the analytic nodes
     */
    public function getAnalytics()
    {
        return $this->analytics;
    }

    /**
     * Will return the analytic node with the specified definition and if nothing could
     * be found we will return false
     *
     * @param string $uri The URI of the analytic in question
     *
     * @return \AppserverIo\Appserver\Core\Api\Node\AnalyticNode|boolean The requested analytics node
     */
    public function getAnalytic($uri)
    {
        // Iterate over all analytics
        foreach ($this->getAnalytics() as $analyticNode) {

            // If we found one with a matching URI we will return it
            if ($analyticNode->getUri() === $uri) {

                return $analyticNode;
            }
        }

        // Still here? Seems we did not find anything
        return false;
    }

    /**
     * Returns the analytics as an associative array
     *
     * @return array The array with the sorted analytics
     */
    public function getAnalyticsAsArray()
    {
        // Iterate over the analytics nodes and sort them into an array
        $analytics = array();
        foreach ($this->getAnalytics() as $analyticNode) {

            // Restructure to an array
            $analytics[] = array(
                'uri' => $analyticNode->getUri(),
                'connectors' => $analyticNode->getConnectorsAsArray()
            );
        }

        // Return what we got
        return $analytics;
    }
}
