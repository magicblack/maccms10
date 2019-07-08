<?php
namespace app\common\behavior;

class Begin
{
    public function run(&$params)
    {
        $module = '';
        $dispatch = request()->dispatch();

        if (isset($dispatch['module'])) {
            $module = $dispatch['module'][0];
        }

        if( $module =='install'){
            return;
        }

        if(defined('ENTRANCE') && ENTRANCE == 'admin') {

            if ($module == '') {
                header('Location: '.url('admin/index/index'));
                exit;
            }

            if ($module != 'admin' ) {
                header('Location: '.url('admin/index/index'));
                exit;
            }
        }

    }
}