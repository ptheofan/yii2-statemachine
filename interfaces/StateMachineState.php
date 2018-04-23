<?php
/**
 * User: Paris Theofanidis
 * Date: 01/07/16
 * Time: 11:08
 */
namespace ptheofan\statemachine\interfaces;

use ptheofan\statemachine\commands\Command;
use ptheofan\statemachine\exceptions\CannotGuessEventException;
use ptheofan\statemachine\StateMachine;
use SimpleXMLElement;

/**
 * Interface StateMachineState
 *
 * @package ptheofan\statemachine\interfaces
 */
interface StateMachineState
{
    /**
     * @return boolean
     */
    public function isFinal();

    /**
     * @return boolean
     */
    public function isInitial();

    /**
     * @return boolean
     */
    public function isIntermediate();

    /**
     * @return string
     */
    public function getLabel();

    /**
     * @return string
     */
    public function getValue();

    /**
     * @param string|null $role
     * @return StateMachineEvent[]
     */
    public function getEvents($role = null);

    /**
     * @return StateMachineTimeout[]
     */
    public function getTimeOuts();

    /**
     * @param string $value
     * @return StateMachineEvent[]
     */
    public function getEventsTargeting($value);

    /**
     * @param string $label
     * @param StateMachineContext $context
     * @return StateMachineEvent|null
     */
    public function getEventByLabel($label, $context);

    /**
     * @return Command[]
     */
    public function getEnterCommands();

    /**
     * @return Command[]
     */
    public function getExitCommands();

    /**
     * @param string $target
     * @param string $role
     * @return StateMachineEvent
     * @throws CannotGuessEventException
     */
    public function guessEvent($target, $role);

    /**
     * @return string
     */
    public function __toString();

    /**
     * @param SimpleXMLElement $xml
     * @param StateMachine $sm
     * @return static
     */
    public static function fromXml(SimpleXMLElement $xml, StateMachine $sm);
}