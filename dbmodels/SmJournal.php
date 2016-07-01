<?php

namespace ptheofan\statemachine\dbmodels;

use ptheofan\statemachine\interfaces\StateMachineContext;
use ptheofan\statemachine\interfaces\StateMachineEvent;
use ptheofan\statemachine\interfaces\StateMachineJournal;
use yii;
use yii\db\ActiveRecord;

/**
 * Class SmJournal
 *
 * @package ptheofan\statemachine\dbmodels
 */
class SmJournal extends ActiveRecord implements StateMachineJournal
{
    /**
     * @param StateMachineContext $context
     * @param StateMachineEvent|null $event - this is null when an item enters the SM for the first time
     * @return static
     */
    public static function nu(StateMachineContext $context, $event)
    {
        $j = new static();
        $m = $context->getModel();
        $j->role = $context->getRole();
        $j->created_by = $context->getUser()->getId();
        $j->model = $m::className();
        $j->sm_name = $context->getSm()->name;
        $j->attr = $context->getAttr();
        $j->model_pk = $context->getModelPk();

        if ($event) {
            $j->from_state = $event->getState()->getValue();
            $j->to_state = $event->getTargetState()->getValue();
        } else {
            $j->from_state = null;
            $j->to_state = $context->getSm()->getInitialStateValue();
        }

        $j->save();

        return $j;
    }
}
