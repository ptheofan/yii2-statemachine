<?php
/**
 * User: Paris Theofanidis
 * Date: 01/07/16
 * Time: 11:02
 */
namespace ptheofan\statemachine\interfaces;

use Exception;
use ptheofan\statemachine\StateMachine;
use yii\db\BaseActiveRecord;
use yii\web\IdentityInterface;

/**
 * Interface StateMachineContext
 *
 * @package ptheofan\statemachine\interfaces
 */
interface StateMachineContext
{
    /**
     * @param StateMachineEvent $e
     * @return $this
     */
    public function setEvent(StateMachineEvent $e);

    /**
     * @return StateMachineEvent|null
     */
    public function getEvent();

    /**
     * @return string
     */
    public function getRole();

    /**
     * @param string $role
     * @return $this
     */
    public function setRole($role);

    /**
     * @return BaseActiveRecord
     */
    public function getUser();

    /**
     * @param BaseActiveRecord $user
     * @return $this
     */
    public function setUser($user);

    /**
     * @return BaseActiveRecord
     */
    public function getModel();

    /**
     * @param BaseActiveRecord $model
     * @return $this
     */
    public function setModel($model);

    /**
     * @return string
     */
    public function getModelClassName();

    /**
     * @return StateMachine
     */
    public function getSm();

    /**
     * @param StateMachine $sm
     * @return $this
     */
    public function setSm($sm);

    /**
     * @return string
     */
    public function getAttr();

    /**
     * @param string $attr
     * @return $this
     */
    public function setAttr($attr);

    /**
     * @return string
     */
    public function getVirtAttr();

    /**
     * @param string $virtAttr
     * @return $this
     */
    public function setVirtAttr($virtAttr);

    /**
     * @param Exception $e
     * @return $this
     */
    public function attachException(Exception $e);

    /**
     * @return bool
     */
    public function hasException();

    /**
     * @return \Exception[]
     */
    public function getExceptions();

    /**
     * @return Exception|null
     */
    public function getFirstException();

    /**
     * @param null $attribute
     * @return bool
     */
    public function hasErrors($attribute = null);

    /**
     * A string representing the id of the model.
     * @return string
     */
    public function getModelPk();

    /**
     * @param StateMachine $sm
     * @param string $role
     * @param IdentityInterface $user - model of user initiating the context
     * @param BaseActiveRecord $model - model that holds the attribute controlled by the state machine
     * @param string $attr
     * @param string $virtAttr
     * @return static
     */
    public static function nu($sm, $role, $user, $model, $attr, $virtAttr);
}