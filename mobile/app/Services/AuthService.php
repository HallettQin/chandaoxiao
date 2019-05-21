<?php

namespace App\Services;

use App\Models\User;
use App\Extensions\Wxapp;
use App\Api\Foundation\Token;
use App\Repositories\User\UserRepository;
use App\Repositories\Wechat\WechatUserRepository;
use App\Repositories\Wechat\WxappConfigRepository;

class AuthService
{
    private $request;
    private $userRepository;
    private $WechatUserRepository;
    private $WxappConfigRepository;

    /**
     * AuthService constructor.
     * @param UserRepository $userRepository
     * @param  WechatUserRepository $WechatUserRepository
     */
    public function __construct(UserRepository $userRepository, WechatUserRepository $WechatUserRepository, WxappConfigRepository $WxappConfigRepository)
    {
        $this->userRepository = $userRepository;
        $this->WechatUserRepository = $WechatUserRepository;
        $this->WxappConfigRepository = $WxappConfigRepository;
    }

    /**
     * 登录认证
     * @param $request
     * @return
     */
    public function loginMiddleWare(array $request)
    {
        $this->request = $request['userinfo'];

        $result = $this->wxLogin($request['code']);

        if (isset($result['token']) && isset($result['unionid'])) {
            return $result;
        }
        return false;
    }

    /**
     * 用户登录/注册
     * @return
     */
    private function wxLogin($code)
    {
        $request = $this->request;

        $config = [
            'appid' => $this->WxappConfigRepository->getWxappConfigByCode('wx_appid'),
            'secret' => $this->WxappConfigRepository->getWxappConfigByCode('wx_appsecret'),
        ];
        $wxapp = new Wxapp($config);

        $response = $wxapp->getOauthOrization($code);

        /*
         * [session_key] => 2/Rr1liKpt3IZR6RNsHkBQ==
         * [expires_in] => 7200
         * [openid] => odewX0YjbGuyHx7dQsfi8Q3ZkJL0
         * [unionid] => "UNIONID"
         */
        /** 以上为获取微信信息 */

        if (!isset($response['unionid'])) {
            // code 失效
            if ($wxapp->errCode == '40029') {
                $wxapp->log($wxapp->errMsg);
            }
            return false;
        }

        // 获取到 unionid 判断登录或注册
        $connectUser = $this->userRepository->getConnectUser($response['unionid']);

        // 组合数据
        $args['unionid'] = $response['unionid'];
        $args['openid'] = $response['openid'];
        $args['nickname'] = isset($request['nickName']) ? $request['nickName'] : '';
        $args['sex'] = isset($request['gender']) ? $request['gender'] : '';
        $args['province'] = isset($request['province']) ? $request['province'] : '';
        $args['city'] = isset($request['city']) ? $request['city'] : '';
        $args['country'] = isset($request['country']) ? $request['country'] : '';
        $args['headimgurl'] = isset($request['avatarUrl']) ? $request['avatarUrl'] : '';

        if (empty($connectUser)) {
            $result = $this->createUser($args);
            if ($result['error_code'] == 0) {
                $args['user_id'] = $result['user_id'];
                if ($args['user_id'] && $args['unionid']) {
                    $this->creatConnectUser($args);
                    $this->creatWechatUser($args);
                }
            }
        }

        $args['user_id'] = !empty($args['user_id']) ? $args['user_id'] : $connectUser['user_id'];

        $this->updateUser($args);
        $this->connectUserUpdate($args);
        $this->wechatUserUpdate($args);

        $token = Token::encode(['uid' => $args['user_id']]);

        return ['token' => $token, 'openid' => $args['openid'], 'unionid' => $args['unionid']];
    }

    /**
     * 注册用户users
     * @param $args
     * @return
     */
    public function createUser($args)
    {
        // 注册会员
        $username = 'wx' . substr(md5($args['unionid']), -5) . substr(time(), 0, 4) . mt_rand(1000, 9999);
        $newUser = [
            'user_name' => $username,
            'password' => $this->generatePassword(mt_rand(100000, 999999)),
            'email' => $username . '@qq.com',
        ];
        $extends = [
            'nick_name' => $args['nickname'],
            'sex' => $args['sex'],
            'user_picture' => $args['headimgurl'],
            'reg_time' => gmtime(),
        ];
        if (!User::where(['user_name' => $username])->first()) {
            $model = new User();
            $data = array_merge($newUser, $extends);
            $model->fill($data);
            if ($model->save()) {
                $token = Token::encode(['uid' => $model->user_id]);
                return ['error_code' => 0, 'token' => $token, 'user_id' => $model->user_id];
            } else {
                return ['error_code' => 1, 'msg' => '创建用户失败'];
            }
        } else {
            return ['error_code' => 1, 'msg' => '用户已存在'];
        }
    }

    /**
     * 更新用户信息users
     * @param $args
     * @return
     */
    public function updateUser($args)
    {
        // 组合数据
        $data = [
            'user_id' => $args['user_id'],
            'nick_name' => $args['nickname'],
            'sex' => $args['sex'],
            'user_picture' => $args['headimgurl'],
        ];

        $res = $this->userRepository->renewUser($data);

        return $res;
    }

    /**
     * 新增社会化登录用户信息
     * @param $args
     * @return
     */
    public function creatConnectUser($args, $type = 'wechat')
    {
        // 组合数据
        $profile = [
            'nickname' => $args['nickname'],
            'sex' => $args['sex'],
            'province' => $args['province'],
            'city' => $args['city'],
            'country' => $args['country'],
            'headimgurl' => $args['headimgurl'],
        ];
        $data = [
            'connect_code' => 'sns_' . $type,
            'user_id' => $args['user_id'],
            'open_id' => $args['unionid'],
            'profile' => serialize($profile),
            'create_at' => gmtime()
        ];

        $res = $this->userRepository->addConnectUser($data);

        return $res;
    }

    /**
     * 更新社会化登录用户信息
     * @param $args
     * @return
     */
    public function connectUserUpdate($args, $type = 'wechat')
    {
        // 组合数据
        $profile = [
            'nickname' => $args['nickname'],
            'sex' => $args['sex'],
            'province' => $args['province'],
            'city' => $args['city'],
            'country' => $args['country'],
            'headimgurl' => $args['headimgurl'],
        ];
        $data = [
            'connect_code' => 'sns_' . $type,
            'user_id' => $args['user_id'],
            'open_id' => $args['unionid'],
            'profile' => serialize($profile),
        ];

        $res = $this->userRepository->updateConnnectUser($data);

        return $res;
    }

    /**
     * 新增微信用户信息
     * @return
     */
    public function creatWechatUser($args)
    {
        $data = [
            'nickname' => $args['nickname'],
            'sex' => $args['sex'],
            'city' => $args['city'],
            'country' => isset($args['country']) ? $args['country'] : '',
            'province' => $args['province'],
            'language' => isset($args['language']) ? $args['language'] : '',
            'headimgurl' => $args['headimgurl'],
            'remark' => isset($args['remark']) ? $args['remark'] : '',
            'openid' => $args['openid'],
            'unionid' => $args['unionid'],
            'ect_uid' => $args['user_id']
        ];

        $res = $this->WechatUserRepository->addWechatUser($data);

        return $res;
    }

    /**
     * 更新微信用户信息
     * @param  $args
     * @return
     */
    public function wechatUserUpdate($args)
    {
        $data = [
            'nickname' => $args['nickname'],
            'sex' => $args['sex'],
            'city' => $args['city'],
            'country' => isset($args['country']) ? $args['country'] : '',
            'province' => $args['province'],
            'language' => isset($args['language']) ? $args['language'] : '',
            'headimgurl' => $args['headimgurl'],
            'remark' => isset($args['remark']) ? $args['remark'] : '',
            'openid' => $args['openid'],
            'unionid' => $args['unionid']
        ];

        $res = $this->WechatUserRepository->updateWechatUser($data);

        return $res;
    }

    /**
     * 生成密码
     * @param $password
     * @param bool $salt
     * @return string
     */
    public function generatePassword($password, $salt = false)
    {
        if ($salt) {
            return md5(md5($password) . $salt);
        }
        return md5($password);
    }

    /**
     * 生成用户ID
     * @return array
     */
    public function authorization()
    {
        $token = $_SERVER[strtoupper('HTTP_X_' . app('config')->get('app.name') . '_Authorization')];

        if (empty($token)) {
            return ['error' => 1, 'msg' => strtolower('header parameter `x-' . app('config')->get('app.name') . '-authorization` is required')];
        }
        if ($payload = Token::decode($token)) {
            if (is_object($payload) && property_exists($payload, 'uid')) {
                return $payload->uid;
            }
        }
        if ($payload == 10002) {
            return ['error' => 1, 'msg' => 'token-expired'];
        }
        return ['error' => 1, 'msg' => 'token-illegal'];
    }
}
