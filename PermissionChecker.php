<?php

namespace pff\modules;
use pff\IConfigurableModule;

/**
 * Manages images
 */
class PermissionChecker extends \pff\AModule implements IConfigurableModule, IBeforeHook{

    private $_resize;

    public function __construct($confFile = 'pff2-img_manager/module.conf.local.yaml'){
        $this->loadConfig($confFile);
    }

    public function loadConfig($confFile) {
        $conf = $this->readConfig($confFile);
        $this->_resize = $conf['moduleConf']['resize'];
    }

    /**
     *
     */
    public function doBefore() {

    }
}
