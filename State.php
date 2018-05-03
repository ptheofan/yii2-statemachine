<?php
/**
 * User: Paris Theofanidis
 * Date: 04/06/16
 * Time: 22:55
 */
namespace ptheofan\statemachine;

use ptheofan\statemachine\commands\Command;
use ptheofan\statemachine\exceptions\CannotGuessEventException;
use ptheofan\statemachine\interfaces\StateMachineContext;
use ptheofan\statemachine\interfaces\StateMachineEvent;
use ptheofan\statemachine\interfaces\StateMachineState;
use ptheofan\statemachine\interfaces\StateMachineTimeout;
use SimpleXMLElement;
use yii\base\BaseObject;

class State extends BaseObject implements StateMachineState
{
    /**
     * @var StateMachine
     */
    protected $sm;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $value;

    /**
     * @var bool
     */
    protected $isFinal;

    /**
     * @var bool
     */
    protected $isInitial;

    /**
     * @var bool
     */
    protected $isIntermediate;

    /**
     * @var StateMachineEvent[]
     */
    protected $events = [];

    /**
     * @var StateMachineTimeout[]
     */
    protected $timeouts = [];

    /**
     * @var SimpleXMLElement[]
     */
    protected $enter = [];

    /**
     * @var SimpleXMLElement[]
     */
    protected $exit = [];

    /**
     * @var array
     */
    protected $data;

    /**
     * @var Command[]
     */
    private $enterCommands;

    /**
     * @var Command[]
     */
    private $exitCommands;

    /**
     * @return boolean
     */
    public function isFinal()
    {
        return $this->isFinal;
    }

    /**
     * @return boolean
     */
    public function isInitial()
    {
        return $this->isInitial;
    }

    /**
     * @return boolean
     */
    public function isIntermediate()
    {
        return $this->isIntermediate;
    }

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
    public function getValue()
    {
        return $this->value;
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
     * @param StateMachineContext|null $context
     * @return interfaces\StateMachineEvent[]
     */
    public function getEvents($context = null)
    {
        if ($context === null) {
            return $this->events;
        } else {
            return array_filter($this->events, function($e) use ($context) {
                /** @var StateMachineEvent $e */
                return $e->isValid($context);
            });
        }
    }

    /**
     * @return StateMachineTimeout[]
     */
    public function getTimeOuts()
    {
        return $this->timeouts;
    }

    /**
     * @param string $value
     * @return StateMachineEvent[]
     */
    public function getEventsTargeting($value)
    {
        $rVal = [];
        foreach ($this->getEvents() as $event) {
            if ($event->getTarget() === $value) {
                $rVal[] = $event;
            }
        }

        return $rVal;
    }

    /**
     * @param string $label
     * @param StateMachineContext $context
     * @return StateMachineEvent|null
     */
    public function getEventByLabel($label, $context)
    {
        $events = array_merge($this->getEvents(), $this->getTimeOuts());
        /** @var StateMachineEvent $event */
        foreach ($events as $event) {
            if ($event->getLabel() === $label) {
                if ($event->isValid($context)) {
                    return $event;
                }
            }
        }

        return null;
    }

    /**
     * @return Command[]
     */
    public function getEnterCommands()
    {
        if (!$this->enterCommands) {
            $sm = $this->sm;
            $this->enterCommands = array_map(function($xml) use ($sm) { return Command::fromXml($xml, $sm); }, $this->enter);
        }

        return $this->enterCommands;
    }

    /**
     * @return Command[]
     */
    public function getExitCommands()
    {
        if (!$this->exitCommands) {
            $sm = $this->sm;
            $this->exitCommands = array_map(function($xml) use ($sm) { return Command::fromXml($xml, $sm); }, $this->exit);
        }

        return $this->exitCommands;
    }

    /**
     * @param string $target
     * @param string $role
     * @return StateMachineEvent
     * @throws CannotGuessEventException
     */
    public function guessEvent($target, $role)
    {
        $events = $this->getEventsTargeting($target);
        $candidates = [];
        foreach ($events as $event) {
            if ($event->isRoleValid($role)) {
                $candidates[] = $event;
            }
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        } elseif (count($candidates) === 0) {
            throw new CannotGuessEventException("Cannot find any suitable event.");
        } else {
            throw new CannotGuessEventException("Cannot find any suitable event. Too many candidates.");
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->value;
    }

    /**
     * @param SimpleXMLElement $xml
     * @param StateMachine $sm
     * @return static
     */
    public static function fromXml(SimpleXMLElement $xml, StateMachine $sm)
    {
        $rVal = new static();

        // Attach the statemachine
        $rVal->sm = $sm;

        // Value and Label
        $rVal->value = (string)$xml->attributes()->value;
        $rVal->label = (string)$xml->attributes()->label;

        $rVal->data = [];
        foreach ($xml->attributes() as $k => $v) {
            if (!in_array($k, ['value', 'label'])) {
                $rVal->data[$k] = (string)$v;
            }
        }

        // Load events
        foreach ($xml->event as $eventXml) {
            $rVal->events[] = Event::fromXml($eventXml, $sm, $rVal);
        }

        // Load timeouts
        foreach ($xml->timeout as $timeoutXml) {
            $rVal->timeouts[] = Timeout::fromXml($timeoutXml, $sm, $rVal);
        }

        // Enter Commands XML fragments
        $enterXml = !empty($xml->enter) ? $xml->enter[0]->command : [];
        foreach ($enterXml as $item) {
            $rVal->enter[] = $item;
        }

        // Exit Commands XML fragments
        $exitXml = !empty($xml->exit) ? $xml->exit[0]->command : [];
        foreach ($exitXml as $item) {
            $rVal->exit[] = $item;
        }

        $rVal->isInitial = $sm->getInitialStateValue() === $rVal->value;
        $rVal->isFinal = empty($rVal->events);

        return $rVal;
    }
}