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
use ptheofan\statemachine\exceptions\TransitionException;
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
 * @method BaseActiveRecord getOwner()
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
     * @var callable $userRoleGetter(IdentityInterface $user)
     */
    public $userRoleGetter = 'getUserRole';

    /**
     * @return array
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'initStateMachine',
        ];
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
     * @return mixed|null
     */
    protected function internalGetUserRole($identity)
    {
        if ($this->userRoleGetter) {
            if (!$identity) {
                $identity = Yii::$app->user->identity;
            }

            $getter = is_string($this->userRoleGetter) ? [$this->owner, $this->userRoleGetter] : $this->userRoleGetter;
            return call_user_func($getter, $identity);
        } else {
            return null;
        }
    }

    /**
     * @param string|null $role
     * @return StateMachineContext
     */
    public function createContext($role = null)
    {
        $m = $this->owner;
        $identity = Yii::$app->user->getIdentity(false);
        if ($role === null) {
            $role = $this->internalGetUserRole($identity);
        }

        return Context::nu($this->sm, $role, $identity, $m, $this->attr, $this->virtAttr);
    }

    /**
     * @param string|StateMachineEvent $event
     * @param string|null|false $role - null means automatically set role, false will explicitly NOT use any role
     * @return StateMachineContext
     * @throws EventNotFoundException
     * @throws InvalidSchemaException
     * @throws StateNotFoundException
     * @throws \Exception
     */
    public function trigger($event, $role = null)
    {
        $m = $this->owner;

        // State
        $state = $this->sm->getState($m->{$this->attr});

        // Role
        if ($role === null) {
            $role = $this->internalGetUserRole(Yii::$app->user->getIdentity(false));
        }

        // Event
        if (is_string($event)) {
            $evt = $state->getEventByLabel($event, $role);
            if (!$evt) {
                throw new EventNotFoundException("Event `{$event}` for Role `{$role}` not found in State Machine `{$this->sm->name}`");
            }

            $event = $evt;
        }

        // Context
        $context = $this->createContext($role);

        try {
            $this->sm->transition($event, $context);
        } catch (Exception $e) {
            $context->attachException($e);
        } finally {
            // Migrate the context errors to the virtual attribute
            foreach ($context->errors as $attr => $error) {
                $m->addError($this->virtAttr, $error);
            }

            return $context;
        }
    }

    /**
     * @param string|null|false $role - null will auto-detect user role, false will get every possible trigger regardless role
     * @return Event[]
     * @throws InvalidSchemaException
     * @throws StateNotFoundException
     */
    public function getTriggers($role = null)
    {
        $context = $this->createContext($role);
        return $context->getPossibleEvents();
    }

    /**
     * @throws InvalidSchemaException
     * @throws TransitionException
     * @throws \yii\db\Exception
     * @throws exceptions\StateNotFoundException
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
     * @throws TransitionException
     * @throws \yii\base\UnknownPropertyException
     * @throws exceptions\StateNotFoundException
     * @throws null
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