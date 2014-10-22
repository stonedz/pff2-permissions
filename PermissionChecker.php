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

    private $userClass, $sessionUserId, $getPermission, $controllerNotLogged, $actionNotLogged;

    public function __construct($confFile = 'pff2-permissions/module.conf.local.yaml'){
        $this->loadConfig($confFile);
    }

    /**
     * {@inheritdoc}
     */
    public function loadConfig($confFile) {
        $conf = $this->readConfig($confFile);

        $this->userClass           = $conf['moduleConf']['userClass'];
        $this->sessionUserId       = $conf['moduleConf']['sessionUserId'];
        $this->getPermission       = $conf['moduleConf']['getPermission'];
        $this->controllerNotLogged = $conf['moduleConf']['controllerNotLogged'];
        $this->actionNotLogged     = $conf['moduleConf']['actionNotLogged'];
    }

    /**
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws pffexception
     */
    public function doBefore() {
        $controller        = $this->getController();
        $classReflection    = new \ReflectionClass(get_class($controller));
        $class_annotations = $this->getClassAnnotations($classReflection, $controller);
        $annotations       = $this->getAnnotations($classReflection, $controller);

        if((!isset($annotations['Pff2Permissions']) && !isset($class_annotations['Pff2Permissions'])) || count($annotations)<1) {
            return true;
        }

        if(isset($annotations['Pff2Permissions']) && !isset($class_annotations['Pff2Permissions'])) {
            $annotations = $annotations['Pff2Permissions'];
        }
        else if (!isset($annotations['Pff2Permissions']) && isset($class_annotations['Pff2Permissions'])) {
            $annotations = $class_annotations['Pff2Permissions'];
        }
        else {
            $annotations = array_merge($annotations['Pff2Permissions'], $class_annotations['Pff2Permissions']);
            $annotations = array_unique($annotations);
        }

        if(isset($_SESSION['logged_data'][$this->sessionUserId])) {
            $user = $this->_controller->_em->find('\\pff\\models\\'.$this->userClass, $_SESSION['logged_data'][$this->sessionUserId]);
            $perm = call_user_func(array($user, $this->getPermission));
        }
        else {
            //$this->_controller->resetViews();
            header("Location : ".$this->_app->getExternalPath().$this->controllerNotLogged."/".$this->actionNotLogged);
            //header("Location : http://www.google.it");
            exit();
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
    private function getAnnotations(\ReflectionClass $classReflection, \pff\AController $controller) {
        $actions = $classReflection->getMethod($controller->getAction());
        return new Annotations($actions);
    }

    /**
     * @param \ReflectionClass $classReflection
     * @param \pff\AController $controller
     * @return \zpt\anno\Annotations
     */
    private function getClassAnnotations(\ReflectionClass $classReflection, \pff\AController $controller) {
        return new Annotations($classReflection);
    }
}
