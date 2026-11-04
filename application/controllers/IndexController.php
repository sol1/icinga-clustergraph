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
        $hosts = $api->sendCommand(
            'GET',
            'objects/hosts?attrs=name&attrs=display_name&attrs=state&attrs=last_check&attrs=groups&attrs=zone'
        );
        $dependencies = $api->sendCommand(
            'GET',
            'objects/dependencies?attrs=parent_host_name&attrs=child_host_name&attrs=child_service_name'
        );

        if (!is_array($hosts) || !is_array($dependencies)) {
            throw new \Exception("Failed to fetch or decode data from the Icinga2 API.");
        }

        $hostsByName = [];
        $synthetic = [];
        foreach ($hosts as $host) {
            $hostsByName[$host['name']] = $host;
            if (in_array('synthetic', $host['attrs']['groups'] ?? [], true)) {
                $synthetic[$host['name']] = true;
            }
        }

        // Host-to-host edges (parent -> child) that touch at least one synthetic
        // host. Service dependencies (child_service_name set) are ignored.
        $links = [];
        foreach ($dependencies as $dependency) {
            $attrs = $dependency['attrs'];
            if (!empty($attrs['child_service_name'])) {
                continue;
            }
            $parent = $attrs['parent_host_name'] ?? '';
            $child = $attrs['child_host_name'] ?? '';
            if ($parent === '' || $child === '' || $parent === $child) {
                continue;
            }
            if (!isset($synthetic[$parent]) && !isset($synthetic[$child])) {
                continue;
            }
            $links[$parent . "\0" . $child] = ['source' => $parent, 'target' => $child];
        }

        // Nodes: every synthetic host, plus any direct parent or child outside
        // the group so the relationship stays visible.
        $nodeNames = $synthetic;
        foreach ($links as $link) {
            $nodeNames[$link['source']] = true;
            $nodeNames[$link['target']] = true;
        }

        $nodes = [];
        foreach (array_keys($nodeNames) as $name) {
            $name = (string) $name; // PHP turns numeric string keys into ints
            $host = $hostsByName[$name] ?? null;
            $nodes[] = [
                'name'       => $name,
                'label'      => $host['attrs']['display_name'] ?? $name,
                'state'      => $host['attrs']['state'] ?? null,
                'last_check' => $host['attrs']['last_check'] ?? null,
                'zone'       => $host['attrs']['zone'] ?? null,
                'synthetic'  => isset($synthetic[$name]),
                'link'       => $ICINGAWEB_HOST_PATH . rawurlencode($name)
            ];
        }

        $this->_helper->json([
            'nodes' => $nodes,
            'links' => array_values($links)
        ]);
    }


}

