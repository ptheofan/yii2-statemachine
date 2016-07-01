<?php
/**
 * User: Paris Theofanidis
 * Date: 01/07/16
 * Time: 13:33
 */
namespace ptheofan\statemachine\console;

use ptheofan\statemachine\dbmodels\SmTimeout;
use yii\console\Controller;
use yii\db\Expression;
use yii\helpers\VarDumper;

/**
 * Class StateMachineController
 * This is an example implementation of how a console command manages the timeouts.
 * It is recommended that this runs as often as possible - ie. in crontab run every minute.
 *
 * @package ptheofan\statemachine\console
 */
class StateMachineController extends Controller
{
    /**
     * Manage the timeouts of the StateMachines
     */
    public function actionTimeouts()
    {
        /** @var SmTimeout[] $timeouts */
        $timeouts = SmTimeout::find()->andWhere(['<=', 'expires_at', new Expression('NOW()')])->all();

        foreach ($timeouts as $timeout) {
            $context = $timeout->transition();
            if ($context->hasException()) {
                // Handle timeout transition failure
                VarDumper::dump($context->getFirstException(),10);
            }
        }
    }
}