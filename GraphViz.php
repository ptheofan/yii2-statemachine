<?php
/**
 * User: Paris Theofanidis
 * Date: 03/07/16
 * Time: 01:22
 */
namespace ptheofan\statemachine;

use Alom\Graphviz\Digraph;
use ptheofan\statemachine\interfaces\StateMachineState;
use yii\base\BaseObject;

class GraphViz extends BaseObject
{
    /**
     * @var array
     */
    public $profile = [];

    /**
     * @param StateMachineState $state
     * @param $profile
     * @return array
     */
    protected function renderState(StateMachineState $state, $profile)
    {
        $p = $profile['default'];
        if ($state->isInitial()) {
            $p = array_merge($p, $profile['initial']);
        } elseif ($state->isFinal()) {
            $p = array_merge($p, $profile['final']);
        } elseif ($state->isIntermediate()) {
            $p = array_merge($p, $profile['intermediate']);
        }

        $fillcolor = $p['fillcolor'];
        $style = $p['style'];
        $shape = $p['shape'];
        $fontcolor = $p['fontcolor'];
        $tableTypeIndicatorColor = $p['tableTypeIndicatorColor'];
        $tableTypeValueColor = $p['tableTypeValueColor'];

        $onEnter = [];
        foreach ($state->getEnterCommands() as $enterCommand) {
            $onEnter[] = $enterCommand->shortName();
        }

        $onExit = [];
        foreach ($state->getExitCommands() as $exitCommand) {
            $onExit[] = $exitCommand->shortName();
        }

        $stateTpl = '<table cellpadding="0" cellspacing="0" cellborder="0" border="0">';
        $stateTpl .= '<tr><td colspan="2">{stateLabel}</td></tr>';

        if (!empty($onEnter))
            $stateTpl .= '<tr><td valign="top" align="right"><font point-size="8" color="'.$tableTypeIndicatorColor.'">onEnter: </font></td><td align="left"><font point-size="8" color="'.$tableTypeValueColor.'">{onEnter}</font></td></tr>';

        if (!empty($onExit))
            $stateTpl .= '<tr><td valign="top" align="right"><font point-size="8" color="'.$tableTypeIndicatorColor.'">onExit: </font></td><td align="left"><font point-size="8" color="'.$tableTypeValueColor.'">{onExit}</font></td></tr>';

        $stateTpl .= '</table>';

        $label = strtr($stateTpl, [
            '{stateLabel}' => $state->getValue(),
            '{onEnter}' => empty($onEnter) ? '-' : implode('<br align="left"/>', $onEnter) . '<br align="left"/>',
            '{onExit}' => empty($onExit) ? '-' : implode('<br align="left"/>', $onExit) . '<br align="left"/>',
        ]);

        return [
            'label' => chr(1) . '<'.$label.'>',
            'shape' => $shape,
            'margin' => 0.1,
            'fontsize' => 10,
            'fontname' => 'Helvetica Neue',
            'fontcolor' => $fontcolor,
            'fillcolor' => $fillcolor,
            'style' => $style,
        ];
    }

    /**
     * Will return the script required by GraphViz (dot) to render the graph
     *
     * @param StateMachine $sm
     * @return string
     */
    public function render(StateMachine $sm)
    {
        $graph = new Digraph('G');
        $graph->set('truecolor', true);

        foreach ($sm->getStates() as $state) {
            $graph->node($state->getValue(), $this->renderState($state, $this->profile['states']));
        }

        foreach ($sm->getStates() as $state) {
            /** @var Event[] $connectors */
            $connectors = array_merge($state->getEvents(), $state->getTimeOuts());
            foreach ($connectors as $event) {
                $p = $this->profile['events']['default'];

                // We now have conditional and no conditional events.
                // TODO: Represent the conditions of the event on the graph
//                foreach ($this->profile['events']['exclusiveRoles'] as $exclusiveRole => $roleProfile) {
//                    if ($event->isExclusiveTo($exclusiveRole)) {
//                        $p = array_merge($p, $roleProfile);
//                        break;
//                    }
//                }

                if ($event instanceof Timeout) {
                    $p = array_merge($p, $this->profile['events']['timeout']);
                } elseif ($event->isRefresh()) {
                    $p = array_merge($p, $this->profile['events']['refresh']);
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