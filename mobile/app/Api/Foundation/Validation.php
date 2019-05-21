<?php

namespace App\Api\Foundation;

/**
 * 验证类
 * Class Validation
 * @package App\Api\Foundation
 */
class Validation
{

    // 数据类型
    private static $dataType = [
        'integer',
        'string'
    ];

    /**
     * 生成验证对象
     * @return \Model|\Think\Model
     */
    public static function createValidation()
    {
        return M();
    }

    /**
     * 转换验证规则
     * @param $pattern
     * @return mixed
     */
    public static function transPattern($pattern)
    {
        if (!is_array($pattern)) {
            die(json_encode(['code'=>1, 'msg'=>'验证格式不正确，以数组组合规则']));
        }
        $rules = [];
        foreach ($pattern as $k => $v) {
            if (!is_string($k) || !is_string($v)) {
                die(json_encode(['code'=>1, 'msg'=>'验证规则格式不正确，规则应为字符串']));
            }
            //
            $rules = array_merge($rules, self::explodePattern($k, $v));
        }

        return $rules;
    }

    /**
     * 拼接验证规则
     * @param $name
     * @param $pattern
     * @return array
     */
    private static function explodePattern($name, $pattern)
    {
        $patterns = explode('|', $pattern);
        array_slice($patterns, 0, 3);

        $rules = []; //规则
        $ruleRequires = self::ruleRequires($patterns);  // 验证条件

        foreach ($patterns as $p) {
            $errorMsg = $name.self::errorMsg($p);   // 错误信息
            $ruleContent = self::generageRule($p);  // 验证规则

            //错误规则
            if (is_array($ruleContent)) {
                if (count($ruleContent) == 2) {
                    $rules[] = [$name, $ruleContent[1], $errorMsg, $ruleRequires, $ruleContent[0]];
                } elseif (count($ruleContent) == 3) {
                    $rules[] = [$name, $ruleContent[0], $errorMsg, $ruleRequires, $ruleContent[1], 3, [$ruleContent[2]]];
                }
            } elseif (is_string($ruleContent)) {
                $rules[] = [$name, $ruleContent, $errorMsg, $ruleRequires];  //错误规则
            }
            //错误规则end
        }
        return $rules;
    }

    /**
     * 生成验证规则
     * @param $pattern
     * @return string
     */
    private static function generageRule($pattern)
    {
        $rule = '';

        if ($pattern == 'required') {
            $rule = 'require';
        } elseif (strpos($pattern, ':')) {
            //判断附加条件
            $rule = self::attachRequires($pattern);
        } elseif (!strstr($pattern, ':') && in_array($pattern, self::$dataType)) {
            //判断类型
            $rule[0] = "App\\Api\\Foundation\\Validation::verifyType";
            $rule[1] = 'function';
            $rule[2] = $pattern;
        }

        return $rule;
    }

    /**
     * 错误语句
     * @param $rule
     * @return string
     */
    private static function errorMsg($rule)
    {
        if ($rule == 'required') {
            $errorMsg = ": is required";
        } elseif (strstr($rule, 'min') || strstr($rule, 'max')) {
            $errorMsg = ": is out of range";
        } else {
            $errorMsg = ": datatype is wrong";
        }

        return $errorMsg;
    }

    /**
     * 验证条件
     * @param $patterns
     * @return int
     */
    private static function ruleRequires($patterns)
    {
        if (in_array('required', $patterns)) {
            return 1;
        }
        return 2;
    }


    /**
     * 附加条件验证
     * @param $pattern
     * @return int
     */
    private static function attachRequires($pattern)
    {
        $patterns = explode(':', $pattern);
        $return = [];

        switch ($patterns[0]) {
            case 'in':
                $return[0] = 'in';
                $return[1] = explode(',', $patterns[1]);
                break;
            case 'min':
                $return[0] = 'min';
                $return[1] = explode(',', $patterns[1]);
                break;
            case 'max':
                $return[0] = 'max';
                $return[1] = explode(',', $patterns[1]);
                break;
        }

        return $return;
    }


    /**
     * 判断类型
     * @param $str
     * @param $type
     * @return bool
     */
    public static function verifyType($str, $type)
    {
        if ($type === 'integer') {
            if ($str === '0') {
                return true;
            }
            $originLen = strlen($str);
            $str = (int)$str;
            $parLen = strlen($str);
            if (empty($str) || $originLen !== $parLen) {
                return false;
            }
        }
        return ($type === gettype($str));
    }
}
