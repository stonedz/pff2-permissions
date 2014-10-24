<?php

namespace pff\modules;
use pff\IBeforeHook;
use pff\IConfigurableModule;
use pff\pffexception;
use zpt\anno\Annotations;
use Minime\Annotations\Reader;
use Minime\Annotations\Parser;
use Minime\Annotations\Cache\FileCache;

/**
 * Manages Controller->action permissions
 */
class PermissionChecker extends \pff\AModule implements IConfigurableModule, IBeforeHook{

    private $userClass,
        $sessionUserId,
        $getPermission,
        $controllerNotLogged,
        $actionNotLogged,
        $permissionClass;

    /**
     * @var \ReflectionClass
     */
    private $classReflection;

    /**
     * @var \pff\AController
     */
    private $controller;

    /**
     * @var Reader
     */
    private $reader;

    public function __construct($confFile = 'pff2-permissions/module.conf.local.yaml'){
        $this->loadConfig($confFile);
        $this->reader = new Reader(new Parser(), new FileCache(ROOT.DS.'app'.DS.'tmp'.DS));
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
        $this->permissionClass     = $conf['moduleConf']['permissionClass'];
    }

    /**
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws pffexception
     */
    public function doBefore() {
//        $this->controller        = $this->getController();
//        $this->classReflection   = new \ReflectionClass(get_class($this->controller));
//        $class_annotations = $this->getClassAnnotations($this->classReflection, $this->controller);
//        $annotations       = $this->getAnnotations($this->classReflection, $this->controller);

        $class_name        = get_class($this->_controller);
        $class_annotations = $this->reader->getClassAnnotations($class_name);
        $annotations       = $this->reader->getMethodAnnotations($class_name, $this->_controller->getAction());

        $method_permissions = $annotations->get('Pff2Permissions');
        $class_permissions  = $class_annotations->get('Pff2Permissions');

        //There's no permissions, let the user in
        if((!$method_permissions && !$class_permissions)) {
            return true;
        }

        if($method_permissions && !$class_permissions) {
            $annotations = $method_permissions;
        }
        else if (!$method_permissions && $class_permissions) {
            $annotations = $class_permissions;
        }
        else {
            $annotations = array_merge($method_permissions, $class_permissions);
            $annotations = array_unique($annotations);
        }

        if(isset($_SESSION['logged_data'][$this->sessionUserId])) {
            $user = $this->_controller->_em->find('\\pff\\models\\'.$this->userClass, $_SESSION['logged_data'][$this->sessionUserId]);
            $perm = call_user_func(array($user, $this->getPermission));
        }
        else {
            header("Location: ".$this->_app->getExternalPath().$this->controllerNotLogged."/".$this->actionNotLogged);
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
     * Returns the description of the Permission's class properties
     * in the format array[property_name] = [@Pff2PermissionDescription]
     *
     * @return array
     */
    public function getPrettyPermissions() {
        $permissionReflect = new \ReflectionClass('\\pff\models\\'.$this->permissionClass);
        $prop = $permissionReflect->getProperties();
        $toReturnAnnotations = array();
        foreach($prop as $a) {
            $i = $this->reader->getPropertyAnnotations('\\pff\models\\'.$this->permissionClass, $a->name);
            $arr = $i->toArray();
            if(isset($arr['Pff2PermissionDescription'])){
                $tmp_name = explode('_', $a->name);
                array_walk($tmp_name, array($this,'capitalize'));
                $toReturnAnnotations[implode($tmp_name)] = $arr['Pff2PermissionDescription'];
            }
        }
        return $toReturnAnnotations;
    }

    private function capitalize(&$arr, $k) {
        $arr = ucfirst($arr);
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
