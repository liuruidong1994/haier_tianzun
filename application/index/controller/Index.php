<?php
namespace app\index\controller;
use think\Controller;
use UpYun;
use EasyWeChat\Foundation\Application;
use think\Db;

class Index extends Controller
{


    /*
     * array(7) {
  ["id"] => string(28) "oFZwDt4CRPKHbDUX-FETZGGm5QDY"
  ["name"] => string(6) "Reader"
  ["nickname"] => string(6) "Reader"
  ["avatar"] => string(118) "http://wx.qlogo.cn/mmopen/PiajxSqBRaEJj5SaE2vj4FZP486EdXw8GX4qDRribws3dE2DeYHIX2w0iaLNyOrxLHeW0frTkcia5vKfBpeSSFk8MQ/0"
  ["email"] => NULL
  ["original"] => array(9) {
    ["openid"] => string(28) "oFZwDt4CRPKHbDUX-FETZGGm5QDY"
    ["nickname"] => string(6) "Reader"
    ["sex"] => int(1)
    ["language"] => string(5) "zh_CN"
    ["city"] => string(6) "青岛"
    ["province"] => string(6) "山东"
    ["country"] => string(6) "中国"
    ["headimgurl"] => string(118) "http://wx.qlogo.cn/mmopen/PiajxSqBRaEJj5SaE2vj4FZP486EdXw8GX4qDRribws3dE2DeYHIX2w0iaLNyOrxLHeW0frTkcia5vKfBpeSSFk8MQ/0"
    ["privilege"] => array(0) {
    }
  }
  ["token"] => object(Overtrue\Socialite\AccessToken)#83 (1) {
    ["attributes":protected] => array(5) {
      ["access_token"] => string(107) "Bb6GObHeSM92OHu6cXVWbvUj-41V1CKl-cuHRrHCRoSXgFnuOsUEF5hcEDxpkQ_tMmQSdXR5l-o3ZzpxV7V2DOksvwHCpYCBb_pj5jWULVw"
      ["expires_in"] => int(7200)
      ["refresh_token"] => string(107) "NIOQGTeOkGm9CBPjVc4T8WIMDXoIS2gUiq4L2dZE7EhKB1tI0hPgZIC7RVBoC4RrvGGKsSNa7zNugbFENpCYtwnwYHnS3oxORpb3emzvlbU"
      ["openid"] => string(28) "oFZwDt4CRPKHbDUX-FETZGGm5QDY"
      ["scope"] => string(15) "snsapi_userinfo"
    }
  }
}
     *
     * */
    private function getWechatConfig(){
        $options = [
            'debug'  => true,
            'app_id' => 'wx55d06e53fc61d8f5',
            'secret' => 'bf430aad5a50a2e50d3ae2f9fa9809a4',
            'token'  => 'easywechat',
            // 'aes_key' => null, // 可选
            'log' => [
                'level' => 'debug',
                'file'  => '/tmp/easywechat.log', // XXX: 绝对路径！！！！
            ],
            'oauth' => [
                'scopes'   => ['snsapi_userinfo'],
                'callback' => url('index/index/callback'),
            ],
            //...
        ];
        $app = new Application($options);
        return $app;
    }

    //上传图片页面，主页
    public function index()
    {
        $app=$this->getWechatConfig();
        $oauth = $app->oauth;
        // 未登录
        $userInfo=session('wechat_user');
        if (empty($userInfo)) {
            session('target_url',url('index/index/index'));
            $oauth->redirect()->send();
            // 这里不一定是return，如果你的框架action不是返回内容的话你就得使用
            // $oauth->redirect()->send();
        }
        // 已经登录过
        $projectInfo=$this->getUserInfo($userInfo['id']);
        //已经上传过
        if(!empty($projectInfo)){
            $this->redirect('detail');
        }
        //未上传

        //是否需要播放动画,默认需要，不播放则直接至上传图片页
        $isAnimVisible=input('get.is_anim_visible',1);
        $this->assign('userInfo',$userInfo);
        $this->assign('isAnimVisible',$isAnimVisible);
        $this->assign('isDetail',false);
        $this->assign('isMine',false);
        $this->assign('projectInfo',[]);
        $this->assign('rankingList',[]);
        return $this->fetch();
    }

    //详情页
    public function detail()
    {
        $app=$this->getWechatConfig();
        $oauth = $app->oauth;

        $userInfo=session('wechat_user');
        $id=input('get.id');

        // 未登录
        if (empty($userInfo)) {
            session('target_url',url('index/index/detail',['id'=>$id]));
            $oauth->redirect()->send();
            //return $oauth->redirect();
            // 这里不一定是return，如果你的框架action不是返回内容的话你就得使用
            // $oauth->redirect()->send();
        }
        // 已经登录过
        if(!empty($id)){
            //查看别人的
            $projectInfo=$this->getUserInfo($id);
            $this->assign('isMine',false);
        }else{
            //查看自己的
            $projectInfo=Db::name('wechat_user')->where('open_id',$userInfo['id'])->find();
            $this->assign('isMine',true);
        }
        //排行榜
        $rankingList=Db::name('wechat_user')
            ->order('zan_num desc')
            ->limit(5)
            ->select();

        //dump($userInfo);
        //Sdump($projectInfo);
        $isAnimVisible=input('get.is_anim_visible',0);
        $this->assign('userInfo',$userInfo);
        $this->assign('projectInfo',$projectInfo);
        $this->assign('isDetail',true);
        $this->assign('rankingList',$rankingList);
        $this->assign('isAnimVisible',$isAnimVisible);

        return $this->fetch('index');
    }

    public function zan()
    {
        $userInfo=session('wechat_user');
        //未登录
        if (empty($userInfo)) {
            //$this->redirect('index');
            return json(['code'=>-1,'msg'=>'未登录']);
        }
        $id=input('id');
        if(empty($id)){
            return json(['code'=>-2,'msg'=>'参数错误']);
        }
        //@todo rule of zan
        $zan_recode=Db::name('zan_record')
            ->where('user_open_id',$userInfo['id'])
            ->where('project_id',$id)
            ->select();
        if(empty($zan_recode)){
            $insert=[
                'user_open_id'=>$userInfo['id'],
                'project_id'=>$id,
                'create_date'=>date('Y-m-d H:i:s'),
                'create_time'=>time()
            ];
            Db::name('zan_record')->insert($insert);
            Db::name('wechat_user')->where('id',$id)->setInc('zan_num');
            return json(['code'=>200,'msg'=>'ok']);
        }else{
            return json(['code'=>-3,'msg'=>'已经赞过了']);
        }



    }

    public function upload()
    {
        $userInfo=session('wechat_user');
        //未登录
        if (empty($userInfo)) {
            //$this->redirect('index');
            return json(['code'=>-1,'msg'=>'未登录']);
        }
        //是否已上传过
        if($this->getUserInfo($userInfo['id'])){
            //$this->redirect('detail');
            return json(['code'=>-2,'msg'=>'已上传过']);
        }

        $imgBase64=input('imgBase64');
        $filePath=ROOT_PATH . 'public' . DS . 'uploads'.DS.$userInfo['id'].'.png';
        if(!empty($imgBase64)){
            $tmp=explode(',',$imgBase64);
            file_put_contents($filePath, base64_decode($tmp[1]));
            //上传到upyun
            $res=$this->uploadImg($filePath,$userInfo['id']);
            if($res===false){
                //$this->error('图片上传失败');
                return json(['code'=>-2,'msg'=>'图片上传失败']);
            }else{
                //insert into database
                $insert=[
                    'open_id'=>$userInfo['id'],
                    'nickname'=>$userInfo['nickname'],
                    'head_img'=>$userInfo['avatar'],
                    'img_url'=>$res,
                    'zan_num'=>0,
                    'create_date'=>date('Y-m-d H:i:s'),
                    'create_time'=>time()
                ];
                $res=Db::name('wechat_user')->insert($insert);
                if($res){
                    return json(['code'=>200,'msg'=>'ok']);
                }else{
                    return json(['code'=>-3,'msg'=>'error']);
                }
            }

        }

       /* // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('image');
        // 移动到框架应用根目录/public/uploads/ 目录下
        $info = $file->validate(['ext'=>'jpg,png'])->move(ROOT_PATH . 'public' . DS . 'uploads');
        if($info){
            // 成功上传后 获取上传信息
            // 输出 jpg
            //echo $info->getExtension();
            // 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
            //echo $info->getSaveName();
            // 输出 42a79759f284b767dfcb2a0197904287.jpg
            //echo $info->getFilename();

            $filePath=ROOT_PATH . 'public' . DS . 'uploads'.DS.$info->getSaveName();
            //上传到upyun
            $res=$this->uploadImg($filePath,$userInfo['id']);
            if($res===false){
                $this->error('图片上传失败');
            }
            //insert into datebase

            $this->redirect('detail');

        }else{
            // 上传失败获取错误信息
            echo $file->getError();
            $this->error('图片上传失败');
        }*/
    }

    private function getUserInfo($id){
        $info=Db::name('wechat_user')->where('id',$id)->find();
        return $info;
    }

    public function callback(){
        $app=$this->getWechatConfig();
        $oauth = $app->oauth;
        // 获取 OAuth 授权结果用户信息
        $user = $oauth->user();
        session('wechat_user',$user->toArray());
        $targetUrl = empty(session('target_url')) ? url('index/index/index') : session('target_url');
        $this->redirect($targetUrl);
    }

    private function uploadImg($filePath,$openId)
    {
        $upyun = new UpYun('qdredsoft','redsoft','qdredsoft');
        $opts = array(
            //UpYun::X_GMKERL_THUMBNAIL => 'square', //创建缩略图
            'x-gmkerl-thumb' => '/format/png'
        );
        $name='/haier_tianzun/productions/'.$openId;
        $fh = fopen($filePath, 'r');
        try {
            $upyun->writeFile($name, $fh, true, $opts);
            fclose($fh);
            return 'http://qdredsoft.b0.upaiyun.com'.$name;

        } catch(Exception $e) {
            fclose($fh);
            echo $e->getCode();		// 错误代码
            echo $e->getMessage();	// 具体错误信息
            return false;
        }
    }

    public function clearSession()
    {
        session('wechat_user',null);
    }
}
