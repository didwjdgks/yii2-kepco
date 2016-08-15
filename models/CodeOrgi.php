<?php
namespace kepco\models;

class CodeOrgi extends \yii\db\ActiveRecord
{
  public static function tableName(){
    return 'code_org_i';
  }

  public static function getDb(){
    return \kepco\Module::getInstance()->db;
  }
}

