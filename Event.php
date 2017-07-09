<?php
/**
 * User: Paris Theofanidis
 * Date: 05/06/16
 * Time: 13:02
 */
namespace ptheofan\statemachine;

use ptheofan\statemachine\conditions\Condition;
use ptheofan\statemachine\interfaces\StateMachineContext;
use ptheofan\statemachine\interfaces\StateMachineEvent;
use ptheofan\statemachine\interfaces\StateMachineState;
use SimpleXMLElement;
use yii\base\Object;

/**
 * Class Event
 *
 * @package ptheofan\statemachine
 */
class Event extends Object implements StateMachineEvent
{
    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $target;

    /**
     * @var array
     */
    protected $roles;

    /**
     * @var StateMachine
     */
    protected $sm;

    /**
     * @var State - the state the event belongs to
     */
    protected $state;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var Condition[]
     */
    protected $conditions = [];

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @return State
     * @throws exceptions\InvalidSchemaException
     * @throws exceptions\StateNotFoundException
     */
    public function getTargetState()
    {
        return $this->sm->getState($this->target);
    }

    /**
     * @return State
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @return array
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @param string $role
     * @return bool
     */
    public function hasRole($role)
    {
        return in_array($role, $this->roles);
    }

    /**
     * @param string $role
     * @return bool
     */
    public function isRoleValid($role)
    {
        if (!$role || empty($this->roles)) {
            return true;
        }

        return in_array($role, $this->roles);
    }

    /**
     * Test if the event can be triggered by ALL of $roles
     *
     * @param array|string $roles
     * @param StateMachineContext|null $context
     * @return bool
     */
    public function isEligible($roles, $context = null)
    {
        if (!is_array($roles)) {
            if (!in_array($roles, $this->roles)) {
                return false;
            }
        } else {
            if (array_diff($roles, $this->roles) !== []) {
                return false;
            }
        }

        if ($context) {
            foreach ($this->conditions as $condition) {
                if (!$condition->check($context)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Will return true if ONLY the $roles can trigger this event
     * @param array|string $roles
     * @return bool
     */
    public function isExclusiveTo($roles)
    {
        if (!is_array($roles)) {
            return $this->roles === [$roles];
        } else {
            return array_diff($this->roles, $roles) === [];
        }
    }

    /**
     * @return bool
     */
    public function isRefresh()
    {
        return $this->getTarget() === $this->getState()->getValue();
    }

    /**
     * @deprecated replaced with getDataValue
     * @param string $name
     * @param mixed $default
     * @return string
     */
    public function getValue($name, $default)
    {
        return (array_key_exists($name, $this->data)) ? $this->data[$name] : $default;
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return string
     */
    public function getDataValue($name, $default)
    {
        return (array_key_exists($name, $this->data)) ? $this->data[$name] : $default;
    }

    /**
     * @param SimpleXMLElement $xml
     * @param StateMachine $sm
     * @param StateMachineState $state
     * @return static
     */
    public static function fromXml(SimpleXMLElement $xml, StateMachine $sm, StateMachineState $state)
    {
        $rVal = new static();
        $rVal->sm = $sm;
        $rVal->state = $state;

        $rVal->target = (string)$xml->attributes()->target;
        $rVal->label = (string)$xml->attributes()->label;

        $rVal->data = [];
        foreach ($xml->attributes() as $k => $v) {
            if (!in_array($k, ['target', 'label'])) {
                $rVal->data[$k] = (string)$v;
            }
        }

        if (!empty($xml->role)) {
            foreach ($xml->role as $roleXml) {
                $rVal->roles[] = (string)$roleXml;
            }
        }

        $conditions = !empty($xml->conditions) ? $xml->conditions[0]->condition : [];
        foreach ($conditions as $condition) {
            $rVal->conditions[] = Condition::fromXml($condition, $sm);
        }

        return $rVal;
    }
}