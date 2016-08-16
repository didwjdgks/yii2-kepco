<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

$this->title='한국전력 로봇 로그인';
?>
<div class="site-login">
  <h1><?=Html::encode($this->title)?></h1>
  <div class="row">
    <div class="col-lg-5">
      <?php $form=ActiveForm::begin(['id'=>'login-form']); ?>
        <?= $form->field($model,'cookie')->textInput(['autofocus'=>true]) ?>
        <?= $form->field($model,'token')->textInput() ?>

        <?= Html::submitButton('Login',['class'=>'btn btn-primary','name'=>'login-button']) ?>
      <?php ActiveForm::end(); ?>
    </div>
  </div>
</div>


