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
 *
 * @property int $id
 * @property string $model
 * @property string $model_pk
 * @property string $virtual_attribute
 * @property string $sm_name
 * @property string $event_name
 * @property int $expires_at
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

        // If model does not exist or is marked as deleted the drop this
        // TODO: Delete this timeout
        if (!$model || $model->isModelDeleted()) {
            return null;
        }

        /** @var StateMachineBehavior $smBehavior */
        $smBehavior = $model->{$this->virtual_attribute};
        return $smBehavior->trigger($this->event_name);
    }
}
