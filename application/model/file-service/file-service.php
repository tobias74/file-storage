<?php 

class FileService
{
  
  public function __construct()
  {
    $this->mongoConnection = new \MongoClient();
    
    
    //$this->collection->ensureIndex(array('serialized_specification' => 1));
        
  }

  public function setProfiler($profiler)
  {
    if (!is_object($profiler))
    {
      throw new \ErrorException('profiler is not an object?'.$profiler);
    }
    $this->profiler = $profiler;
  }

  protected function getUniqueId()
  {
      $uid=uniqid();
      $uid.=rand(100000,999999);
      return $uid;
  }


  public function storeFile($uploadStruct,$applicationId)
  {
    $uniqueId = $this->getUniqueId();

    
    $this->storeGridFile($uploadStruct,$applicationId,$uniqueId);


    $this->storeInFileSystem($uploadStruct,$applicationId,$uniqueId);
    
    
    return $uniqueId;
    
  }



//FileSystem

  protected function getPathById($idString)
  {
    $path = '';
    $stringParts = str_split($idString,1);
    foreach ($stringParts as $part)
    {
      $path = $path.$part.'/';
    }
    return $path;
  }

  protected function getFileFolder($applicationId,$fileId)
  {
    $basePath = '/var/webdata/filestorage';
    
    return $basePath.'/'.$applicationId.'/'.$this->getPathById($fileId);
  }
  
  protected function getFileName($path,$fileId)
  {
    return $path.$fileId.'_filestorage.bin';
  }
  
  public function storeInFileSystem($uploadStruct,$applicationId,$fileId)
  {
    $sourceFileName = $uploadStruct['tmp_name'];
    
    
    $myPath = $this->getFileFolder($applicationId,$fileId);
    
    if(!is_dir($myPath))
    {
      mkdir($myPath, 0777, true);
    }
    
    $myFileName = $this->getFileName($myPath,$fileId);


    if (file_exists($myFileName))
    {
      throw new ErrorException("we did not get a unique filename. thats not so good.");
    }
    
    if (!copy($sourceFileName, $myFileName))
    {
      throw new ErrorException("we could not move the file.");
    }
    
  }

  public function getBinaryStringFromFileSystem($fileId, $applicationId)
  {
    $myPath = $this->getFileFolder($applicationId,$fileId);
    $myFileName = $this->getFileName($myPath,$fileId);
    
    if (!file_exists($myFileName))
    {
      throw new ZeitfadenException('could not find the file wanted in fileservice: '.$myFileName); 
    }
    
    return file_get_contents($myFileName);

  }




// MongoDB

  public function storeGridFile($uploadStruct,$applicationId,$uniqueId)
  {
    $fileName = $uploadStruct['tmp_name'];
    $fileType = $uploadStruct['type'];
    $uploadFileName = $uploadStruct['name'];
    $fileSize = $uploadStruct['size'];
    
    $name = 'file_storage_'.$applicationId;
    $this->mongoDb = $this->mongoConnection->$name;
    $gridFS = $this->mongoDb->getGridFS();
    
    $hash = array();
    $hash['file_id'] = $uniqueId;
    $hash['application_id'] = $applicationId;
    $hash['size'] = $fileSize;
    $hash['type'] = $fileType;
    $hash['original_name'] = $uploadFileName;
    $gridFS->storeFile($fileName,array("metadata" => $hash));
    
  }

  public function getGridFile($fileId, $applicationId)
  {
    $name = 'file_storage_'.$applicationId;
    $this->mongoDb = $this->mongoConnection->$name;

    $gridFS = $this->mongoDb->getGridFS();
    $fileDocument = $gridFS->findOne(array('metadata.file_id' => $fileId, 'metadata.application_id' => $applicationId));
    
    if (!$fileDocument)
    {
      throw new \Exception('not found'); 
    }
    
    return $fileDocument;
  }     


  public function deleteGridFile($fileId, $applicationId)
  {
    $name = 'file_storage_'.$applicationId;
    $this->mongoDb = $this->mongoConnection->$name;

    $gridFS = $this->mongoDb->getGridFS();
    $status = $gridFS->remove(array('metadata.file_id' => $fileId, 'metadata.application_id' => $applicationId));
    
    if (!$status)
    {
      throw new \Exception('delete failed'); 
    }
    
  }     
  
  
}






