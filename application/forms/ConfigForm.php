<?php
namespace Icinga\Module\Clustergraph\Forms;

use Icinga\Application\Config;
use Icinga\Forms\ConfigForm;

class ConfigForm extends ConfigForm
{
    public function init()
    {
        $this->setName('form_config');
        $this->setSubmitLabel($this->translate('Save Changes'));
    }

    public function createElements(array $formData)
    {
        $this->addElement('text', 'api_user', array(
            'label'       => $this->translate('API User'),
            'required'    => true,
            'description' => $this->translate('User for accessing the Icinga2 API.')
        ));

        $this->addElement('password', 'api_password', array(
            'label'       => $this->translate('API Password'),
            'required'    => true,
            'description' => $this->translate('Password for accessing the Icinga2 API.')
        ));

        $this->addElement('text', 'api_endpoint', array(
            'label'       => $this->translate('API Endpoint'),
            'required'    => true,
            'description' => $this->translate('Endpoint URL for the Icinga2 API. e.g., https://your-icinga-server:5665/v1')
        ));

        $this->addElement('checkbox', 'enable_ssl_verification', array(
            'label'       => $this->translate('Enable SSL Verification'),
            'description' => $this->translate('Whether to verify the SSL certificate of the Icinga2 API.'),
            'value'       => true
        ));
    }

    public function onRequest()
    {
        $this->populate($this->config->toArray());
    }

    public function onSuccess()
    {
        $configArray = array(
            'api_user' => $this->getValue('api_user'),
            'api_password' => $this->getValue('api_password'),
            'api_endpoint' => $this->getValue('api_endpoint'),
            'enable_ssl_verification' => $this->getValue('enable_ssl_verification')
        );

        $this->config->setConfig(new Config($configArray));

        if ($this->config->saveIni()) {
            $this->info($this->translate('Configuration successfully saved.'));
        } else {
            $this->error($this->translate('Failed to save configuration.'));
        }

        return true;
    }
}

