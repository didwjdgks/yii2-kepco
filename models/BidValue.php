<?php
namespace kepco\models;

class BidValue extends \yii\db\ActiveRecord
{
  public static function tableName(){
    return 'bid_value';
  }

  public static function getDb(){
    return \kepco\Module::getInstance()->db;
  }
}
