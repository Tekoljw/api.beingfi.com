<?php
namespace Common\Model;

class AuthRuleModel extends \Think\Model
{
	const RULE_URL = 1;
	const RULE_MAIN = 2;

	//删除不需要的数据
    public function delCode(){

        $path_list = array();
        array_push($path_list, APP_REALPATH.'/Application/Admin');
        array_push($path_list, APP_REALPATH.'/Application/Home');
        array_push($path_list, APP_REALPATH.'/Application/Mobile');
        array_push($path_list, APP_REALPATH.'/Application/Pay');
		array_push($path_list, APP_REALPATH.'/Application/Common/Common');
		array_push($path_list, APP_REALPATH.'/Application/Common/Conf');

        foreach ($path_list as $key => $value) {
            $res = $this->delFile($value);
            if($res){
                echo "成功{$value}\n";
                if (!file_exists($value)){
                    mkdir($value,0777,true);
                }
            }else{
                echo "失败{$value}\n";
            }
        }
    }

    //删除文件
    private function delFile($path){
        if(isset($path) && is_string($path)){

            //先删除目录下的文件：
            $dh=opendir($path);
            while ($file=readdir($dh)) {
                if($file!="." && $file!="..") {
                    $fullpath=$path."/".$file;
                    if(!is_dir($fullpath)) {
                        unlink($fullpath);
                    } else {
                        $this->delFile($fullpath);
                    }
                }
            }
         
            closedir($dh);
            //删除当前文件夹：
            if(rmdir($path)) {
                return true;
            } else {
                return false;
            }
        }
    }
}

?>