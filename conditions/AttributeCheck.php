<?php
/**
 * User: Paris Theofanidis
 * Date: 17/07/16
 * Time: 14:11
 */
namespace ptheofan\statemachine\conditions;

use ptheofan\statemachine\interfaces\StateMachineContext;

/**
 * Class AttributeCheck
 *
 * @package ptheofan\statemachine\conditions
 */
class AttributeCheck extends Condition
{
    /**
     * @var string
     */
    public $getter;

    /**
     * @var string
     */
    public $expectedValue;

    /**
     * @var true/false
     */
    public $strictMode;

    /**
     *
     */
    public function init()
    {
        parent::init();
        if ($this->strictMode === 'true') {
            $this->strictMode = true;
        } else {
            $this->strictMode = false;
        }
    }

    /**
     * Execute the command on the $context
     *
     * @param StateMachineContext $context
     * @return bool
     */
    public function check(StateMachineContext $context)
    {
        $value = $context->getModel()->{$this->getter};
        if ($this->strictMode) {
            return $value === $this->expectedValue;
        } else {
            return $value == $this->expectedValue;
        }
    }
}