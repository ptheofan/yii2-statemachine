<?php
/**
 * User: Paris Theofanidis
 * Date: 10/06/16
 * Time: 20:53
 */
namespace ptheofan\statemachine;

use common\models\sql\SmTimeout;
use SimpleXMLElement;
use yii\helpers\Json;

/**
 * Class Timeout
 *
 * @package common\sm
 */
class Timeout extends Event implements StateMachineTimeout
{
    /**
     * @var int
     */
    private $expiresAt;

    /**
     * @return int
     */
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }

    /**
     * Changing the expiration timeout you can dynamically reconfigure the timeout
     * @param $timestamp
     */
    public function setExpiresAt($timestamp)
    {
        $this->expiresAt = $timestamp;
    }

    /**
     * Call this function to clean up registered timeouts
     * @param Context $context
     */
    public static function cleanUp(Context $context)
    {
        $model = $context->getModel();
        $modelName = $model::className();
        $modelPk = $context->getModelPk();
        $virtAttr = $context->getVirtAttr();
        $smName = $context->getSm()->name;

        SmTimeout::deleteAll('model = :model AND model_pk = :model_pk AND virtual_attribute = :virtAttr AND sm_name = :smName', [
            'model' => $modelName,
            'model_pk' => $modelPk,
            'virtAttr' => $virtAttr,
            'smName' => $smName,
        ]);
    }

    /**
     * @param Context $context
     */
    public function register(Context $context)
    {
        $m = new SmTimeout();

        $m->model = $context->getModelClassName();
        $m->virtual_attribute = $context->getVirtAttr();
        $m->sm_name = $context->getSm()->name;
        $m->event_name = $this->getLabel();
        $m->expires_at = $this->getExpiresAt();
        $m->model_pk = $context->getModelPk();
        $m->save();
    }

    /**
     * @param SimpleXMLElement $xml
     * @param StateMachine $sm
     * @param State $state
     * @return static
     */
    public static function fromXml(SimpleXMLElement $xml, StateMachine $sm, State $state)
    {
        /** @var Timeout $rVal */
        $rVal = parent::fromXml($xml, $sm, $state);

        $dt = new \DateTime();
        $days = (int)@$xml->attributes()->days;
        $hours = (int)@$xml->attributes()->hours;
        $minutes = (int)@$xml->attributes()->minutes;
        $seconds = 0; //@$state->attributes()->seconds;
        $interval = sprintf('P0000-00-%02dT%02d:%02d:%02d', $days, $hours, $minutes, $seconds);
        $dt->add(new \DateInterval($interval));
        $rVal->expiresAt = $dt->getTimestamp();

        return $rVal;
    }
}