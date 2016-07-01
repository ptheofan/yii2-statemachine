<?php
/**
 * User: Paris Theofanidis
 * Date: 01/07/16
 * Time: 11:10
 */
namespace ptheofan\statemachine\interfaces;

use ptheofan\statemachine\StateMachine;
use SimpleXMLElement;

/**
 * Interface StateMachineTimeout
 *
 * @package ptheofan\statemachine\interfaces
 */
interface StateMachineTimeout
{
    /**
     * @return int
     */
    public function getExpiresAt();

    /**
     * Changing the expiration timeout you can dynamically reconfigure the timeout
     * @param $timestamp
     */
    public function setExpiresAt($timestamp);

    /**
     * Call this function to clean up registered timeouts
     * @param StateMachineContext $context
     */
    public static function cleanUp(StateMachineContext $context);

    /**
     * @param StateMachineContext $context
     */
    public function register(StateMachineContext $context);

    /**
     * @param SimpleXMLElement $xml
     * @param StateMachine $sm
     * @param StateMachineState $state
     * @return static
     */
    public static function fromXml(SimpleXMLElement $xml, StateMachine $sm, StateMachineState $state);
}