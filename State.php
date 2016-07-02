<?php
/**
 * User: Paris Theofanidis
 * Date: 04/06/16
 * Time: 22:55
 */
namespace ptheofan\statemachine;

use ptheofan\statemachine\commands\Command;
use ptheofan\statemachine\exceptions\CannotGuessEventException;
use ptheofan\statemachine\interfaces\StateMachineEvent;
use ptheofan\statemachine\interfaces\StateMachineState;
use ptheofan\statemachine\interfaces\StateMachineTimeout;
use SimpleXMLElement;
use yii\base\Object;

class State extends Object implements StateMachineState
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
     * @var Command[]
     */
    private $enterCommands;

    /**
     * @var Command[]
     */
    private $exitCommands;

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
     * @param string|null $role
     * @return StateMachineEvent[]
     */
    public function getEvents($role = null)
    {
        if ($role === null) {
            return $this->events;
        } else {
            return array_filter($this->events, function($e) use ($role) {
                /** @var StateMachineEvent $e */
                return $e->isEligible($role);
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
     * @param string|null $role
     * @return StateMachineEvent|null
     */
    public function getEventByLabel($label, $role = null)
    {
        $events = array_merge($this->getEvents(), $this->getTimeOuts());
        /** @var StateMachineEvent $event */
        foreach ($events as $event) {
            if ($event->getLabel() === $label) {
                if ($event->isRoleValid($role)) {
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
     * @param array $profile - appearance profile
     * @return array
     */
    public function dot($profile)
    {
        $p = $profile['default'];
        if ($this->isInitial) {
            $p = array_merge($p, $profile['initial']);
        } elseif ($this->isFinal) {
            $p = array_merge($p, $profile['final']);
        } elseif ($this->isIntermediate) {
            $p = array_merge($p, $profile['intermediate']);
        }

        $fillcolor = $p['fillcolor'];
        $style = $p['style'];
        $shape = $p['shape'];
        $fontcolor = $p['fontcolor'];
        $tableTypeIndicatorColor = $p['tableTypeIndicatorColor'];
        $tableTypeValueColor = $p['tableTypeValueColor'];

        $onEnter = [];
        foreach ($this->getEnterCommands() as $enterCommand) {
            $onEnter[] = $enterCommand->shortName();
        }

        $onExit = [];
        foreach ($this->getExitCommands() as $exitCommand) {
            $onExit[] = $exitCommand->shortName();
        }

        $stateTpl = '<table cellpadding="0" cellspacing="0" cellborder="0" border="0">';
        $stateTpl .= '<tr><td colspan="2">{stateLabel}</td></tr>';

        if (!empty($onEnter))
            $stateTpl .= '<tr><td valign="top" align="right"><font point-size="8" color="'.$tableTypeIndicatorColor.'">onEnter: </font></td><td align="left"><font point-size="8" color="'.$tableTypeValueColor.'">{onEnter}</font></td></tr>';

        if (!empty($onExit))
            $stateTpl .= '<tr><td valign="top" align="right"><font point-size="8" color="'.$tableTypeIndicatorColor.'">onExit: </font></td><td align="left"><font point-size="8" color="'.$tableTypeValueColor.'">{onExit}</font></td></tr>';

        $stateTpl .= '</table>';

        $label = strtr($stateTpl, [
            '{stateLabel}' => $this->value,
            '{onEnter}' => empty($onEnter) ? '-' : implode('<br align="left"/>', $onEnter) . '<br align="left"/>',
            '{onExit}' => empty($onExit) ? '-' : implode('<br align="left"/>', $onExit) . '<br align="left"/>',
        ]);

        return [
            'label' => chr(1) . '<'.$label.'>',
            'shape' => $shape,
            'margin' => 0.1,
            'fontsize' => 10,
            'fontname' => 'Helvetica Neue',
            'fontcolor' => $fontcolor,
            'fillcolor' => $fillcolor,
            'style' => $style,
        ];
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