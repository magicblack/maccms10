<?php
namespace app\common\extend\email;

class Phpmailer {

    public $name = 'PhpMailer';
    public $ver = '1.0';

    public function submit($to, $title, $body,$config=[])
    {
        if(empty($config)) {
            $config = $GLOBALS['config']['email']['phpmailer'];
            $config['nick'] =  $GLOBALS['config']['email']['nick'];
        }
        $mail = new \phpmailer\src\PHPMailer();
        //$mail->SMTPDebug = 2;
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->CharSet = "UTF-8";
        $mail->Host = $config['host'];
        $mail->SMTPSecure = $config['secure'];
        $mail->Port = $config['port'];
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->setFrom($config['username'] , $config['nick']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $title;
        $mail->Body    = $body;
        unset($config);
        $res = $mail->send();

        if($res===true){
            return ['code'=>1,'msg'=>'发送成功'];
        }
        else{
            return ['code'=>102,'msg'=>'发生错误：'. $mail->ErrorInfo ];
        }
    }
}
