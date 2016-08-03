<?php
namespace kepco\models;

class BidKey extends \yii\db\ActiveRecord
{
  public static function tableName(){
    return 'bid_key';
  }

  public static function getDb(){
    return \kepco\Module::getInstance()->db;
  }
}

