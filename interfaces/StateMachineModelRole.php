<?php
/**
 * User: Paris Theofanidis
 * Date: 01/07/16
 * Time: 13:08
 */
namespace ptheofan\statemachine\interfaces;

use yii\db\BaseActiveRecord;

interface StateMachineModelRole
{
    /**
     * @param User $user
     * @return string
     */
    public function getUserRole(BaseActiveRecord $user);
}