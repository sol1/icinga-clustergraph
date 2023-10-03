<?php

namespace Icinga\Module\Clustergraph\Controllers;

use Icinga\Web\Controller;
use Icinga\Module\Clustergraph\Forms\Config\GeneralConfigForm;

class ConfigController extends Controller
{
    /**
     * General configuration for Clustergraph
     */


public function indexAction()
{
    $form = new GeneralConfigForm();
    $form->setIniConfig($this->Config());
    $form->handleRequest();

    if ($form->isSubmitted() && $form->isValid()) {
        $this->redirectNow('clustergraph/config');
    }

    $this->view->form = $form;
    $this->view->tabs = $this->Module()->getConfigTabs()->activate('config');
}


}

