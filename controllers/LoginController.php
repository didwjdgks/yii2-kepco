<?php
namespace kepco\controllers;

use kepco\models\LoginForm;
use kepco\Redis;

class LoginController extends \yii\web\Controller
{
  public function actionIndex(){
    $model=new LoginForm;
    if($model->load(\Yii::$app->request->post()) && $model->login()){
      $pub=\Yii::createObject([
        'class'=>Redis::className(),
        'hostname'=>$this->module->redis_server,
      ]);
      $pub->publish('kepco-login',[
        'cookie'=>$model->cookie,
        'token'=>$model->token,
      ]);
      return $this->render('index',['model'=>$model]);
    }else{
      return $this->render('index',['model'=>$model]);
    }
  }
}
