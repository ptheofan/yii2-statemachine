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
use yii\web\User;

/**
 * Interface StateMachineContext
 *
 * @package ptheofan\statemachine\interfaces
 */
interface StateMachineContext
{
    /**
     * Get a list of possible valid events (triggers) for this context
     * @return StateMachineEvent[]
     */
    public function getPossibleEvents();

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
     * @return IdentityInterface|User
     */
    public function getIdentity();

    /**
     * @return bool
     */
    public function isModelDeleted();

    /**
     * @return BaseActiveRecord
     */
    public function getModel();

    /**
     * @return string
     */
    public function getModelClassName();

    /**
     * @return StateMachine
     */
    public function getSm();

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
     * @param string $attribute
     * @return array errors - @see yii\base\Model
     */
    public function getErrors($attribute = null);

    /**
     * A string representing the id of the model.
     * @return string
     */
    public function getModelPk();

    /**
     * @param StateMachine $sm
     * @param IdentityInterface $user - model of user initiating the context
     * @param BaseActiveRecord $model - model that holds the attribute controlled by the state machine
     * @param string $attr
     * @param string $virtAttr
     * @return static
     */
    public static function nu($sm, $user, $model, $attr, $virtAttr);
}