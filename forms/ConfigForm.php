<?php

namespace Icinga\Module\Clustergraph\Forms;

use Icinga\Forms\ConfigForm;

class ConfigForm extends ConfigForm
{
    public function init()
    {
        $this->setName('form_config');
        $this->setSubmitLabel($this->translate('Save'));

        $this->addElement('text', 'api_user', [
            'label'       => 'API User',
            'description' => 'Add a user in api-users.conf with these permissions:  permissions = [ "objects/query/Zone", "objects/query/Endpoint" ]',
            'required'    => true,
        ]);

        $this->addElement('password', 'api_password', [
            'label'       => 'API Password',
            'required'    => true,
        ]);

        $this->addElement('text', 'api_endpoint', [
            'label'       => 'API Endpoint',
            'description' => 'Example: https://myicingamaster.mydomain:5665/v1',
            'required'    => true,
        ]);

        $this->addElement('checkbox', 'ssl_verification', [
            'label'       => 'Enable SSL Verification',
            'description' => 'If checked, SSL verification will be enabled.',
            'value'       => 1,
        ]);

    }

    public function onSuccess()
    {
        $this->setConfig('clustergraph', $this->getValues());
        parent::onSuccess();
    }
}

