<?php
namespace kepco\models;

class BidContent extends \yii\db\ActiveRecord
{
  public static function tableName(){
    return 'bid_content';
  }

  public static function getDb(){
    return \kepco\Module::getInstance()->db;
  }
}
