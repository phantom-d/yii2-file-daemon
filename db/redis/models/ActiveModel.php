<?php

namespace phantomd\filedaemon\db\redis\models;

use Yii;
use yii\base\NotSupportedException;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/**
 * Class ActiveModel
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class ActiveModel extends \yii\db\BaseActiveRecord implements \phantomd\filedaemon\db\ActiveInterface
{

    use \phantomd\filedaemon\traits\DbEventsTrait;

    /**
     * @var string Data type
     */
    protected static $type = '';

    /**
     * @var array Errors
     */
    protected static $errors = [];

    /**
     * @var string New name
     */
    protected $tableRename = null;

    /**
     * @var string Current name
     */
    protected $tableName = null;

    /**
     * Initializes the object.
     */
    public function init()
    {
        parent::init();
        $this->tableName = $this->tableName();
    }

    /**
     * Returns the validation rules for attributes.
     */
    public function rules()
    {
        return [];
    }

    /**
     * Returns the list of attribute names.
     */
    public function attributes()
    {
        return [];
    }

    /**
     * Returns the attribute labels.
     */
    public function attributeLabels()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDb()
    {
        return \Yii::$app->get('filedaemon_' . Inflector::camel2id(StringHelper::basename(get_called_class()), '_'));
    }

    /**
     * @inheritdoc
     */
    public static function model($params = [])
    {
        $tableName = static::tableName();

        if ('zset' === static::$type) {
            if (is_array($params)) {
                if (isset($params['name'])) {
                    $tableName = (string)$params['name'];
                    unset($params['name']);
                }
            } else {
                $tableName = (string)$params;
                $params    = [];
            }
        }
        $params['class'] = static::className();

        $model = \Yii::createObject($params);

        $model->tableName = $tableName;

        return $model;
    }

    /**
     * Declares the name of the database table associated with this AR class.
     */
    public static function tableName()
    {
        return Inflector::camel2id(StringHelper::basename(get_called_class()), '_');
    }

    /**
     * Get current name
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * @inheritdoc
     */
    public static function count($params = [])
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public static function one($params = [], $remove = false)
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public static function all($params = [], $limit = 10, $page = 0)
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public static function names($params = [])
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public static function groups($params = [])
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function rename($params = [])
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function remove()
    {
        throw new NotSupportedException();
    }

    /**
     * Saves the current record.
     */
    public function save($runValidation = true, $attributeNames = null)
    {
        return parent::save($runValidation, $attributeNames);
    }

    /**
     * Inserts a row into the associated database table using the attribute values of this record.
     */
    public function insert($runValidation = true, $attributeNames = null)
    {
        return parent::insert($runValidation, $attributeNames);
    }

    /**
     * Saves the changes to this active record into the associated database table.
     */
    public function update($runValidation = true, $attributeNames = null)
    {
        return parent::update($runValidation, $attributeNames);
    }

    /**
     * Deletes the table row corresponding to this active record.
     */
    public function delete()
    {
        return parent::delete();
    }

    /**
     * Creates an ActiveQuery instance for query purpose.
     */
    public static function find()
    {
        return Yii::createObject(static::className());
    }

    /**
     * Returns the primary key **name(s)** for this AR class.
     */
    public static function primaryKey()
    {
        return parent::primaryKey();
    }

    /**
     * Check exist key in RedisDB
     *
     * @return boolean
     * @throws InvalidParamException
     */
    protected function checkTable()
    {
        $table = $this->tableName();

        if ('' === $table) {
            $message = \Yii::t('app', "Parameter 'table' cannot be blank.");

            static::$errors['table'] = $message;
            \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        $db    = static::getDb();
        $query = [$table];

        if (false === $db->executeCommand('exists', $query)) {
            $message = \Yii::t('app', "Table not exists: {table}!", ['table' => $table]);

            static::$errors['table'] = $message;
            \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        $type = $db->executeCommand('type', $query);

        if ($type && 'none' !== $type && static::$type !== $type) {
            $message = \Yii::t('app', "Incorrect type of table '{table}'! Must be sorted sets!", ['table' => $table]);

            static::$errors['table'] = $message;
            \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        return true;
    }

}
