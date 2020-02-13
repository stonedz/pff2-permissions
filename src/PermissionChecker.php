<?php

namespace pff\modules;
use Doctrine\Common\Annotations\AnnotationReader;
use Minime\Annotations\Cache\ApcCache;
use pff\Abs\AController;
use pff\Abs\AModule;
use pff\Core\ModuleManager;
use pff\Iface\IBeforeHook;
use pff\Iface\IConfigurableModule;
use pff\Exception\PffException;
use Minime\Annotations\Reader;
use Minime\Annotations\Parser;

/**
 * Manages Controller->action permissions
 */
class PermissionChecker extends AModule implements IConfigurableModule, IBeforeHook{

    private $userClass,
        $sessionUserId,
        $getPermission,
        $controllerNotLogged,
        $actionNotLogged,
        $permissionClass,
        $dbType;

    /**
     * @var \ReflectionClass
     */
    private $classReflection;

    /**
     * @var AController
     */
    private $controller;

    /**
     * @var Reader
     */
    private $reader;

    public function __construct($confFile = 'pff2-permissions/module.conf.local.yaml'){
        $this->loadConfig($confFile);
        $this->reader = new Reader(new Parser(), new ApcCache());
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
        $this->dbType              = $conf['moduleConf']['dbType'];
    }

    /**
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws PffException
     */
    public function doBefore() {
	$logical_operator = 'and';
        $annotationReader = ModuleManager::loadModule('pff2-annotations');

        $class_permissions  = $annotationReader->getClassAnnotation('Pff2Permissions');
        $method_permissions = $annotationReader->getMethodAnnotation('Pff2Permissions');
	$logical_operator_tmp = $annotationReader->getMethodAnnotation('Pff2PermissionsLogicalOperator');
	
	if($logical_operator_tmp == 'and' || $logical_operator_tmp == 'AND'){
	    $logical_operator = 'and';
	}
	elseif($logical_operator_tmp == 'or' || $logical_operator_tmp == 'OR'){
	    $logical_operator = 'or';
	}

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
            if($this->dbType == 'odm') {
                $user = $this->_controller->_dm->find('\\pff\\models\\'.$this->userClass, $_SESSION['logged_data'][$this->sessionUserId]);
            }else {
                $user = $this->_controller->_em->find('\\pff\\models\\'.$this->userClass, $_SESSION['logged_data'][$this->sessionUserId]);
            }
            $perm = call_user_func(array($user, $this->getPermission));
            if(!$perm) {
                throw new PffException('Action not permitted', 403);
            }
        }
        else {
            header("Location: ".$this->_app->getExternalPath().$this->controllerNotLogged."/".$this->actionNotLogged);
            exit();
        }

	
	if($logical_operator == 'and'){
            foreach($annotations as $a) {
                if(!call_user_func(array($perm, 'get'.$a))) {
                    throw new PffException('Action not permitted', 403);
                }
            }
	}
	elseif($logical_operator == 'or'){
            foreach($annotations as $a) {
                if(call_user_func(array($perm, 'get'.$a))) {
                    return true;
                }
            }
            throw new PffException('Action not permitted', 403);
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
                array_walk($tmp_name, function(&$arr,$k){$arr = ucfirst($arr);});
                $toReturnAnnotations[implode($tmp_name)] = $arr['Pff2PermissionDescription'];
            }
        }
        return $toReturnAnnotations;
    }
}
