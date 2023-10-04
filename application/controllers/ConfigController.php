<?php

namespace Icinga\Module\Clustergraph\Controllers;

use Icinga\Application\Config;
use Icinga\Module\Clustergraph\Forms\ModuleconfigForm;
use Icinga\Web\Controller;

class ConfigController extends Controller
{
    /**
     * General configuration for Clustergraph
     */


    public function indexAction()
    {
        $form = (new ModuleconfigForm())
            ->setIniConfig(Config::module('clustergraph', "config"));

        $form->handleRequest();

        $this->view->tabs = $this->Module()->getConfigTabs()->activate('config');

        $this->view->form = $form;
    }


}

