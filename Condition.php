<?php
/**
 * User: Paris Theofanidis
 * Date: 05/06/16
 * Time: 01:10
 */
namespace ptheofan\statemachine;

use ptheofan\statemachine\exceptions\InvalidSchemaException;
use ptheofan\statemachine\interfaces\StateMachineContext;
use SimpleXMLElement;
use yii;
use yii\base\BaseObject;

/**
 * Class Condition
 *
 * @package ptheofan\statemachine\commands
 */
abstract class Condition extends BaseObject
{
    /**
     * @var StateMachine
     */
    protected $sm;

    /**
     * Execute the command on the $context
     * @param StateMachineContext $context
     * @return bool
     */
    public abstract function isValid(StateMachineContext $context);

    /**
     * @return string
     * @throws \ReflectionException
     */
    public function shortName()
    {
        $ref = new \ReflectionClass($this);
        return $ref->getShortName();
    }

    /**
     * @param SimpleXMLElement $xml
     * @param StateMachine $sm
     * @return Condition
     * @throws InvalidSchemaException
     * @throws \yii\base\InvalidConfigException
     */
    public static function fromXml(SimpleXMLElement $xml, StateMachine $sm)
    {
        $config = [];
        foreach($xml->attributes() as $key => $value) {
            $config[$key] = (string)$value;
        }

        if ($xml->count() > 0) {
            /** @var SimpleXMLElement $child */
            foreach ($xml->children() as $child) {
                $config[(string)$child->getName()][] = (string)$child;
            }
        } else {
            $v = trim((string)$xml[0]);
            if (!empty($v)) {
                $config[$xml->getName()] = $v;
            }
        }

        // If no class is defined use the node name as class
        if (!isset($config['class'])) {
            $config['class'] = ucfirst((string)$xml->getName());
        }

        // If no namespace is defined, use the $sm default commands namespace
        if (strpos($config['class'], '\\') === false) {
            $config['class'] = $sm->conditionsNamespace . '\\' . $config['class'];
        }

        $condition = Yii::createObject($config);
        if (!($condition instanceof Condition)) {
            throw new InvalidSchemaException("All state machine conditions must derive from ptheofan\\statemachine\\Condition");
        }

        $condition->sm = $sm;
        return $condition;
    }
}