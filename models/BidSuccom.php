<?php
namespace kepco\models;

class BidSuccom extends \yii\db\ActiveRecord
{
  public static function tableName(){
    return 'bid_succom';
  }

  public static function getDb(){
    return \kepco\Module::getInstance()->db;
  }
}
