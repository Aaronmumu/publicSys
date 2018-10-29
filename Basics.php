<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/9/4
 * Time: 16:39
 */

class AskPublicSystemService_Basics extends Common_AskPublicSystemService
{
    /**
     * 获取订阅列表
     * @param $condition
     * @param string $type
     * @return array
     */
    public function getAPISubscribeList($condition = [], $type = '')
    {
        $list = [];

        $condition = [
            'codes' => isset($condition['smt_code_in']) ? $condition['smt_code_in'] : [],
            'method' => 0,//0 API; 1 邮件订阅
        ];

        $this->apiCode = '0010007';
        $this->rightReturnFormat = [
            [
                'SubscribeTypeId',
                'Code',
                'Name',
                'Type',
                'Method',
            ]
        ];

        try {
            $sendPost = $this->sendPost($this->apiCode, $condition);
            $validatorResponseParams = $this->validatorResponseParams($sendPost);

            if ($validatorResponseParams['ask'] == Common_AskPublicSystemService::SUCCESS) {
                $list = $validatorResponseParams['data'];
                if (!empty($list)) {
                    $list = self::mappingRelationArrFormat([
                        'smt_id' => 'SubscribeTypeId',
                        'smt_code' => 'Code',
                        'smt_name' => 'Name',
                        'smt_type' => 'Type',
                        'smt_method' => 'Method',
                    ], $list);

                    switch ($type) {
                        case 'getByField' :
                            $list = $list[0];
                            break;
                    }
                }
            }
        } catch (Exception $e) {
            $list = [];
        }
        return $list;
    }

    /**
     * 客户是否存在API订阅
     * @param $customerCode
     * @param $subscribeTypeCode
     * @return bool 返回 false || messageChannelKey(string)
     */
    public function getExistAPISubscribe($customerCode, $subscribeTypeCode)
    {
        $messageChannelKey = fasle;

        $this->apiCode = '0010008';
        $this->rightReturnFormat = [
            'IsExist' => '布尔型',
            'MessageChannelKey' => 'key值',
        ];

        try {
            $sendPost = $this->sendPost($this->apiCode, [
                'CustomerCode' => $customerCode,
                'SubscribeTypeCode' => $subscribeTypeCode,
            ]);
            $validatorResponseParams = $this->validatorResponseParams($sendPost, $this->rightReturnFormat);

            if ($validatorResponseParams['ask'] == Common_AskPublicSystemService::SUCCESS
                && $validatorResponseParams['data']['IsExist'] === true
            ) {
                $messageChannelKey = $validatorResponseParams['data']['MessageChannelKey'];
            }
        } catch (Exception $e) {
            $messageChannelKey = fasle;
        }
        return $messageChannelKey;
    }

    /**
     * 获取当前登录用户的角色类型及绑定的客户
     * @return array
     */
    public function getUserPower()
    {
        $result = [
            'ask' => Common_AskPublicSystemService::FAIL,
            'errMessage' => '',
            'data' => []
        ];

        $this->apiCode = '0010010';
        $this->rightReturnFormat = [
            'type' => '-1/0/1/2;-1即其他角色;0客服代表;1销售经理;2 客服代表&&销售经理;',
            'customerCode' => '拥有的客户code数组',
        ];

        try {
            $sendPost = $this->sendPost($this->apiCode);
            $validatorResponseParams = $this->validatorResponseParams($sendPost, $this->rightReturnFormat);

            if ($validatorResponseParams['ask'] == Common_AskPublicSystemService::SUCCESS) {
                $result['ask'] = Common_AskPublicSystemService::SUCCESS;
                $result['data'] = $validatorResponseParams['data'];
            } else {
                throw new Exception($validatorResponseParams['errMessage']);
            }
        } catch (Exception $e) {
            $result['errMessage'] = $e->getMessage();
        }
        return $result;
    }

    /**
     * 获取当前登录用户的角色类型及绑定的客户
     * @param string $type
     * @return array
     */
    public function getCurrUserPower()
    {
        $result = [
            'errMessage' => false,
            'isSelectSelfRelation' => false,
            'customerCode' => [],
        ];
        $getCustomerByUser = $this->getUserPower();

        if ($getCustomerByUser['ask'] == Common_AskPublicSystemService::SUCCESS) {
            $result['isSelectSelfRelation'] = in_array($getCustomerByUser['data']['type'], [0, 1, 2]) ? true : false;
            $result['customerCode'] = $getCustomerByUser['data']['customerCode'];
        } else {
            $result['errMessage'] = $getCustomerByUser['errMessage'];
        }
        return $result;
    }

    /**
     * 获取用户的客户查询权限
     * @param $uid
     * @return array
     */
    public static function getUserHadCustomers($uid = '')
    {
        $result = [
            'errMessage' => false,
            'isSelectSelfRelation' => false,
            'customerCode' => [],
        ];

        try {
            $getCustomerByUser = Common_CurrentUser::getCurrentUser();
            $getCustomerByUser['UserHadCustomers'] = $getCustomerByUser['UserHadCustomers']['ResponseData'];
            if (!empty($getCustomerByUser['UserHadCustomers'])
                && isset($getCustomerByUser['UserHadCustomers']['CustomerPermissionType'])
                && in_array($getCustomerByUser['UserHadCustomers']['CustomerPermissionType'], [0, 1])
            ) {
                $result['isSelectSelfRelation'] = $getCustomerByUser['UserHadCustomers']['CustomerPermissionType'] == 1 ? true : false;
                $result['customerCode'] = $getCustomerByUser['UserHadCustomers']['CustomerList'];
                if (!empty($result['customerCode'])) {
                    $customer = Service_Customer::getByCondition([
                        'customer_code_in' => $result['customerCode']
                    ], 'customer_id');
                    $result['customerId'] = array_column($customer, 'customer_id');
                }
            } else {
                $result['errMessage'] = '获取用户拥有的客户失败';
            }
        } catch (Exception $e) {
            $result['errMessage'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * 获取当前用户查询
     * @param array $return
     * @param array $condition 查询条件
     * @param int $type 区分不同情况的不同处理无特殊意义
     * @return array
     * @throws Exception
     */
    public function getCurrUserPowerSelect($return, $condition, $type = 1)
    {
        if (!is_array($return) || !is_array($condition)) {
            throw new Exception('传入参数必须为数组');
        }
        //是否只查询自己关联的客户客销
        $userPower = self::getUserHadCustomers();

        switch ($type) {
            case 1 :
                if ($userPower['errMessage'] !== false) {
                    $return['message'] = $userPower['errMessage'];
                    die(Zend_Json::encode($return));
                }
                if ($userPower['isSelectSelfRelation'] === true) {
                    if (empty($userPower['customerCode'])) {
                        die(Zend_Json::encode($return));
                    } else {
                        $condition['customer_code_in'] = $userPower['customerCode'];
                    }
                }
                return [
                    $return,
                    $condition,
                ];
                break;
            case 2 :
                if ($userPower['errMessage'] !== false) {
                    die('no data');
                }
                if ($userPower['isSelectSelfRelation'] === true) {
                    if (empty($userPower['customerCode'])) {
                        die('no data');
                    } else {
                        $condition['customer_code_in'] = $userPower['customerCode'];
                    }
                }
                return [$condition];
                break;
            case 3 :
                if ($userPower['errMessage'] !== false) {
                    header("Content-type: text/html; charset=utf-8");
                    echo "No Data";
                    exit;
                }
                if ($userPower['isSelectSelfRelation'] === true) {
                    if (empty($userPower['customerCode'])) {
                        header("Content-type: text/html; charset=utf-8");
                        echo "No Data";
                        exit;
                    } else {
                        $condition['customer_code_in'] = $userPower['customerCode'];
                    }
                }
                return [$condition];
                break;
            case 4 :
                if ($userPower['errMessage'] !== false) {
                    $return['message'] = $userPower['errMessage'];
                    die(Zend_Json::encode($return));
                }
                if ($userPower['isSelectSelfRelation'] === true) {
                    if (empty($userPower['customerCode'])) {
                        die(Zend_Json::encode($return));
                    } else {
                        if (!empty($condition['customer_code'])) {
                            unset($condition['customer_code_in']);
                            if (!in_array($condition['customer_code'], $userPower['customerCode'])) {
                                $return['message'] = "当前账户无权限查看" . $condition['customer_code'];
                                die(Zend_Json::encode($return));
                            }
                        }
                    }
                }
                return [
                    $return,
                    $condition,
                ];
                break;
            case 5 :
                if ($userPower['errMessage'] !== false) {
                    $return['message'] = $userPower['errMessage'];
                    die(Zend_Json::encode($return));
                }
                if ($userPower['isSelectSelfRelation'] === true) {
                    if (empty($userPower['customerCode'])) {
                        $return['message'] = "当前账户无关联客户";
                        die(Zend_Json::encode($return));
                    } else {
                        if (!in_array($condition['customer_code'], $userPower['customerCode'])) {
                            $return['message'] = "当前账户无权限查看" . $condition['customer_code'];
                            die(Zend_Json::encode($return));
                        }
                    }
                }
                return [
                    $return,
                    $condition,
                ];
                break;
            case 6 :
                if ($userPower['errMessage'] !== false) {
                    $condition['customer_code_in'] = [-1];
                } elseif ($userPower['isSelectSelfRelation'] === true) {
                    if (empty($userPower['customerCode'])) {
                        $condition['customer_code_in'] = [-1];
                    } else {
                        $condition['customer_code_in'] = $userPower['customerCode'];
                    }
                }
                return [$condition];
                break;
            case 7 :
                $data = '';
                if ($userPower['errMessage'] !== false) {
                    $data = '-1';
                } elseif ($userPower['isSelectSelfRelation'] === true) {
                    if (empty($userPower['customerCode'])) {
                        $data = '-1';
                    } else {
                        foreach ($userPower['customerCode'] as $code) {
                            $data .= "'$code',";
                        }
                    }
                }
                $condition['customer_code_in'] = trim($data, ',');
                return [$condition];
                break;
            case 8 :
                if ($userPower['errMessage'] !== false) {
                    die('没有数据');
                }
                if ($userPower['isSelectSelfRelation'] === true) {
                    if (empty($userPower['customerCode'])) {
                        die('没有数据');
                    } else {
                        $condition['customer_code_in'] = $userPower['customerCode'];
                    }
                }
                return [$condition];
                break;
            case 9 :
                if ($userPower['errMessage'] !== false) {
                    $return['message'] = $userPower['errMessage'];
                    die(Zend_Json::encode($return));
                }
                if ($userPower['isSelectSelfRelation'] === true) {
                    if (empty($userPower['customerCode'])) {
                        die(Zend_Json::encode($return));
                    } else {
                        if (!empty($condition['customer_code']) && !in_array($condition['customer_code'], $userPower['customerCode'])) {
                            die($return);
                        }
                        if (empty($condition['customer_code'])) {
                            $condition['customer_code'] = $userPower['customerCode'];
                        }
                    }
                }
                return [
                    $return,
                    $condition,
                ];
                break;
            case 10 :
                if ($userPower['errMessage'] !== false) {
                    $return['message'] = $userPower['errMessage'];
                    die(Zend_Json::encode($return));
                }
                if ($userPower['isSelectSelfRelation'] === true) {
                    if (empty($userPower['customerCode'])) {
                        $return['message'] = 'No Data';
                        die(Zend_Json::encode($return));
                    } else {
                        $condition['customer_code_in'] = $userPower['customerCode'];
                    }
                }
                return [
                    $return,
                    $condition,
                ];
                break;
            case 11 :
                return $userPower;
                break;
            case 12 :
                if ($userPower['errMessage'] !== false) {
                    $return['message'] = $userPower['errMessage'];
                    die(Zend_Json::encode($return));
                }
                if ($userPower['isSelectSelfRelation'] === true) {
                    if (empty($userPower['customerCode'])) {
                        die(Zend_Json::encode($return));
                    } else {
                        $condition['customer_id_in'] = $userPower['customerId'];
                    }
                }
                return [
                    $return,
                    $condition,
                ];
                break;
            case 13 :
                if ($userPower['errMessage'] !== false) {
                    $return['message'] = $userPower['errMessage'];
                    die(Zend_Json::encode($return));
                }
                if ($userPower['isSelectSelfRelation'] === true) {
                    if (empty($userPower['customerCode'])) {
                        die(Zend_Json::encode($return));
                    } else {
                        $condition['customer_id_in'] = $userPower['customerId'];
                    }
                }
                return [
                    $condition,
                ];
                break;
        }
    }

    /**
     * 获取访问Token
     * @param $requestData
     * @return array
     */
    public function getAccessToken($requestData)
    {
        $result = [
            'ask' => Common_AskPublicSystemService::FAIL,
            'errMessage' => '',
            'AccessToken' => ''
        ];

        $this->apiCode = '0050001';
        $this->rightReturnFormat = [
            'AccessToken' => 'AccessToken'
        ];

        try {
            $sendPost = $this->sendPost($this->apiCode, $requestData);
            $validatorResponseParams = $this->validatorResponseParams($sendPost, $this->rightReturnFormat);

            if ($validatorResponseParams['ask'] == Common_AskPublicSystemService::SUCCESS) {
                $result['ask'] = Common_AskPublicSystemService::SUCCESS;
                $result['AccessToken'] = $validatorResponseParams['data'];
            } else {
                throw new Exception($validatorResponseParams['errMessage']);
            }
        } catch (Exception $e) {
            $result['errMessage'] = $e->getMessage();
        }
        return $result;
    }

    /**
     * 获取用户权限
     * @param $requestData
     * @return array
     */
    public function getUserPermission($requestData)
    {
        $result = [
            'ask' => Common_AskPublicSystemService::FAIL,
            'errMessage' => '',
            'data' => []
        ];

        $this->apiCode = '0050002';
        $this->rightReturnFormat = [];

        try {
            $sendPost = $this->sendPost($this->apiCode, $requestData);
            $validatorResponseParams = $this->validatorResponseParams($sendPost);

            if ($validatorResponseParams['ask'] == Common_AskPublicSystemService::SUCCESS) {
                $result['ask'] = Common_AskPublicSystemService::SUCCESS;
                $result['data'] = $validatorResponseParams['data'];
            } else {
                throw new Exception($validatorResponseParams['errMessage']);
            }
        } catch (Exception $e) {
            $result['errMessage'] = $e->getMessage();
        }
        return $result;
    }

    /**
     * 获取角色
     * @param $roleId
     * @return array
     */
    public function getRole($roleId)
    {
        $result = [
            'ask' => Common_AskPublicSystemService::FAIL,
            'errMessage' => '',
            'data' => []
        ];

        $this->apiCode = '0050003';
        $this->rightReturnFormat = [
            'RoleId' => '角色ID',
            'Name' => '角色名称',
            'NameEn' => '角色英文名称',
        ];

        try {
            if (empty($roleId)) {
                throw new Exception('参数错误：roleId不能为空');
            }

            $sendData = [
                'RoleId' => $roleId
            ];

            $sendPost = $this->sendPost($this->apiCode, $sendData);
            $validatorResponseParams = $this->validatorResponseParams($sendPost, $this->rightReturnFormat);

            if ($validatorResponseParams['ask'] == Common_AskPublicSystemService::SUCCESS) {
                $result['ask'] = Common_AskPublicSystemService::SUCCESS;
                $result['data'] = $validatorResponseParams['data'];
            } else {
                throw new Exception($validatorResponseParams['errMessage']);
            }
        } catch (Exception $e) {
            $result['errMessage'] = $e->getMessage();
        }
        return $result;
    }

    /**
     * 获取当前用户
     * @param int $type
     * @throws Exception
     */
    public function getCurrUserRoleName($roleId = 'currUser', $type = 1)
    {
        if ($roleId == 'currUser') {
            $userInfo = Common_CurrentUser::getCurrentUser();
            $roleId = $userInfo['RoleId'];
        }
        $getRole = (new AskPublicSystemService_Basics())->getRole($roleId);
        switch ($type) {
            case 1 :
                if ($getRole['ask'] == Common_AskPublicSystemService::SUCCESS) {
                    return $getRole['data']['Name'];
                } else {
                    throw new Exception($getRole['errMessage']);
                }
                break;
        }
    }
}
