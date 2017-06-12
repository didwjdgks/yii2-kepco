<?php
namespace kepco\models;

class Time_Test extends \yii\db\ActiveRecord
{
  public static function tableName(){
    return 'time_test';
  }

  public static function getDb(){
    return \kepco\Module::getInstance()->db;
  }
}

