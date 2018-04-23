<?php
/**
 * User: Paris Theofanidis
 * Date: 05/06/16
 * Time: 14:04
 */
namespace ptheofan\statemachine;

use ptheofan\statemachine\exceptions\CannotGuessEventException;
use ptheofan\statemachine\exceptions\EventNotFoundException;
use ptheofan\statemachine\exceptions\InvalidSchemaException;
use ptheofan\statemachine\exceptions\StateNotFoundException;
use ptheofan\statemachine\interfaces\StateMachineContext;
use ptheofan\statemachine\interfaces\StateMachineEvent;
use yii;
use yii\base\Behavior;
use yii\base\Exception;
use yii\base\InvalidValueException;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;

/**
 * Class StateMachineBehavior
 * @package ptheofan\statemachine
 *
 * @property BaseActiveRecord $owner
 */
class StateMachineBehavior extends Behavior
{
    /**
     * @var string - the name of the physical model attribute
     * ie. sm_status
     */
    public $attr;

    /**
     * @var string - the name of the virtual model attribute
     * ie. status
     */
    public $virtAttr;

    /**
     * @var StateMachine
     */
    public $sm;

    /**
     * @var bool
     */
    private $modelDeleted = false;

    /**
     * @return array
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'initStateMachine',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterModelDelete',
        ];
    }

    /**
     * @return bool
     */
    public function isModelDeleted()
    {
        return $this->modelDeleted;
    }

    /**
     * @param $evt
     */
    public function afterModelDelete($evt)
    {
        $this->modelDeleted = true;
    }

    /**
     * @param string $name
     * @param bool|true $checkVars
     * @return bool
     */
    public function canGetProperty($name, $checkVars = true)
    {
        return $name === $this->virtAttr || parent::canGetProperty($name, $checkVars);
    }

    /**
     * @param string $name
     * @param bool $checkVars
     * @return bool
     */
    public function canSetProperty($name, $checkVars = true)
    {
        return $name === $this->virtAttr || parent::canSetProperty($name, $checkVars);
    }

    /**
     * @param yii\web\IdentityInterface|null $identity
     * @return StateMachineContext
     * @throws \Throwable
     */
    public function createContext($identity = null)
    {
        $m = $this->owner;
        return Context::nu($this->sm, $identity, $m, $this->attr, $this->virtAttr);
    }

    /**
     * Get the current state
     *
     * @return State
     * @throws InvalidSchemaException
     * @throws StateNotFoundException
     * @throws exceptions\StateMachineNotFoundException
     */
    public function getState()
    {
        if (!empty($this->owner->{$this->attr})) {
            return $this->sm->getState($this->owner->{$this->attr});
        } else {
            return $this->sm->getState($this->sm->getInitialStateValue());
        }
    }

    /**
     * @param string|StateMachineEvent $event
     * @param yii\web\IdentityInterface|null $identity
     * @return StateMachineContext
     * @throws EventNotFoundException
     * @throws InvalidSchemaException
     * @throws StateNotFoundException
     * @throws \Exception
     * @throws \Throwable
     */
    public function trigger($event, $identity = null)
    {
        $m = $this->owner;

        // State
        $state = $this->sm->getState($m->{$this->attr});

        // Context
        $context = $this->createContext($identity);

        // Event
        if (!$event instanceof StateMachineEvent) {
            $evt = $state->getEventByLabel($event, $context);
            if (!$evt) {
                throw new EventNotFoundException("No valid event `{$event}`".($context ? ' (with context)' : null)." found in State Machine `{$this->sm->name}`");
            }

            $event = $evt;
        }

        try {
            $this->sm->transition($event, $context);
        } catch (Exception $e) {
            $context->attachException($e);
        }

        if ($context->hasErrors()) {
            // Migrate the context errors to the virtual attribute
            foreach ($context->getErrors() as $attr => $error) {
                $m->addError($this->virtAttr, $error);
            }
        }

        return $context;
    }

    /**
     * @param yii\web\IdentityInterface $identity
     * @return StateMachineEvent[]
     * @throws \Throwable
     */
    public function getTriggers($identity = null)
    {
        $context = $this->createContext($identity);
        return $context->getPossibleEvents();
    }

    /**
     * @return StateMachineContext
     * @throws \Throwable
     * @throws yii\db\Exception
     */
    public function initStateMachine()
    {
        $this->owner->off(ActiveRecord::EVENT_AFTER_INSERT, [$this, 'initStateMachine']);

        // If value already set then ignore the call
        $context = $this->createContext();
        $this->sm->initStateMachineAttribute($context);

        return $context;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->owner->{$this->attr};
    }

    /**
     * @param string $name
     * @param mixed $value
     * @throws CannotGuessEventException
     * @throws EventNotFoundException
     * @throws InvalidSchemaException
     * @throws StateNotFoundException
     * @throws \Throwable
     * @throws exceptions\StateMachineNotFoundException
     * @throws yii\base\UnknownPropertyException
     * @throws yii\db\Exception
     */
    public function __set($name, $value)
    {
        if ($name === $this->virtAttr) {
            $m = $this->owner;

            // Value did not change - ignore
            if ($m->{$this->attr} === $value) {
                return;
            }

            // First time? Initialize SM for $value state
            if ($m->{$this->attr} === null && $m->getIsNewRecord()) {
                if ($value !== $this->sm->getInitialStateValue()) {
                    throw new InvalidValueException("This attribute is just entering the State Machine and must be set to {$this->sm->getInitialStateValue()}");
                }

                $context = $this->initStateMachine();
            } else {
                // Not first time, try to trigger for $value
                $state = $this->sm->getState($m->{$this->attr});
                if (!$state) {
                    throw new InvalidSchemaException("Cannot load current state {$m->{$this->attr}}");
                }

                $event = $state->guessEvent($value, $this->internalGetUserRole(Yii::$app->user->identity));
                $context = $this->trigger($event);
            }

            // Migrate the context errors to the virtual attribute
            foreach ($context->errors as $attr => $error) {
                $m->addError($this->virtAttr, $error);
            }
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * @param string $name
     * @return mixed
     * @throws yii\base\UnknownPropertyException
     */
    public function __get($name)
    {
        if ($name === $this->virtAttr) {
            return $this;
        } else {
            return parent::__get($name);
        }
    }
}