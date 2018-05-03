<?php
/**
 * User: Paris Theofanidis
 * Date: 05/06/16
 * Time: 13:02
 */
namespace ptheofan\statemachine;

use ptheofan\statemachine\interfaces\StateMachineContext;
use ptheofan\statemachine\interfaces\StateMachineEvent;
use ptheofan\statemachine\interfaces\StateMachineState;
use SimpleXMLElement;
use yii\base\BaseObject;

/**
 * Class Event
 *
 * @package ptheofan\statemachine
 */
class Event extends BaseObject implements StateMachineEvent
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
     * @throws exceptions\StateMachineNotFoundException
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
     * @param StateMachineContext $context
     * @return bool
     */
    public function isValid(StateMachineContext $context)
    {
        foreach ($this->conditions as $condition) {
            if (!$condition->isValid($context)) {
                return false;
            }
        }

        return true;
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
     * @throws \yii\base\InvalidConfigException
     * @throws exceptions\InvalidSchemaException
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