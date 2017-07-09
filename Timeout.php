<?php
/**
 * User: Paris Theofanidis
 * Date: 10/06/16
 * Time: 20:53
 */
namespace ptheofan\statemachine;

use ptheofan\statemachine\dbmodels\SmTimeout;
use ptheofan\statemachine\interfaces\StateMachineContext;
use ptheofan\statemachine\interfaces\StateMachineState;
use ptheofan\statemachine\interfaces\StateMachineTimeout;
use SimpleXMLElement;

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
     * @param StateMachineContext $context
     */
    public static function cleanUp(StateMachineContext $context)
    {
        $model = $context->getModel();
        $modelName = $model::className();
        $modelPk = $context->getModelPk();
        $virtAttr = $context->getVirtAttr();
        $smName = $context->getSm()->name;

        $dbClass = $context->getSm()->modelTimeout;
        if ($dbClass) {
            /** @var SmTimeout $dbClass */
            $dbClass::deleteAll('model = :model AND model_pk = :model_pk AND virtual_attribute = :virtAttr AND sm_name = :smName', [
                'model' => $modelName,
                'model_pk' => $modelPk,
                'virtAttr' => $virtAttr,
                'smName' => $smName,
            ]);
        }
    }

    /**
     * @param StateMachineContext $context
     */
    public function register(StateMachineContext $context)
    {
        $dbClass = $context->getSm()->modelTimeout;
        if ($dbClass && !$context->isModelDeleted()) {
            /** @var SmTimeout $m */
            $m = new $dbClass();

            $m->model = $context->getModelClassName();
            $m->virtual_attribute = $context->getVirtAttr();
            $m->sm_name = $context->getSm()->name;
            $m->event_name = $this->getLabel();
            $m->expires_at = $this->getExpiresAt();
            $m->model_pk = $context->getModelPk();
            $m->save();
        }
    }

    /**
     * @param SimpleXMLElement $xml
     * @param StateMachine $sm
     * @param StateMachineState $state
     * @return static
     */
    public static function fromXml(SimpleXMLElement $xml, StateMachine $sm, StateMachineState $state)
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