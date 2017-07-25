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
use yii\web\User;

/**
 * Class Context
 *
 * @package common\sm
 */
class Context extends Model implements StateMachineContext
{
    /**
     * @var string - role of event initiator
     */
    private $role;

    /**
     * @var BaseActiveRecord - user that initiated the event
     */
    private $user;

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
     */
    public function getPossibleEvents()
    {
        return $this->sm->getState($this->model->{$this->attr})->getEvents($this->role, $this);
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
     * @return string
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * @param string $role
     * @return $this
     */
    public function setRole($role)
    {
        $this->role = $role;
        return $this;
    }

    /**
     * @return BaseActiveRecord|User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param BaseActiveRecord $user
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return BaseActiveRecord
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @param BaseActiveRecord $model
     * @return $this
     */
    public function setModel($model)
    {
        $this->model = $model;
        return $this;
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
     * @param StateMachine $sm
     * @return $this
     */
    public function setSm($sm)
    {
        $this->sm = $sm;
        return $this;
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
     * @return bool
     */
    public function isModelDeleted()
    {
        $model = $this->getModel();
        $virtAtr = $this->getVirtAttr();
        /** @var StateMachineBehavior $smBehavior */
        $smBehavior = $model->{$virtAtr};

        return $smBehavior->isModelDeleted();
    }

    /**
     * @param StateMachine $sm
     * @param string $role
     * @param IdentityInterface $user - model of user initiating the context
     * @param BaseActiveRecord $model - model that holds the attribute controlled by the state machine
     * @param string $attr
     * @param string $virtAttr
     * @return static
     */
    public static function nu($sm, $role, $user, $model, $attr, $virtAttr)
    {
        return new static([
            'sm' => $sm,
            'role' => $role,
            'user' => $user,
            'model' => $model,
            'attr' => $attr,
            'virtAttr' => $virtAttr,
        ]);
    }
}