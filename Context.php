<?php
/**
 * User: Paris Theofanidis
 * Date: 05/06/16
 * Time: 13:54
 */
namespace ptheofan\statemachine;

use Exception;
use ptheofan\statemachine\interfaces\StateMachineContext;
use ptheofan\statemachine\interfaces\StateMachineEvent;
use yii\base\Model;
use yii\db\BaseActiveRecord;
use yii\helpers\Json;
use yii\web\IdentityInterface;

/**
 * Class Context
 *
 * @package common\sm
 */
class Context extends Model implements StateMachineContext
{
    /**
     * @var IdentityInterface - user that initiated the event
     */
    private $identity;

    /**
     * @var BaseActiveRecord
     */
    private $model;

    /**
     * @var Event[]
     */
    private $events = [];

    /**
     * @var StateMachine
     */
    private $sm;

    /**
     * @var string
     */
    private $attr;

    /**
     * @var string
     */
    private $virtAttr;

    /**
     * @var Exception[]
     */
    private $exceptions = [];

    /**
     * @return interfaces\StateMachineEvent[]
     * @throws exceptions\InvalidSchemaException
     * @throws exceptions\StateMachineNotFoundException
     * @throws exceptions\StateNotFoundException
     */
    public function getPossibleEvents()
    {
        return $this->sm->getState($this->model->{$this->attr})->getEvents($this);
    }

    /**
     * @param StateMachineEvent $e
     * @return $this
     */
    public function setEvent(StateMachineEvent $e)
    {
        if ($this->getEvent() !== $e) {
            $this->events[] = $e;
        }

        return $this;
    }

    /**
     * @return StateMachineEvent|null
     */
    public function getEvent()
    {
        $count = count($this->events);
        if ($count === 0) {
            return null;
        } else {
            return $this->events[$count - 1];
        }
    }

    /**
     * @return IdentityInterface|null
     */
    public function getIdentity()
    {
        return $this->identity;
    }

    /**
     * @return bool
     */
    public function isModelDeleted()
    {
        return $this->model->{$this->virtAttr}->isModelDeleted();
    }

    /**
     * @return BaseActiveRecord
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @return string
     */
    public function getModelClassName()
    {
        $m = $this->model;
        return $m::className();
    }

    /**
     * @return StateMachine
     */
    public function getSm()
    {
        return $this->sm;
    }

    /**
     * @return string
     */
    public function getAttr()
    {
        return $this->attr;
    }

    /**
     * @param string $attr
     * @return $this
     */
    public function setAttr($attr)
    {
        $this->attr = $attr;
        return $this;
    }

    /**
     * @return string
     */
    public function getVirtAttr()
    {
        return $this->virtAttr;
    }

    /**
     * @param string $virtAttr
     * @return $this
     */
    public function setVirtAttr($virtAttr)
    {
        $this->virtAttr = $virtAttr;
        return $this;
    }

    /**
     * @param \Exception $e
     * @return $this
     */
    public function attachException(\Exception $e)
    {
        $this->exceptions[] = $e;
        $this->addError('exceptions', $e->getMessage());
        return $this;
    }

    /**
     * @return bool
     */
    public function hasException()
    {
        return !empty($this->exceptions);
    }

    /**
     * @return \Exception[]
     */
    public function getExceptions()
    {
        return $this->exceptions;
    }

    /**
     * @return Exception|null
     */
    public function getFirstException()
    {
        return isset($this->exceptions[0]) ? $this->exceptions[0] : null;
    }

    /**
     * @param null $attribute
     * @return bool
     */
    public function hasErrors($attribute = null)
    {
        if (!empty($this->exceptions)) {
            return true;
        } else {
            return parent::hasErrors($attribute);
        }
    }

    /**
     * A string representing the id of the model.
     * @return string
     */
    public function getModelPk()
    {
        $pk = $this->getModel()->getPrimaryKey(true);
        foreach ($pk as $k => &$v) {
            if (is_object($v)) {
                $v = (string)$v;
            }
        }
        return Json::encode($pk);
    }

    /**
     * @param StateMachine $sm
     * @param IdentityInterface $identity - model of user initiating the context
     * @param BaseActiveRecord $model - model that holds the attribute controlled by the state machine
     * @param string $attr
     * @param string $virtAttr
     * @return static
     */
    public static function nu($sm, $identity, $model, $attr, $virtAttr)
    {
        $rVal = new static([
            'attr' => $attr,
            'virtAttr' => $virtAttr,
        ]);

        $rVal->sm = $sm;
        $rVal->identity = $identity;
        $rVal->model = $model;

        return $rVal;
    }
}