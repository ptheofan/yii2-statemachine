<?php

namespace ptheofan\statemachine\dbmodels;

use ptheofan\behaviors\mysql\SqlToTimestampBehavior;
use ptheofan\statemachine\exceptions\EventNotFoundException;
use ptheofan\statemachine\interfaces\StateMachineContext;
use ptheofan\statemachine\StateMachineBehavior;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * Class SmTimeout
 *
 * @package ptheofan\statemachine\dbmodels
 */
class SmTimeout extends ActiveRecord
{
    /**
     * @return array
     */
    public function behaviors()
    {
        $rVal = parent::behaviors();
        $rVal['timestamp'] = [
            'class' => SqlToTimestampBehavior::className(),
            'attributes' => ['expires_at'],
        ];

        return $rVal;
    }

    /**
     * @return StateMachineContext
     * @throws EventNotFoundException
     */
    public function transition()
    {
        /** @var ActiveRecord $modelName */
        $modelName = $this->model;
        $model = $modelName::findOne(Json::decode($this->model_pk));

        /** @var StateMachineBehavior $smBehavior */
        $smBehavior = $model->{$this->virtual_attribute};
        return $smBehavior->trigger($this->event_name);
    }
}
