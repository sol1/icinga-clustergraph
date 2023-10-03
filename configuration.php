
<?php

$this->provideConfigTab('config', array(
    'title' => $this->translate('Configure this module'),
    'label' => $this->translate('Config'),
    'url' => 'config'
));

$this->menuSection('ClusterGraph', array(
    'url' => 'clustergraph',
    'icon' => 'clock'
));


