<?php
namespace kepco\models;

class BidKey extends \i2\models\BidKey
{
  public static function tableName(){
    return 'bid_key';
  }

  public static function getDb(){
    return \kepco\Module::getInstance()->db;
  }
}

