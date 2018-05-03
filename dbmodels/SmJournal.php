<?php

namespace ptheofan\statemachine\dbmodels;

use ptheofan\statemachine\interfaces\StateMachineContext;
use ptheofan\statemachine\interfaces\StateMachineEvent;
use ptheofan\statemachine\interfaces\StateMachineJournal;
use ptheofan\statemachine\StateMachineBehavior;
use yii;
use yii\db\ActiveRecord;

/**
 * Class SmJournal
 *
 * @package ptheofan\statemachine\dbmodels
 *
 * @property int $id
 * @property string $model
 * @property string $model_pk
 * @property string $sm_name
 * @property string $attr
 * @property string $from_state
 * @property string $to_state
 * @property int $created_at
 * @property int $created_by
 */
class SmJournal extends ActiveRecord implements StateMachineJournal
{
    /**
     * @return array
     */
    public function behaviors()
    {
        return [
            [
                'class' => yii\behaviors\BlameableBehavior::className(),
                'updatedByAttribute' => false,
            ],
            [
                'class' => yii\behaviors\TimestampBehavior::className(),
                'value' => new yii\db\Expression('NOW()'),
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => false,
            ],
        ];
    }

    /**
     * @param StateMachineContext $context
     * @param StateMachineEvent|null $event - this is null when an item enters the SM for the first time
     * @return static
     */
    public static function nu(StateMachineContext $context, $event)
    {
        $j = new static();
        $m = $context->getModel();
        $user = $context->getIdentity();
        if ($user) {
            $j->created_by = $user->getId();
        }

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

    /**
     * @param StateMachineBehavior $stateMachineBehavior
     * @return $this
     */
    public static function getLastEntryOf($stateMachineBehavior)
    {
        // TODO: Fix code duplication (this comes from Context)
        // Generate model id
        $model = $stateMachineBehavior->owner;
        $pk = $model->getPrimaryKey(true);
        foreach ($pk as $k => &$v) {
            if (is_object($v)) {
                $v = (string)$v;
            }
        }

        return static::find()
            ->andWhere([
                'model_pk' => \yii\helpers\Json::encode($pk),
                'model' => $model::className(),
                'attr' => $stateMachineBehavior->attr,
            ])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(1)
            ->one();
    }
}
