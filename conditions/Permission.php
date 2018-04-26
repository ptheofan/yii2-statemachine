<?php
/**
 * User: Paris Theofanidis
 * Date: 23.04.18
 * Time: 03:20
 */

namespace ptheofan\statemachine\conditions;

use ptheofan\statemachine\Condition;
use ptheofan\statemachine\interfaces\StateMachineContext;
use Yii;

/**
 * Class Permission
 *
 * @package ptheofan\statemachine\conditions
 */
class Permission extends Condition
{
    /**
     * @var string
     */
    public $permission;

    /**
     * Execute the command on the $context
     *
     * @param StateMachineContext $context
     * @return bool
     */
    public function isValid(StateMachineContext $context)
    {
        if (empty($this->permission)) {
            return true;
        }

        return Yii::$app->user->can($this->permission[0], ['context' => $context, 'permission' => $this]);
    }
}