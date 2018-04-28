<?php
/**
 * User: Paris Theofanidis
 * Date: 01/07/16
 * Time: 11:05
 */
namespace ptheofan\statemachine\interfaces;

use ptheofan\statemachine\Context;
use ptheofan\statemachine\exceptions\InvalidSchemaException;
use ptheofan\statemachine\exceptions\StateNotFoundException;
use ptheofan\statemachine\StateMachine;
use SimpleXMLElement;

/**
 * Interface StateMachineEvent
 *
 * @package ptheofan\statemachine\interfaces
 */
interface StateMachineEvent
{
    /**
     * @return string
     */
    public function getLabel();

    /**
     * @return string
     */
    public function getTarget();

    /**
     * @return StateMachineState
     * @throws InvalidSchemaException
     * @throws StateNotFoundException
     */
    public function getTargetState();

    /**
     * @return StateMachineState
     */
    public function getState();

    /**
     * @return array
     */
    public function getRoles();

    /**
     * @param string $role
     * @return bool
     */
    public function hasRole($role);

    /**
     * @param string $role
     * @return bool
     */
    public function isRoleValid($role);

    /**
     * Test if the event can be triggered by ALL of $roles
     *
     * @param array|string $roles
     * @param StateMachineContext|null $context
     * @return bool
     */
    public function isEligible($roles, $context);

    /**
     * Will return true if ONLY the $roles can trigger this event
     * @param array|string $roles
     * @return bool
     */
    public function isExclusiveTo($roles);

    /**
     * @return bool
     */
    public function isRefresh();

    /**
     * @param string $name
     * @param mixed $default
     * @return string
     */
    public function getValue($name, $default);

    /**
     * @param SimpleXMLElement $xml
     * @param StateMachine $sm
     * @param StateMachineState $state
     * @return static
     */
    public static function fromXml(SimpleXMLElement $xml, StateMachine $sm, StateMachineState $state);
}