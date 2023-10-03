<?php

namespace Icinga\Module\Clustergraph\Forms\Config;

use Icinga\Forms\ConfigForm;
use Icinga\Web\Notification;
use Icinga\Application\Logger;


class GeneralConfigForm extends ConfigForm
{
    /**
     * Initialize this form
     */
    public function init()
    {
        $this->setName('form_config_clustergraph');
        $this->setSubmitLabel($this->translate('Save Changes'));
    }

    /**
     * {@inheritdoc}
     */
    public function createElements(array $formData)
    {
        $this->addElement(
            'text',
            'icinga_api_user',
            array(
                'required'      => true,
                'label'         => $this->translate('API User'),
                'description'   => $this->translate('API username for Icinga 2.'),
                'autocomplete'  => 'off'
            )
        );

        $this->addElement(
            'password',
            'icinga_api_pass',
            array(
                'required'      => true,
                'label'         => $this->translate('API Password'),
                'description'   => $this->translate('API password for Icinga 2.'),
                'renderPassword'=> false,
                'autocomplete'  => 'off'
            )
        );

        $this->addElement(
            'text',
            'api_endpoint',
            array(
                'required'      => true,
                'label'         => $this->translate('API Endpoint'),
                'description'   => $this->translate('API endpoint for Icinga 2, e.g. https://your-icinga2-server:5665/v1'),
                'autocomplete'  => 'off'
            )
        );

        $this->addElement(
            'checkbox',
            'ssl_verification',
            array(
                'label'         => $this->translate('Enable SSL Verification'),
                'description'   => $this->translate('Check to enable SSL verification.')
            )
        );
    }

public function onSetup()
{
    // Set config section to be written to the INI file
    $this->setConfig($this->Config('clustergraph'));

    // Get the 'api' section from the config
    $apiConfig = $this->config->get('api', []);

    // Convert to actual array
    $apiConfigArray = [];
    foreach ($apiConfig as $key => $value) {
        $apiConfigArray[$key] = $value;
    }

    // Log the array for debugging
    Logger::debug("API Config Array: " . print_r($apiConfigArray, true));
error_log("API Config Array: " . print_r($apiConfigArray, true));


    // Set this as the default data for the form
    $this->setDefaults($apiConfigArray);
}


    public function onSuccess()
    {
        $this->config->setSection('api', $this->getValues());
        $this->config->saveIni();
        
        // Provide feedback to the user
        Notification::success($this->translate('Configuration saved successfully!'));
    }
}

