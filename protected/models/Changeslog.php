<?php

class ChangesLog extends CActiveRecord
{

    public $changes = [];
    public $entity;

    public static function model($name = __CLASS__)
    {
        return parent::model($name);
    }

    public function tableName()
    {
        return 'changeslog';
    }

    public function rules()
    {
        return array(
            array(
                'title, entity_key, entity_id, user_id',
                'safe',
                'on' => 'search'
            ),
            [
                'ip',
                'default',
                'value' => isset($_SERVER['REMOTE_ADDR']) ? ip2long(
                    $_SERVER['REMOTE_ADDR']
                ) : ip2long('127.0.0.1')
            ],
            ['date', 'default', 'value' => new CDbExpression('NOW()')],
        );
    }

    public function afterFind()
    {
        $this->ip = long2ip($this->ip);

        if (json_encode($this->new_message)
            && json_encode(
                $this->old_message
            )
        ) {
            $this->new_message = json_decode($this->new_message, true);
            $this->old_message = json_decode($this->old_message, true);
        } else {
            $this->new_message = unserialize($this->new_message);
            $this->old_message = unserialize($this->old_message);
        }

        $this->changes = $this->getChanges();

        return parent::afterFind();
    }

    public static function getKeyEntities()
    {
        $sql = "SELECT DISTINCT entity_key FROM `changeslog` ORDER BY entity_key ASC";
        return Yii::app()->db->createCommand($sql)->queryAll();
    }

    /** Create custom log message
     *
     * @param   array $msg associative array, where key is model attribute, and
     *     value - is a value of that attribute
     * @param   CActiveRecord $entity object which was updated
     * @param   string $title title of log message, may be Create, Update or
     *     Delete
     *
     * @return  boolean was log saved or not
     */
    public static function addToLog(
        array $msg, CActiveRecord $entity, $title = 'Create'
    ) {
        $log = new ChangesLog();
        $log->new_message = json_encode($msg);
        $log->entity_key = get_class($entity);
        $log->entity_id = $entity->getPrimaryKey();
        $log->title = $title;
        if ($title != 'Create') {
            $log->old_message = json_encode(
                array_map(
                    function () {
                        return null;
                    }, $msg
                )
            );
        }

        return $log->save();
    }
}