<?php
namespace kepco\workers;

class FileWorker extends Worker
{
  public $saveDir;

  public function download($name,$url){
    $savePath=$this->saveDir.'/'.$name;
    return $this->client->request('GET',$url,['sink'=>$savePath]);
  }
}

