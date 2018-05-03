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

class Permission extends Condition
{
    /**
     * @var string
     */
    public $perm;

    /**
     * Execute the command on the $context
     *
     * @param StateMachineContext $context
     * @return bool
     */
    public function isValid(StateMachineContext $context)
    {
        if (empty($this->perm)) {
            return true;
        }

        return Yii::$app->user->can($this->perm, ['context' => $context, 'permission' => $this]);
    }
}