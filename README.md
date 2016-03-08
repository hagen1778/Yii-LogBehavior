# Yii-LogBehavior

This extension helps to monitor model attributes and relations changes. It allows to gather and compare model attributes before and after changes including relations

## Requirements

* Yii 1.1.6 or above
* As Yii Framework this behavior is compatible with all PHP versions above 5.5
* Behavior will only work for ActiveRecord classes that have primary key defined.

## How to install

1. Get the source in one of the following ways:
   * [Download](https://github.com/hagen1778/Yii-LogBehavior) 
   Files are already placed at standard structure:
   * /models/Changeslog.php
   * /extensions/CLogBehavior
   * /migrations/m160307_180300_changeslog
2. Apply migration: ./yiic migrate
3. Add it to the models you want to use it with, by adding it to the `behaviors()` method.

~~~php
<?php
public function behaviors()
{
    return [
       'CLogBehavior' => [
       				'class' => 'application.extensions.CLogBehavior'
       			]
    ];
}
~~~


## How it works
## Changeslog model

    Model Changeslog used to save chages into database. Only changed attributes are logged. Every log message consist of:
* title => action, which create this record. May be Create, Update, Delete etc
* old_message => list of attributes before change
* new_message => list of attributes after change
* ip => ip-adrres of event initiator
* entity_id => id-key of entity, which was changed
* entity_key => class of entity, which was changed
* Date => time, when change was made

    There is also two static functions, which you can use in other parts of applications:
* addToLog => create custom log message, without saving models. I am using it at batch sql-queries, where model objects are not welcome
* getKeyEntities => get distinct list of entities. Im using it to build filter forms
    
    
## CLogBehavior

CLogBehavior is used to store previous state of model object and to make comparison after some change of this object.
Behavior use Changeslog model to store processed data. 
You can easily adjust behavior or model to store something additional. Its can be id-key of owner of changed entity, or additional custom title of each message.
There are some additional options, which you can use:

~~~php
<?php
public function behaviors()
{
    return [
       'CLogBehavior' => [
       				'class' => 'application.extensions.CLogBehavior',
       				'withRelations' => true,
       				'trigger' => function(){return true;}
       			]
    ];
}
~~~
withRelations - allow to parse relations of changed object. Relations are converted to two-dimensional array, all levels deeper than 2d level will be abandoned. 
This options is disabled by default, because sometimes it may take extra time while working with heavy objects or thousands of related objects. Thats why, you have to be careful.

trigger - additional check before behavior running. May contain lambda-function. I am using it to control model changes from CLI. For ecxample:
~~~php
<?php
public function behaviors()
{
    return [
       'CLogBehavior' => [
       				'class' => 'application.extensions.CLogBehavior',
       				'trigger'=>function() {
                                        return php_sapi_name() != "cli";
                                    }
       			]
    ];
}
~~~

Sometimes you need to store some additional attributes, which are not present at model. Try to use function "addToCompare".
For example, i need to display changes in count of domains, which are stored in text-format:
```php
<?php
public function beforeSave()
    {
        if (!$this->isNewRecord) {
            if ($this->domains != $this->oldRecord->domains) {
                $old = $this->oldRecord->domains
                    ? substr_count($this->oldRecord->domains, ',') + 1
                    : 0;
                $new = $this->domains
                    ? substr_count($this->domains, ',') + 1
                    : 0;
                $this->CLogBehavior->addToCompare(
                    'domainsCount', $old, $new
                );
            }
        }

        return parent::beforeSave();
    }
```
Attribute domainsCount will be stored at current object and will be added to Changeslog as well
