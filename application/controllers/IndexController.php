<?php


namespace Icinga\Module\Clustergraph\Controllers;

error_reporting(E_ALL);
ini_set('display_errors', '1');

use Icinga\Application\Logger;
use Icinga\Web\Controller;

class IndexController extends Controller
{
    public function indexAction()
    {
        $config = \Icinga\Application\Config::module('clustergraph');

        $ICINGA_API_ENDPOINT = $config->get('api', 'endpoint');
        $ICINGA_API_USER = $config->get('api', 'user');
        $ICINGA_API_PASSWORD = $config->get('api', 'password');
        $SSL_VERIFICATION = $config->get('api', 'verify') === '1';


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
        $ICINGA_API_ENDPOINT = $config->get('api', 'endpoint');
        $ICINGA_API_USER = $config->get('api', 'user');
        $ICINGA_API_PASSWORD = $config->get('api', 'password');
        $SSL_VERIFICATION = $config->get('api', 'verify') === '1';


        if (!$ICINGA_API_ENDPOINT || !$ICINGA_API_USER || !$ICINGA_API_PASSWORD) {
            throw new \Exception("Please configure the module in module settings.");
        }


        // Fetch data from Icinga2 API using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ICINGA_API_ENDPOINT . '/objects/zones');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERPWD, $ICINGA_API_USER . ':' . $ICINGA_API_PASSWORD);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        if (!$SSL_VERIFICATION) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $output = curl_exec($ch);
        curl_close($ch);
        Logger::error(serialize($output));
        $zones = json_decode($output)->results;

        if (!is_array($zones)) {
            throw new \Exception("Failed to fetch or decode data from the Icinga2 API.");
        }

        $nodes = [];
        foreach ($zones as $zone) {
            $zoneName = $zone->name;
            $nodes[$zoneName] = [
                'name' => $zoneName,
                'children' => [],
                'endpoints' => $zone->attrs->endpoints ?? []
            ];
        }

        foreach ($zones as $zone) {
            $zoneName = $zone->name;
            $parentName = $zone->attrs->parent;
            if ($parentName && isset($nodes[$parentName])) {
                $nodes[$parentName]['children'][] = &$nodes[$zoneName];
            }
        }

        // Find root node
        $rootZoneName = '';
        foreach ($zones as $zone) {
            if (!$zone->attrs->parent) {
                $rootZoneName = $zone->name;
                break;
            }
        }

        $tree = $nodes[$rootZoneName];

        $this->_helper->json($tree);
    }


}

