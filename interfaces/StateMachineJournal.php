<?php
/**
 * User: Paris Theofanidis
 * Date: 01/07/16
 * Time: 10:24
 */
namespace ptheofan\statemachine\interfaces;

/**
 * Interface StateMachineJournal
 *
 * @package ptheofan\statemachine\interfaces
 */
interface StateMachineJournal
{
    /**
     * @param StateMachineContext $context
     * @param StateMachineEvent|null $event - this is null when an item enters the SM for the first time
     * @return $this
     */
    public static function nu(StateMachineContext $context, $event);
}