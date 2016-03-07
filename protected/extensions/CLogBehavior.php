<?php

/**
 * This behavior log models changes into database logtable
 */
class CLogBehavior extends CActiveRecordBehavior
{
    /**
     * @var array Old data of the database record
     */
    protected $oldData = array();

    /**
     * @var bool if relations should be checked
     */
    public $withRelations = false;

    /**
     * @var callable trigger function. If trigger is set and returns true
     * then run behavior
     */
    public $trigger;

    /** @var array virtual or unexisted attributes which need to be logged
     * @see CLogBehavior::addToCompare() */
    public $customAttributes = [];

    public function relationalChanges(&$dataSource)
    {
        if (!$this->withRelations || $this->getProgress()) {
            return;
        }

        foreach ($this->owner->relations() as $name => $relation) {
            $dataSource[$name] = $this->owner->$name;
        }
    }

    public function afterFind($event)
    {
        $cAction = Yii::app()->urlManager->parseUrl(Yii::app()->request);
        $cAction = explode('/', $cAction);
        if ((isset($cAction[1]) && $cAction[1] != 'index')) {
            $this->oldData = $this->getOwner()->attributes;
            $this->relationalChanges($this->oldData);
        }
    }

    /**
     * Purge array from unnecessary objects (trying to get primary keys instead)
     * remove oldAttributes object also
     * @param $arr - array()
     */
    protected function clearAttrs($arr)
    {
        if (isset($arr['oldAttributes'])) {
            unset($arr['oldAttributes']);
        }

        foreach ($arr as $key => $values) {
            $arr[$key] = $this->convertData($values);
        }

        return $arr;
    }

    /** Convert data: value/array/object
     *  to array of values
     * @param mixed $data
     * @return array
     */
    private function convertData($data)
    {
        if ($data && $data instanceof CActiveRecord) {
            return $data->getPrimaryKey();
        } elseif (is_array($data)) {
            if (empty($data)) {
                return "";
            }

            $result = [];
            foreach ($data as $k => $v) {
                $result[$k] = $this->convertData($v);
            }

            return $result;
        }

        return $data;
    }

    protected function getOldMsg()
    {
        return $this->clearAttrs($this->oldData);
    }

    /**
     * Get refreshed & purged attributes of model
     * @return array
     */
    protected function getNewMsg()
    {
        /** @var CActiveRecord $model */
        $model = $this->getOwner();
        if (!$model->getIsNewRecord()) {
            $model->refresh();
        }

        $new_message = $model->getAttributes();
        if (!empty($this->customAttributes)) {
            $new_message = array_merge($new_message, $this->customAttributes);
        }

        $this->relationalChanges($new_message);
        return $this->clearAttrs($new_message);
    }

    /**
     *  Compare two associative multidimensional arrays
     * @param array $arr1
     * @param array $arr2
     * @return array
     */
    protected function getDiff($arr1, $arr2)
    {
        $diff = [];

        foreach ($arr1 as $key => $value) {
            if (!array_key_exists($key, $arr2)) {
                $diff[$key] = $value;
            } else {
                if (is_array($value) && is_array($arr2[$key])) {
                    $tmpDiff = array_merge(array_diff_assoc($value, $arr2[$key]),
                        array_diff_assoc($arr2[$key], $value));
                    if (count($tmpDiff) > 0) {
                        $diff[$key] = $tmpDiff;
                    }
                } else {
                    if ($value != $arr2[$key]) {
                        $diff[$key] = $value;
                    }
                }

                unset($arr2[$key]);
            }
        }

        return $diff + $arr2;
    }

    /**
     * Save data to logtable
     * @param $state - state of the record (create, update, delete)
     * @return boolean Result of ChangesLog model saving
     */
    protected function saveLog($state = 'Update')
    {
        if (is_callable($this->trigger) && !call_user_func($this->trigger)) {
            return false;
        }

        if (!($msg = $this->generateMsg($state == 'Delete'))) {
            return false;
        }

        /** @var CActiveRecord $owner */
        $owner = $this->getOwner();

        $log = new ChangesLog;
        $log->old_message = $msg['old'];
        $log->new_message = $msg['new'];

        if (count($this->oldData) == 0) {
            $log->title = 'Create ';
        } else {
            $log->title = $state;
        }

        /**
         * Record primary key
         */
        $log->entity_id = $owner->primaryKey;

        /**
         * Name of the class name where changes made
         */
        $log->entity_key = get_class($owner);
        $log->entity = $owner;

        return $log->save();
    }

    /** Generate difference between before and after change model
     * @param $isDelete boolean action is delete
     * @return array|false If differences was found
     * return array [old=>[], new=>[]]. Return false if there is no difference
     */
    protected function generateMsg($isDelete)
    {
        $old_message = $this->getOldMsg();
        $new_message = $isDelete ? [] : $this->getNewMsg();

        $tmpOldMsg = $tmpNewMsg = [];
        $diff = $this->getDiff($old_message, $new_message);

        foreach ($diff as $key => $value) {
            if ($key == 'password' || $key == 'password_confirmation') {
                continue;
            }

            $tmpOldMsg[$key] = array_key_exists($key, $old_message) ? self::limitString($old_message[$key]) : "";
            $tmpNewMsg[$key] = array_key_exists($key, $new_message) ? self::limitString($new_message[$key]) : "";
        }

        if (empty($tmpOldMsg) && empty($tmpNewMsg)) {
            return false;
        }

        return ['old' => json_encode($tmpOldMsg), 'new' => json_encode($tmpNewMsg)];
    }

    public static function limitString($string, $limit = 4000)
    {
        if (is_string($string) && strlen($string) > $limit) {
            $string = substr($string, 0, $limit / 2) . ' <br>  ...  <br> ' . substr($string, -$limit / 2);
        }

        return $string;
    }

    public static function purify($text)
    {
        $text = strip_tags($text, "<style>");
        $substring = substr($text, strpos($text, "<style"), strpos($text, "</style>"));
        $text = str_replace($substring, "", $text);
        $text = str_replace(array("\t", "\r", "\n"), "", $text);
        return trim($text);
    }

    /**
     * This method write model changes to logModel after record Saved in database
     */
    public function afterSave($event)
    {
        parent::afterSave($event);
        $this->saveLog();
    }

    /**
     * This method write model changes to logModel after record Saved in database
     */
    public function afterDelete($event)
    {
        $this->saveLog('Delete');
    }

    /**
     * Method helps to add virtual, unexisted attributes to logs
     * @param string $name of attribute
     * @param mixed $oldVal old value of attribute
     * @param mixed $newVal new value of attribute
     * @throws CException
     */
    public function addToCompare($name, $oldVal, $newVal)
    {
        if (!is_string($name)) {
            throw new CException("Custom attribute name is not a string \n");
        }

        if ($oldVal) {
            $this->oldData[$name] = $oldVal;
        }

        if ($newVal) {
            $this->customAttributes[$name] = $newVal;
        }
    }
}