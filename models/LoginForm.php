<?php
namespace kepco\models;

class LoginForm extends \yii\base\Model
{
  public $cookie;
  public $token;

  public function rules(){
    return [
      [['cookie','token'],'required'],
    ];
  }

  public function login(){
    if($this->validate()){
      return true;
    }
    return false;
  }
}

