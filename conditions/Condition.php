<?php
/**
 * User: Paris Theofanidis
 * Date: 05/06/16
 * Time: 01:10
 */
namespace ptheofan\statemachine\conditions;

use ptheofan\statemachine\exceptions\InvalidSchemaException;
use ptheofan\statemachine\interfaces\StateMachineContext;
use ptheofan\statemachine\StateMachine;
use SimpleXMLElement;
use yii;
use yii\base\Object;

/**
 * Class Condition
 *
 * @package ptheofan\statemachine\commands
 */
abstract class Condition extends Object
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
    public abstract function check(StateMachineContext $context);

    /**
     * @return string
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

        /** @var SimpleXMLElement $child */
        foreach($xml->children() as $child) {
            $config[(string)$child->getName()][] = (string)$child;
        }

        if (!isset($config['class'])) {
            throw new InvalidSchemaException("All conditions must have a class attribute");
        }

        // If no namespace is defined, use the $sm default commands namespace
        if (strpos($config['class'], '\\') === false) {
            $config['class'] = $sm->conditionsNamespace . '\\' . $config['class'];
        }

        $command = Yii::createObject($config);
        if (!($command instanceof Condition)) {
            throw new InvalidSchemaException("All state machine conditions must derive from ptheofan\\statemachine\\conditions\\Condition");
        }

        $command->sm = $sm;
        return $command;
    }
}