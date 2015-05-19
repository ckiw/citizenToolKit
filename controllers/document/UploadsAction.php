<?php
class UploadsAction extends CAction {
	

	public function run($dir,$collection=null,$input,$rename=false) {
		$upload_dir = 'upload/';
        if(!file_exists ( $upload_dir ))
            mkdir ( $upload_dir );
        
        $upload_dir = 'upload/'.$dir.'/';
        if(!file_exists ( $upload_dir ))
            mkdir ( $upload_dir );

        if( isset( $collection ))
            $dir .= '/'.$collection.'/';
        $upload_dir = 'upload/'.$dir.'/';
        if(!file_exists ( $upload_dir ))
            mkdir ( $upload_dir );
        
        $allowed_ext = array('jpg','jpeg','png','gif',"pdf","xls","xlsx","doc","docx","ppt","pptx");
        
        if(strtolower($_SERVER['REQUEST_METHOD']) != 'post')
        {
            echo json_encode(array('result'=>false,'error'=>'Error! Wrong HTTP method!'));
            exit;
        }
        
        if(array_key_exists($input,$_FILES) && $_FILES[$input]['error'] == 0 )
        {
            foreach ( $_FILES[$input]["name"] as $key => $value ) 
            {
                $ext = pathinfo($_FILES[$input]['name'][$key], PATHINFO_EXTENSION);
                if(!in_array($ext,$allowed_ext))
                {
                    echo json_encode(array('result'=>false,'error'=>'Only '.implode(',',$allowed_ext).' files are allowed!'));
                    exit;
                }   
            
                // Move the uploaded file from the temporary 
                // directory to the uploads folder:
                // we use a unique Id for the iamge name Yii::app()->session["userId"].'.'.$ext
                // renaming file
                $name = ($rename) ? Yii::app()->session["userId"].'.'.$ext : $_FILES[$input]['name'][$key];
                if( isset(Yii::app()->session["userId"]) && $name && move_uploaded_file($_FILES[$input]['tmp_name'][$key], $upload_dir.$name))
                {   
                    echo json_encode(array('result'=>true,
                                            "success"=>true,
                                            'name'=>$name,
                                            'dir'=> $upload_dir,
                                            'size'=>Document::getHumanFileSize ( filesize ( $upload_dir.$name ) ) ));
                    exit;
                }
            }
        }
        
        echo json_encode(array('result'=>false,'error'=>'Something went wrong with your upload!'));
        exit;
    }
}