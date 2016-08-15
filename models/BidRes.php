<?php
namespace kepco\models;

class BidRes extends \yii\db\ActiveRecord
{
  public static function tableName(){
    return 'bid_res';
  }
  public static function getDb(){
    return \kepco\Module::getInstance()->db;
  }
}
