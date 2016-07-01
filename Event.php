<?php
/**
 * User: Paris Theofanidis
 * Date: 05/06/16
 * Time: 13:02
 */
namespace ptheofan\statemachine;

use ptheofan\statemachine\interfaces\StateMachineEvent;
use ptheofan\statemachine\interfaces\StateMachineState;
use SimpleXMLElement;
use yii\base\Object;

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
     * @param array|string $roles
     * @return bool
     */
    public function isEligible($roles)
    {
        if (!is_array($roles)) {
            return in_array($roles, $this->roles);
        } else {
            return array_diff($roles, $this->roles) === [];
        }
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

        if (empty($xml->role)) {
            if (empty($rVal->label)) {
                $rVal->roles = ['system'];
            } else {
                $rVal->roles = ['owner', 'admin'];
            }
        } else {
            foreach ($xml->role as $roleXml) {
                $rVal->roles[] = (string)$roleXml;
            }
        }

        return $rVal;
    }
}