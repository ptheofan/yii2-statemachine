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
    public $modelJournal = 'common\\models\\sql\\SmJournal';

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
     * Enable or Disable the internal use of transactions. This is handy when you work with databases
     * that don't support transactions (ie. MongoDB).
     * @var bool
     */
    public $useTransactions = true;

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
    public $commandsNamespace = '\\app\\sm\\commands';

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
            } elseif (StringHelper::startsWith($schemaSource, '<?xml')) {
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
        /** @var yii\db\Transaction|false $txn */
        $txn = $this->useTransactions ? $context->getModel()->getDb()->beginTransaction() : false;

        try {
            // Leaving current state...
            foreach ($event->getState()->getExitCommands() as $command) {
                if (!$command->execute($context)) {
                    $txn && $txn->rollBack();
                    return false;
                }
            }

            // Cleanup old timeouts
            Timeout::cleanUp($context);
            
            // Entering new state...
            $context->getModel()->{$context->getAttr()} = $event->getTarget();
            foreach ($event->getTargetState()->getEnterCommands() as $command) {
                if (!$command->execute($context)) {
                    $txn && $txn->rollBack();
                    return false;
                }
            }

            // Register the new timeouts
            foreach ($event->getTargetState()->getTimeOuts() as $timeout) {
                $timeout->register($context);
            }

            // Persist the context's model data
            $context->getModel()->save();

            // Update Journal - if applicable
            if ($this->modelJournal) {
                $journal = $this->modelJournal;
                /** @var StateMachineJournal $journal */
                $journal::nu($context, $event);
            }

            $txn && $txn->commit();

            // transition completed successfully
            return true;
        } catch (Exception $e) {
            $txn && $txn->rollBack();
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
        /** @var yii\db\Transaction|false $txn */
        $txn = $this->useTransactions ? $context->getModel()->getDb()->beginTransaction() : false;
        try {
            // Entering state...
            $context->getModel()->{$context->getAttr()} = $this->getInitialStateValue();
            $state = $this->getState($this->getInitialStateValue());
            foreach ($state->getEnterCommands() as $command) {
                if (!$command->execute($context)) {
                    $txn && $txn->rollBack();
                    return false;
                }
            }

            // Register the new timeouts
            foreach ($state->getTimeOuts() as $timeout) {
                $timeout->register($context);
            }

            // Persist the context's model data
            $context->getModel()->save();

            // Update Journal - if applicable
            if ($this->modelJournal) {
                $journal = $this->modelJournal;
                /** @var StateMachineJournal $journal */
                $journal::nu($context, null);
            }

            $txn && $txn->commit();

            // transition completed successfully
            return true;
        } catch (Exception $e) {
            $txn && $txn->rollBack();
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
            return null;
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
        foreach ($stateXml as $item) {
            $value = (string)$item->attributes()->value;
            $this->getState($value);
        }

        return $this->states;
    }

    /**
     * @param array $profile - appearance profile
     * @return string
     */
    public function dot($profile)
    {
        $graph = new Digraph('G');
        $graph->set('truecolor', true);

        foreach ($this->getStates() as $state) {
            $graph->node($state->getValue(), $state->dot($profile['states']));
        }

        foreach ($this->getStates() as $state) {
            /** @var Event[] $connectors */
            $connectors = array_merge($state->getEvents(), $state->getTimeOuts());
            foreach ($connectors as $event) {
                $p = $profile['events']['default'];

                foreach ($profile['events']['exclusiveRoles'] as $exclusiveRole => $roleProfile) {
                    if ($event->isExclusiveTo($exclusiveRole)) {
                        $p = array_merge($p, $roleProfile);
                        break;
                    }
                }

                if ($event instanceof Timeout) {
                    $p = array_merge($p, $profile['events']['timeout']);
                } elseif ($event->isRefresh()) {
                    $p = array_merge($p, $profile['events']['refresh']);
                }

                $fontcolor = $p['fontcolor'];
                $fillcolor = $p['fillcolor'];
                $color = $p['color'];
                $style = $p['style'];

                $graph->edge([$state->getValue(), $event->getTarget()], [
                    'label' => chr(1) . '<<table border="0" cellspacing="0" cellpadding="2"><tr><td>' . $event->getLabel() . '</td></tr></table>>',
                    'margin' => 10,
                    'arrowsize' => 0.6,
                    'fontsize' => 9,
                    'fontname' => 'Helvetica Neue',
                    'fontcolor' => $fontcolor,
                    'fillcolor' => $fillcolor,
                    'color' => $color,
                    'style' => $style,
                ]);
            }
        }

        return $graph->render();
    }
}