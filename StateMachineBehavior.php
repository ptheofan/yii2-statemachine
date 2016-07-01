<?php
/**
 * User: Paris Theofanidis
 * Date: 05/06/16
 * Time: 14:04
 */
namespace ptheofan\statemachine;

use common\components\MySqlActiveRecord;
use common\models\sql\User;
use common\sm\exceptions\CannotGuessEventException;
use common\sm\exceptions\EventNotFoundException;
use common\sm\exceptions\InvalidSchemaException;
use common\sm\exceptions\StateMachineNotFoundException;
use common\sm\exceptions\TransitionException;
use Yii;
use yii\base\Behavior;
use yii\base\Exception;
use yii\base\InvalidValueException;
use yii\db\ActiveRecord;

/**
 * Class StateMachineBehavior
 * @package common\sm
 *
 * @method MySqlActiveRecord getOwner()
 * @property MySqlActiveRecord $owner
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
     * @param string|null $role
     * @return Context
     */
    public function createContext($role = null)
    {
        $m = $this->owner;
        if (!$role) {
            $role = $m->getUserRole(Yii::$app->user->identity);
        }

        return Context::nu($this->sm, $role, Yii::$app->user->identity, $m, $this->attr, $this->virtAttr);
    }

    /**
     * @param string|Event $event
     * @param string|null|false $role - null means automatically set role, false will explicitly NOT use any role
     * @return Context
     * @throws EventNotFoundException
     * @throws InvalidSchemaException
     * @throws \Exception
     * @throws exceptions\StateNotFoundException
     */
    public function trigger($event, $role = null)
    {
        $m = $this->owner;

        // State
        $state = $this->sm->getState($m->{$this->attr});

        // Role
        if ($role === null) {
            $role = $m->getUserRole(Yii::$app->user->getIdentity(false));
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
     * @return Event[]
     * @throws InvalidSchemaException
     * @throws exceptions\StateNotFoundException
     */
    public function getTriggers()
    {
        $m = $this->owner;
        $role = $m->getUserRole(Yii::$app->user->identity);
        return $this->sm->getState($m->{$this->attr})->getEvents($role);
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

                $event = $state->guessEvent($value, $m->getUserRole(Yii::$app->user->identity));
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