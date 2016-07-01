# yii2-statemachine
State Machine for modern Web Apps for the Yii2 framework


Install using composer (command line)
```bash
composer require ptheofan/yii2-statemachine
```

## Example
Lets assume we want to wrap our users in a state machine. TODO: SCREENSHOT. We will do so using the example-account.xml graph as provided.

### 1. Create the state machine component configuration.
if you have multiple state machines you have to do this multiple times. One time for each state 
machine as each state machine is its very own Component. So, in your configuration file in the components section add the following.
```php
  'smUserAccountStatus' => [
      'class' => 'ptheofan\statemachine\StateMachine',
      'schemaSource' => '@vendor/ptheofan/yii2-statemachine/example-account.xml',
      'name' => 'account',
  ],
```

### 2. Add the behavior to your model.
Go to your database model (ie. User extends ActiveRecord) and add the behavior. If you have multiple attributes 
controlled by state machines in the same class you have to add the behavior multiple times. One time per controlled attribute.
```php
  /**
   * @return array
   */
  public function behaviors()
  {
      return [
          'status' => [
              'class' => StateMachineBehavior::className(),
              'sm' => Yii::$app->smUserAccountStatus,         // The component name we used in the config section.
              'attr' => '_status',                            // The physical attribute
              'virtAttr' => 'status',                         // The attribute used for control point.
          ],
      ];
  }
```


# 3. Roles Based schema.
In this scenario we are also using roles. Roles are simple strings declared in the xml file for each action (optional). Since most
commonly you will be needing roles, let's add a function in the User model that will inform the state machine of the current user's role.
The name, the function, even when the function is can be adjusted from the StateMachine configuration (config section). See the
StateMachine class for details.
```php
  /**
   * @param User $user
   * @return string
   */
  public function getUserRole($user)
  {
      if (!$user) {
          return self::ROLE_GUEST;
      }

      if ($user->role === User::ROLE_ADMIN) {
          return self::ROLE_ADMIN;
      }

      if ($user->role === User::ROLE_SYSTEM) {
          return self::ROLE_SYSTEM;
      }

      if ($this->hasProperty('created_by') && $this->created_by === $this->created_by) {
          return self::ROLE_OWNER;
      }

      return self::ROLE_USER;
  }
```


#4 Ready to use
Finally the configuration of the state machine is ready! Here's a simple example with some cases

```php
  // Creating a model for the first time - StateMachine will automatically introduce the model to the schema
  $tx = User::getDb()->beginTransaction();
  $user = new User();
  $user->email = 'test@example.com';
  $user->setPassword('123');
  $user->save();
  $tx->commit();
  
  // A very simple way of triggering events based on the state we would like to move the object to. This will
  // work only if there's 1 event that can lead to the desired state.
  $tx = User::getDb()->beginTransaction();
  /** @var User $user */
  $user = User::find()->andWhere(['email' => 'test@example.com'])->one();
  $user->status = 'verified';     // This is the state we want to set
  $tx->commit();
  
  // A more formal way of doing the same - using the Event's label
  $tx = User::getDb()->beginTransaction();
  /** @var User $user */
  $user = User::find()->andWhere(['email' => 'test@example.com'])->one();
  $user->status->trigger('verify');     // This is the label of the event we trigger
  $tx->commit();
```

