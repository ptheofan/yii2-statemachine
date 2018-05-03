<?php
/**
 * User: Paris Theofanidis
 * Date: 04/06/16
 * Time: 22:55
 */
namespace ptheofan\statemachine;

use Alom\Graphviz\Digraph;
use Exception;
use ptheofan\statemachine\exceptions\InvalidSchemaException;
use ptheofan\statemachine\exceptions\InvalidSchemaSourceException;
use ptheofan\statemachine\exceptions\StateMachineNotFoundException;
use ptheofan\statemachine\exceptions\StateNotFoundException;
use ptheofan\statemachine\interfaces\StateMachineContext;
use ptheofan\statemachine\interfaces\StateMachineEvent;
use ptheofan\statemachine\interfaces\StateMachineJournal;
use SimpleXMLElement;
use yii;
use yii\base\Component;

/**
 * Class StateMachine
 *
 * @package ptheofan\statemachine
 */
class StateMachine extends Component
{
    /**
     * The model that represents journal entries - set to null to disable journal
     * @var string
     */
    public $modelJournal = 'ptheofan\\statemachine\\dbmodels\\SmJournal';

    /**
     * @var string
     */
    public $modelTimeout = 'ptheofan\\statemachine\\dbmodels\\SmTimeout';

    /**
     * The class tha represents the timeout
     * @var string
     */
    public $timeout = 'Timeout';

    /**
     * The class tha represents the context
     * @var string
     */
    public $context = 'Context';

    /**
     * The class that represents a state
     * @var string|array
     */
    public $state = 'State';

    /**
     * The class that represents an Event
     * @var string
     */
    public $event = 'Event';

    /**
     * The schema of the StateMachine that describes the states, events, etc.
     * This can be
     *      string - xml
     *      string - path/to/file.xml
     *      SimpleXMLElement - Loaded XML Element
     *      callable - function returning any of the above. Accepts 1 parameter, the state machine itself
     *                 ie. mySchemaLoader(StateMachine $sm)
     *
     * @var string|SimpleXMLElement|callable
     */
    public $schemaSource;

    /**
     * @var string - The name of the state machine
     */
    public $name;

    /**
     * @var string - default namespace for the commands (will be used if provided commands do not already provide namespace)
     */
    public $commandsNamespace = '\\ptheofan\\statemachine\\commands';

    /**
     * @var string - default namespace for the conditions (will be used if provided conditions do not already provide namespace)
     */
    public $conditionsNamespace = '\\ptheofan\\statemachine\\conditions';

    /**
     * The cached states
     * @var State[]
     */
    private $states = [];

    /**
     * The value of the initial state
     * @var string
     */
    private $__initialStateValue;

    /**
     * Always use the getter to access $__xml as it needs to be initialized
     * @var SimpleXMLElement - the loaded XML
     */
    private $__xml;

    /**
     * @return string
     * @throws InvalidSchemaException
     */
    public function getInitialStateValue()
    {
        if (!$this->__initialStateValue) {
            $this->__initialStateValue = (string)$this->getXml()->attributes()->initialState;
            if (!$this->__initialStateValue) {
                throw new InvalidSchemaException("The initial state value is missing - should be registered in the XML schema");
            }
        }

        return $this->__initialStateValue;
    }

    /**
     * @return SimpleXMLElement
     * @throws InvalidSchemaException
     * @throws InvalidSchemaSourceException
     * @throws StateMachineNotFoundException
     */
    public function getXml()
    {
        if (!($this->__xml instanceof SimpleXMLElement)) {
            $schemaSource = $this->schemaSource;

            // Callable?
            if (is_callable($schemaSource)) {
                $schemaSource = call_user_func($this->schemaSource, $this);
            }

            // SimpleXMLElement
            if ($schemaSource instanceof SimpleXMLElement) {
                $this->__xml = $schemaSource;
            } elseif (substr($schemaSource, 0, 5) === '<?xml') {
                // XML in a string
                $this->__xml = simplexml_load_string($schemaSource);
            } else {
                // XML file
                $file = Yii::getAlias($schemaSource);
                if (!file_exists($file)) {
                    throw new InvalidSchemaSourceException("The file `{$file}` does not exist");
                }

                $this->__xml = simplexml_load_file($file);
            }

            // Focus on xml part that represents this state machine
            $this->__xml = $this->__xml->xpath('//state-machine[@ name="'.$this->name.'"]');
            if (empty($this->__xml)) {
                throw new StateMachineNotFoundException("State machine does not exist in the provided schema");
            }
            $this->__xml = $this->__xml[0];
        }

        return $this->__xml;
    }

    /**
     * @param StateMachineEvent $event
     * @param StateMachineContext $context
     * @return bool
     * @throws Exception
     * @throws \yii\db\Exception
     */
    public function transition(StateMachineEvent $event, StateMachineContext $context)
    {
        // Let the context know which event triggered the transition
        $context->setEvent($event);

        try {
            // Leaving current state...
            foreach ($event->getState()->getExitCommands() as $command) {
                if (!$command->execute($context)) {
                    return false;
                }
            }

            // Cleanup old timeouts
            Timeout::cleanUp($context);

            // Entering new state...
            $context->getModel()->{$context->getAttr()} = $event->getTarget();
            if ($context->getModel()->hasMethod('save')) {
                $context->getModel()->save(false, [$context->getAttr()]);
            }

            foreach ($event->getTargetState()->getEnterCommands() as $command) {
                if (!$context->isModelDeleted()) {
                    if (!$command->execute($context)) {
                        return false;
                    }
                }
            }

            // Register the new timeouts
            foreach ($event->getTargetState()->getTimeOuts() as $timeout) {
                $timeout->register($context);
            }

            // Update Journal - if applicable
            if ($this->modelJournal) {
                $journal = $this->modelJournal;
                /** @var StateMachineJournal $journal */
                $journal::nu($context, $event);
            }

            // transition completed successfully
            return true;
        } catch (Exception $e) {
            $context->attachException($e);
            throw $e;
        }
    }

    /**
     * Call this when a model is entering the state machine for the first time.
     * @param StateMachineContext $context
     * @return bool
     * @throws Exception
     * @throws \yii\db\Exception
     */
    public function initStateMachineAttribute(StateMachineContext $context)
    {
        try {
            // Entering new state...
            $context->getModel()->{$context->getAttr()} = $event->getTarget();
            $state = $this->getState($this->getInitialStateValue());
            foreach ($state->getEnterCommands() as $command) {
                if (!$command->execute($context)) {
                    return false;
                }
            }

            // Persist model data if possible
            if ($context->getModel()->hasMethod('save')) {
                $context->getModel()->save(false, [$context->getAttr()]);
            }

            // Register the new timeouts
            foreach ($state->getTimeOuts() as $timeout) {
                $timeout->register($context);
            }

            // Update Journal - if applicable
            if ($this->modelJournal) {
                $journal = $this->modelJournal;
                /** @var StateMachineJournal $journal */
                $journal::nu($context, null);
            }

            // transition completed successfully
            return true;
        } catch (Exception $e) {
            $context->attachException($e);
            throw $e;
        }
    }

    /**
     * @param string $value
     * @return State|null
     * @throws InvalidSchemaException
     * @throws StateMachineNotFoundException
     * @throws StateNotFoundException
     */
    public function getState($value)
    {
        if ($value === null) {
            $value = $this->getInitialStateValue();
        }

        if (isset($this->states[$value])) {
            return $this->states[$value];
        }

        $xml = $this->getXml();
        $stateXml = $xml->xpath('state[@ value="'.$value.'"]');
        if (empty($stateXml)) {
            throw new StateNotFoundException("State with value {$value} not found");
        }

        if (count($stateXml) > 1) {
            throw new InvalidSchemaException("All states in a state machine must be unique. State with value {$value} is not unique.");
        }

        $this->states[$value] = State::fromXml($stateXml[0], $this);
        return $this->states[$value];
    }

    /**
     * @return State[]
     * @throws InvalidSchemaException
     * @throws StateMachineNotFoundException
     * @throws StateNotFoundException
     */
    public function getStates()
    {
        $xml = $this->getXml();
        $stateXml = $xml->xpath('state');
        $rVal = [];
        foreach ($stateXml as $item) {
            $value = (string)$item->attributes()->value;
            $rVal[] = $this->getState($value);
            $this->getState($value);
        }

        return $rVal;
    }
}