<?php


namespace Icinga\Module\Clustergraph\Controllers;

error_reporting(E_ALL);
ini_set('display_errors', '1');

use Icinga\Application\Icinga;
use Icinga\Application\Logger;
use Icinga\Module\Clustergraph\Common\IcingaApi;
use Icinga\Module\Clustergraph\Common\IcingaDbZones;
use Icinga\Module\Clustergraph\Common\IdoZones;
use Icinga\Web\Controller;

class IndexController extends Controller
{
    public function indexAction()
    {
        $config = \Icinga\Application\Config::module('clustergraph');

        $ICINGA_API_ENDPOINT = $config->get('api', 'host');
        $ICINGA_API_USER = $config->get('api', 'username');
        $ICINGA_API_PASSWORD = $config->get('api', 'password');


        if (!$ICINGA_API_ENDPOINT || !$ICINGA_API_USER || !$ICINGA_API_PASSWORD) {
            throw new \Exception("Please configure the module in module settings.");
        }

        $this->view->tabs = $this->getTabs();

    }

    public function testAction()
    {
        echo "Test Successful";
        exit;
    }


    public function dataAction()
    {
        // Configuration

        $config = \Icinga\Application\Config::module('clustergraph');
        $ICINGA_API_ENDPOINT = $config->get('api', 'host');
        $ICINGA_API_USER = $config->get('api', 'username');
        $ICINGA_API_PASSWORD = $config->get('api', 'password');
        $ICINGAWEB_TYPE = $config->get('graph', 'ui');
        
        $ICINGAWEB_BASEURL = Icinga::app()->getRequest()->getBaseUrl() . '/';
        if ($ICINGAWEB_TYPE == 'icingaweb2') {
            $ICINGAWEB_HOST_PATH = $ICINGAWEB_BASEURL . 'monitoring/host/show?host=';
        } elseif ($ICINGAWEB_TYPE == 'icingadb') {
            $ICINGAWEB_HOST_PATH = $ICINGAWEB_BASEURL . 'icingadb/host?name=';  
        }
        

        if (!$ICINGA_API_ENDPOINT || !$ICINGA_API_USER || !$ICINGA_API_PASSWORD) {
            throw new \Exception("Please configure the module in module settings.");
        }


        $api = new IcingaApi();
        $zones = $api->sendCommand('GET', 'objects/zones');
        // Only consider hosts that are members of the "synthetic" host group
        $hostFilter = rawurlencode('"synthetic" in host.groups');
        $hosts = $api->sendCommand(
            'GET',
            'objects/hosts?attrs=name&attrs=display_name&attrs=state&filter=' . $hostFilter
        );
        if (!is_array($hosts)) {
            $hosts = [];
        }

        if (!is_array($zones)) {
            throw new \Exception("Failed to fetch or decode data from the Icinga2 API.");
        }

        $nodes = [];
        foreach ($zones as $zone) {
            $zoneName = $zone['name'];
            $endpoints = [];
            foreach ($zone['attrs']['endpoints'] ?? [] as $endpoint) {
                $endpointData = null;
                foreach ($hosts as $host) {
                    if (($endpoint == $host['name']) || ($endpoint == $host['attrs']['display_name'])) {
                        $endpointData = [
                            'name'  => $endpoint,
                            'state' => $host['attrs']['state'],
                            'link'  => $ICINGAWEB_HOST_PATH . rawurlencode($host['name'])
                        ];
                        break;
                    }
                }
                // Skip endpoints that are not hosts in the "synthetic" host group
                if ($endpointData !== null) {
                    $endpoints[] = $endpointData;
                }
            }
            $nodes[$zoneName] = [
                'name' => $zoneName,
                'children' => [],
                'endpoints' => $endpoints
            ];
        }


        foreach ($zones as $zone) {
            $zoneName = $zone['name'];
            $parentName = $zone['attrs']['parent'];
            if ($parentName && isset($nodes[$parentName])) {
                $nodes[$parentName]['children'][] = &$nodes[$zoneName];
            }
        }

        // Find root node
        $rootZoneName = '';
        foreach ($zones as $zone) {
            if (!$zone['attrs']['parent'] && !$zone['attrs']['global']) {
                $rootZoneName = $zone['name'];
                break;
            }
        }

        $tree = $nodes[$rootZoneName];

        // Drop zones that contain no synthetic hosts, either directly or
        // in any descendant zone. The root zone is always kept.
        $tree['children'] = $this->pruneZones($tree['children']);

        $this->_helper->json($tree);
    }

    /**
     * Recursively remove zones that have no endpoints and no remaining children
     *
     * @param array $zones
     *
     * @return array
     */
    protected function pruneZones(array $zones)
    {
        $kept = [];
        foreach ($zones as $zone) {
            $zone['children'] = $this->pruneZones($zone['children']);
            if (!empty($zone['endpoints']) || !empty($zone['children'])) {
                $kept[] = $zone;
            }
        }

        return $kept;
    }


}

