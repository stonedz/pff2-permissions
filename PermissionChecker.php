<?php

namespace pff\modules;
use pff\IBeforeHook;
use pff\IConfigurableModule;
use pff\pffexception;
use zpt\anno\Annotations;

/**
 * Manages Controller->action permissions
 */
class PermissionChecker extends \pff\AModule implements IConfigurableModule, IBeforeHook{

    private $userClass, $sessionUserId, $getPermission;

    public function __construct($confFile = 'pff2-permissions/module.conf.local.yaml'){
        $this->loadConfig($confFile);
    }

    /**
     * {@inheritdoc}
     */
    public function loadConfig($confFile) {
        $conf = $this->readConfig($confFile);
        $this->userClass = $conf['moduleConf']['userClass'];
        $this->sessionUserId = $conf['moduleConf']['sessionUserId'];
        $this->getPermission = $conf['moduleConf']['getPermission'];
    }

    public function doBefore() {
        $controller  = $this->getController();
        $annotations = $this->getAnnotations($controller);
        if(!isset($annotations['PffPermissions']) || count($annotations)<1) {
            return true;
        }
        $annotations = $annotations['PffPermissions'];

        if(isset($_SESSION['logged_data'][$this->sessionUserId])) {
            $user = $this->_controller->_em->find('\\pff\\models\\'.$this->userClass, $_SESSION['logged_data'][$this->sessionUserId]);
            $perm = call_user_func(array($user, $this->getPermission));
        }
        else {
            throw new PffException('No user in session', 500);
        }

        foreach($annotations as $a) {
            if(!call_user_func(array($perm, 'get'.$a))) {
                throw new PffException('Action not permitted', 500);
            }
        }
        return true;
    }

    /**
     * Get annotations for the currently active action
     *
     * @param \pff\AController $controller
     * @return \zpt\anno\Annotations
     */
    private function getAnnotations(\pff\AController $controller) {
        $classRefletion = new \ReflectionClass(get_class($controller));
        $actions = $classRefletion->getMethod($controller->getAction());
        return new Annotations($actions);
    }
}
